<?php

namespace App\Controller;

use App\Entity\Utilisateur;
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
    /**
     * Route publique (voir access_control) : crée un nouveau compte membre,
     * avec un statut "en_attente" tant qu'un admin ne l'a pas validé.
     */
    #[Route('/api/inscription', name: 'api_inscription', methods: ['POST'])]
    public function inscription(
        Request                                                        $request,
        EntityManagerInterface                                         $em,
        UserPasswordHasherInterface                                    $passwordHasher,
        #[Autowire(service: 'limiter.inscription')] RateLimiterFactory $inscriptionLimiter
    ): JsonResponse
    {
        // Anti-spam : limite le nombre d'inscriptions par adresse IP,
        // pour éviter qu'un robot crée des centaines de faux comptes.
        $limiteur = $inscriptionLimiter->create($request->getClientIp());
        if (!$limiteur->consume(1)->isAccepted()) {
            return $this->json([
                'message' => 'Trop de tentatives d\'inscription. Réessayez plus tard.',
            ], 429);
        }

        $data = json_decode($request->getContent(), true);

        // 1. Champs obligatoires : on vérifie leur présence avant d'y toucher,
        // pour éviter un plantage PHP brut si l'un d'eux manque.
        $champsRequis = ['nom', 'prenom', 'pseudo', 'email', 'date_naissance', 'password'];
        foreach ($champsRequis as $champ) {
            if (empty($data[$champ])) {
                return $this->json(['message' => "Le champ \"$champ\" est obligatoire."], 400);
            }
        }

        // 2. Format de l'email : ne jamais faire confiance au frontend pour ça.
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->json(['message' => 'Le format de l\'email est invalide.'], 400);
        }

        // 3. Complexité du mot de passe : 12 caractères minimum, avec au moins
        // une majuscule, une minuscule, un chiffre et un caractère spécial.
        $motDePasse = $data['password'];
        $erreursMotDePasse = [];

        if (strlen($motDePasse) < 12) {
            $erreursMotDePasse[] = '12 caractères minimum';
        }
        if (!preg_match('/[A-Z]/', $motDePasse)) {
            $erreursMotDePasse[] = 'une majuscule';
        }
        if (!preg_match('/[a-z]/', $motDePasse)) {
            $erreursMotDePasse[] = 'une minuscule';
        }
        if (!preg_match('/\d/', $motDePasse)) {
            $erreursMotDePasse[] = 'un chiffre';
        }
        if (!preg_match('/[^A-Za-z0-9]/', $motDePasse)) {
            $erreursMotDePasse[] = 'un caractère spécial';
        }

        if (!empty($erreursMotDePasse)) {
            return $this->json([
                'message' => 'Le mot de passe doit contenir : ' . implode(', ', $erreursMotDePasse) . '.',
            ], 400);
        }

        // 4. Cohérence de la date de naissance.
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

        // 5. Cohérence email du tuteur / minorité.
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

        // 6. Format du téléphone (champ optionnel, mais vérifié s'il est rempli).
        if (!empty($data['telephone'])) {
            $telephoneNettoye = preg_replace('/\s+/', '', $data['telephone']);
            if (!preg_match('/^0\d{9}$/', $telephoneNettoye)) {
                return $this->json(['message' => 'Le numéro de téléphone doit contenir 10 chiffres et commencer par 0.'], 400);
            }
        }

        $utilisateurRepository = $em->getRepository(Utilisateur::class);

        if ($utilisateurRepository->findOneBy(['email' => $data['email']])) {
            return $this->json(['message' => 'Cet email est déjà utilisé.'], 409);
        }

        if ($utilisateurRepository->findOneBy(['pseudo' => $data['pseudo']])) {
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
        // Le consentement est considéré donné si un email de tuteur a été fourni (mineur).
        $utilisateur->setConsentementParental(!empty($data['email_tuteur']));

        // On hashe le mot de passe avant de le stocker : jamais de mot de passe en clair en base.
        $utilisateur->setMotDePasse(
            $passwordHasher->hashPassword($utilisateur, $data['password'])
        );

        $em->persist($utilisateur);
        $em->flush();

        return $this->json([
            'message' => 'Inscription enregistrée, en attente de validation par un administrateur.',
        ], 201);
    }

    /**
     * Route protégée (nécessite un token JWT valide) : renvoie l'identité
     * de l'utilisateur actuellement connecté. Utile pour qu'Angular sache
     * "qui est connecté" et avec quel rôle, juste après le login.
     */
    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $user = $this->getUser();

        return $this->json([
            'email' => $user->getUserIdentifier(),
            'roles' => $user->getRoles(),
        ]);
    }
}
