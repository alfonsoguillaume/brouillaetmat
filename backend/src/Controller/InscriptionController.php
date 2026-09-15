<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class InscriptionController extends AbstractController
{
    /**
     * Route publique (voir access_control) : crée un nouveau compte membre,
     * avec un statut "en_attente" tant qu'un admin ne l'a pas validé.
     */
    #[Route('/api/inscription', name: 'api_inscription', methods: ['POST'])]
    public function inscription(
        Request                     $request,
        EntityManagerInterface      $em,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

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
        $utilisateur->setDateNaissance(new \DateTime($data['date_naissance']));
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
