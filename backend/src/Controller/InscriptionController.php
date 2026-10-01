<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Service\EmailUniciteService;
use App\Service\LimiteurAppelsService;
use App\Service\PolitiqueMotDePasseService;
use App\Service\PseudoUniciteService;
use App\Service\TelephoneService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

class InscriptionController extends AbstractController
{
    // Route publique, crée un compte en_attente jusqu'à validation admin
    #[Route('/api/inscription', name: 'api_inscription', methods: ['POST'])]
    public function inscription(
        Request                                                        $request,
        EntityManagerInterface                                         $em,
        UserPasswordHasherInterface                                    $passwordHasher,
        PseudoUniciteService                                           $pseudoUniciteService,
        EmailUniciteService                                            $emailUniciteService,
        PolitiqueMotDePasseService                                     $politiqueMotDePasseService,
        TelephoneService                                                $telephoneService,
        LimiteurAppelsService                                           $limiteurAppelsService,
        #[Autowire(service: 'limiter.inscription')] RateLimiterFactory $inscriptionLimiter
    ): JsonResponse
    {
        // anti-spam, limite les inscriptions par IP
        if ($limiteurAppelsService->depasse($inscriptionLimiter, $request->getClientIp())) {
            return $this->json([
                'message' => 'Trop de tentatives d\'inscription. Réessayez plus tard.',
            ], 429);
        }

        $data = json_decode($request->getContent(), true);

        // 1. champs obligatoires
        $champsRequis = ['nom', 'prenom', 'pseudo', 'email', 'date_naissance', 'password'];
        foreach ($champsRequis as $champ) {
            if (empty($data[$champ])) {
                return $this->json(['message' => "Le champ \"$champ\" est obligatoire."], 400);
            }
        }

        // 2. format email (jamais confiance au frontend)
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->json(['message' => 'Le format de l\'email est invalide.'], 400);
        }

        // 3. complexité mot de passe
        $erreursMotDePasse = $politiqueMotDePasseService->erreurs($data['password']);

        if (!empty($erreursMotDePasse)) {
            return $this->json([
                'message' => 'Le mot de passe doit contenir : ' . implode(', ', $erreursMotDePasse) . '.',
            ], 400);
        }

        // 4. cohérence date de naissance
        try {
            $dateNaissance = new \DateTime($data['date_naissance']);
        } catch (\Exception $e) {
            return $this->json(['message' => 'La date de naissance est invalide.'], 400);
        }

        $aujourdhui = new \DateTime();
        if ($dateNaissance > $aujourdhui) {
            return $this->json(['message' => 'La date de naissance ne peut pas être dans le futur.'], 400);
        }

        $age = $aujourdhui->diff($dateNaissance)->y;
        if ($age > 120) {
            return $this->json(['message' => 'La date de naissance est invalide.'], 400);
        }

        // 5. email tuteur / minorité
        $estMineur = $age < 18;
        if ($estMineur && empty($data['email_tuteur'])) {
            return $this->json(['message' => 'L\'email du tuteur légal est obligatoire pour un membre mineur.'], 400);
        }

        if (!empty($data['email_tuteur']) && !filter_var($data['email_tuteur'], FILTER_VALIDATE_EMAIL)) {
            return $this->json(['message' => 'Le format de l\'email du tuteur légal est invalide.'], 400);
        }

        if (!empty($data['email_tuteur']) && strtolower($data['email']) === strtolower($data['email_tuteur'])) {
            return $this->json(['message' => 'L\'email du membre et l\'email du tuteur doivent être différents.'], 400);
        }

        // 6. format téléphone (optionnel, vérifié si rempli)
        if (!empty($data['telephone']) && !$telephoneService->estValide($data['telephone'])) {
            return $this->json(['message' => 'Le numéro de téléphone doit contenir 10 chiffres et commencer par 0.'], 400);
        }

        if (!$emailUniciteService->estDisponible($data['email'])) {
            return $this->json(['message' => 'Cet email est déjà utilisé.'], 409);
        }

        if (!$pseudoUniciteService->estDisponible($data['pseudo'])) {
            return $this->json(['message' => 'Ce pseudo est déjà utilisé.'], 409);
        }

        $utilisateur = new Utilisateur();
        $utilisateur->setNom($data['nom']);
        $utilisateur->setPrenom($data['prenom']);
        $utilisateur->setPseudo($data['pseudo']);
        $utilisateur->setEmail($data['email']);
        $utilisateur->setRole('membre');
        $utilisateur->setDateNaissance($dateNaissance);
        $utilisateur->setStatutInscription('en_attente');
        $utilisateur->setTelephone($data['telephone'] ?? null);
        $utilisateur->setEmailTuteur($data['email_tuteur'] ?? null);
        // consentement = donné si email tuteur fourni
        $utilisateur->setConsentementParental(!empty($data['email_tuteur']));

        // mot de passe hashé avant stockage
        $utilisateur->setMotDePasse(
            $passwordHasher->hashPassword($utilisateur, $data['password'])
        );

        $em->persist($utilisateur);
        $em->flush();

        return $this->json([
            'message' => 'Inscription enregistrée, en attente de validation par un administrateur.',
        ], 201);
    }

    // Route protégée (JWT), renvoie qui est connecté (pour Angular après login)
    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();

        return $this->json([
            'email' => $user->getUserIdentifier(),
            'pseudo' => $user->getPseudo(),
            'roles' => $user->getRoles(),
        ]);
    }
}
