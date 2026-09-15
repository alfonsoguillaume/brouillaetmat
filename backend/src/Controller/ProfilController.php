<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class ProfilController extends AbstractController
{
    /**
     * Renvoie les informations du membre actuellement connecté (jamais le mot de passe).
     */
    #[Route('/api/profil', name: 'api_profil_voir', methods: ['GET'])]
    public function voirProfil(): JsonResponse
    {
        /** @var Utilisateur $utilisateur */
        $utilisateur = $this->getUser();

        return $this->json([
            'nom' => $utilisateur->getNom(),
            'prenom' => $utilisateur->getPrenom(),
            'pseudo' => $utilisateur->getPseudo(),
            'email' => $utilisateur->getEmail(),
            'telephone' => $utilisateur->getTelephone(),
            'date_naissance' => $utilisateur->getDateNaissance()->format('Y-m-d'),
            'email_tuteur' => $utilisateur->getEmailTuteur(),
            'role' => $utilisateur->getRole(),
        ]);
    }

    /**
     * Modifie les informations du membre connecté (nom, prénom, pseudo, téléphone,
     * email du tuteur). L'email de connexion et la date de naissance ne sont pas
     * modifiables ici.
     */
    #[Route('/api/profil', name: 'api_profil_modifier', methods: ['PATCH'])]
    public function modifierProfil(Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var Utilisateur $utilisateur */
        $utilisateur = $this->getUser();
        $data = json_decode($request->getContent(), true);

        $champsRequis = ['nom', 'prenom', 'pseudo'];
        foreach ($champsRequis as $champ) {
            if (empty($data[$champ])) {
                return $this->json(['message' => "Le champ \"$champ\" est obligatoire."], 400);
            }
        }

        // Vérifie que le nouveau pseudo n'est pas déjà pris par QUELQU'UN D'AUTRE.
        if ($data['pseudo'] !== $utilisateur->getPseudo()) {
            $pseudoExistant = $em->getRepository(Utilisateur::class)->findOneBy(['pseudo' => $data['pseudo']]);
            if ($pseudoExistant) {
                return $this->json(['message' => 'Ce pseudo est déjà utilisé.'], 409);
            }
        }

        if (!empty($data['telephone'])) {
            $telephoneNettoye = preg_replace('/\s+/', '', $data['telephone']);
            if (!preg_match('/^0\d{9}$/', $telephoneNettoye)) {
                return $this->json(['message' => 'Le numéro de téléphone doit contenir 10 chiffres et commencer par 0.'], 400);
            }
        }

        if (!empty($data['email_tuteur']) && !filter_var($data['email_tuteur'], FILTER_VALIDATE_EMAIL)) {
            return $this->json(['message' => 'Le format de l\'email du tuteur légal est invalide.'], 400);
        }

        $utilisateur->setNom($data['nom']);
        $utilisateur->setPrenom($data['prenom']);
        $utilisateur->setPseudo($data['pseudo']);
        $utilisateur->setTelephone($data['telephone'] ?? null);
        $utilisateur->setEmailTuteur($data['email_tuteur'] ?? null);

        $em->flush();

        return $this->json(['message' => 'Profil mis à jour.']);
    }

    /**
     * Change le mot de passe du membre connecté. Exige l'ancien mot de passe
     * pour confirmer que c'est bien lui qui fait la demande.
     */
    #[Route('/api/profil/mot-de-passe', name: 'api_profil_mot_de_passe', methods: ['PATCH'])]
    public function changerMotDePasse(
        Request                     $request,
        EntityManagerInterface      $em,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse
    {
        /** @var Utilisateur $utilisateur */
        $utilisateur = $this->getUser();
        $data = json_decode($request->getContent(), true);

        if (empty($data['mot_de_passe_actuel']) || empty($data['nouveau_mot_de_passe'])) {
            return $this->json(['message' => 'Merci de remplir l\'ancien et le nouveau mot de passe.'], 400);
        }

        if (!$passwordHasher->isPasswordValid($utilisateur, $data['mot_de_passe_actuel'])) {
            return $this->json(['message' => 'L\'ancien mot de passe est incorrect.'], 400);
        }

        $nouveauMotDePasse = $data['nouveau_mot_de_passe'];
        $erreurs = [];

        if (strlen($nouveauMotDePasse) < 12) {
            $erreurs[] = '12 caractères minimum';
        }
        if (!preg_match('/[A-Z]/', $nouveauMotDePasse)) {
            $erreurs[] = 'une majuscule';
        }
        if (!preg_match('/[a-z]/', $nouveauMotDePasse)) {
            $erreurs[] = 'une minuscule';
        }
        if (!preg_match('/\d/', $nouveauMotDePasse)) {
            $erreurs[] = 'un chiffre';
        }
        if (!preg_match('/[^A-Za-z0-9]/', $nouveauMotDePasse)) {
            $erreurs[] = 'un caractère spécial';
        }

        if (!empty($erreurs)) {
            return $this->json([
                'message' => 'Le nouveau mot de passe doit contenir : ' . implode(', ', $erreurs) . '.',
            ], 400);
        }

        $utilisateur->setMotDePasse(
            $passwordHasher->hashPassword($utilisateur, $nouveauMotDePasse)
        );

        $em->flush();

        return $this->json(['message' => 'Mot de passe modifié avec succès.']);
    }
}
