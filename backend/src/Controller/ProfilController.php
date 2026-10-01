<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Service\EmailUniciteService;
use App\Service\GenerateurTokenService;
use App\Service\NotificationEmailService;
use App\Service\PolitiqueMotDePasseService;
use App\Service\PseudoUniciteService;
use App\Service\TelephoneService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class ProfilController extends AbstractController
{
    // Infos du membre connecté (jamais le mot de passe)
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

    // Modifie le profil connecté (pas l'email ni la date de naissance, non modifiables ici)
    #[Route('/api/profil', name: 'api_profil_modifier', methods: ['PATCH'])]
    public function modifierProfil(Request $request, EntityManagerInterface $em, PseudoUniciteService $pseudoUniciteService, TelephoneService $telephoneService): JsonResponse
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

        // vérifie que le pseudo n'est pas pris par un autre
        if ($data['pseudo'] !== $utilisateur->getPseudo() && !$pseudoUniciteService->estDisponible($data['pseudo'])) {
            return $this->json(['message' => 'Ce pseudo est déjà utilisé.'], 409);
        }

        if (!empty($data['telephone']) && !$telephoneService->estValide($data['telephone'])) {
            return $this->json(['message' => 'Le numéro de téléphone doit contenir 10 chiffres et commencer par 0.'], 400);
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

    // Change le mot de passe, exige l'ancien pour confirmer que c'est bien lui
    #[Route('/api/profil/mot-de-passe', name: 'api_profil_mot_de_passe', methods: ['PATCH'])]
    public function changerMotDePasse(
        Request                     $request,
        EntityManagerInterface      $em,
        UserPasswordHasherInterface $passwordHasher,
        PolitiqueMotDePasseService  $politiqueMotDePasseService
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
        $erreurs = $politiqueMotDePasseService->erreurs($nouveauMotDePasse);

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

    // Demande de changement d'email, applique rien tout de suite.
    // Exige le mot de passe actuel. Email de confirmation à la nouvelle
    // adresse + email d'alerte à l'ancienne (si demande frauduleuse)
    #[Route('/api/profil/changer-email', name: 'api_profil_changer_email', methods: ['POST'])]
    public function demanderChangementEmail(
        Request                     $request,
        EntityManagerInterface      $em,
        UserPasswordHasherInterface $passwordHasher,
        NotificationEmailService    $notificationEmailService,
        EmailUniciteService         $emailUniciteService,
        GenerateurTokenService      $generateurTokenService
    ): JsonResponse
    {
        /** @var Utilisateur $utilisateur */
        $utilisateur = $this->getUser();
        $data = json_decode($request->getContent(), true);

        if (empty($data['mot_de_passe_actuel']) || empty($data['nouvel_email'])) {
            return $this->json(['message' => 'Le mot de passe actuel et le nouvel email sont obligatoires.'], 400);
        }

        if (!$passwordHasher->isPasswordValid($utilisateur, $data['mot_de_passe_actuel'])) {
            return $this->json(['message' => 'Mot de passe incorrect.'], 400);
        }

        $nouvelEmail = $data['nouvel_email'];

        if (!filter_var($nouvelEmail, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['message' => 'Le format de l\'email est invalide.'], 400);
        }

        if ($nouvelEmail === $utilisateur->getEmail()) {
            return $this->json(['message' => 'C\'est déjà votre email actuel.'], 400);
        }

        if (!$emailUniciteService->estDisponible($nouvelEmail)) {
            return $this->json(['message' => 'Cet email est déjà utilisé par un autre compte.'], 409);
        }

        $token = $generateurTokenService->generer();

        $utilisateur->setNouvelEmailEnAttente($nouvelEmail);
        $utilisateur->setTokenChangementEmail($token);
        $utilisateur->setTokenChangementEmailExpireLe((new \DateTime())->modify('+1 hour'));

        $em->flush();

        $lienConfirmation = 'http://localhost:4200/confirmer-email/' . $token;

        $notificationEmailService->envoyer(
            $nouvelEmail,
            'Confirmez votre nouvel email — Brouilla&Mat',
            "Bonjour,\n\n"
            . "Vous avez demandé à utiliser cette adresse comme nouvel email de connexion "
            . "sur le site du club Brouilla&Mat.\n\n"
            . "Pour confirmer, cliquez sur ce lien (valable 1 heure) :\n$lienConfirmation\n\n"
            . "Si vous n'êtes pas à l'origine de cette demande, ignorez simplement cet email.",
            '<p>Bonjour,</p>'
            . '<p>Vous avez demandé à utiliser cette adresse comme nouvel email de connexion '
            . 'sur le site du club Brouilla&amp;Mat.</p>'
            . '<p><a href="' . $lienConfirmation . '" '
            . 'style="display:inline-block;padding:12px 24px;background-color:#CE865A;'
            . 'color:#2D302C;text-decoration:none;border-radius:8px;font-weight:bold;">'
            . 'Confirmer mon nouvel email</a></p>'
            . '<p>Ce lien est valable 1 heure.</p>'
            . '<p>Si vous n\'êtes pas à l\'origine de cette demande, ignorez simplement cet email.</p>'
        );

        $notificationEmailService->envoyer(
            $utilisateur->getEmail(),
            'Alerte : changement d\'email demandé sur votre compte — Brouilla&Mat',
            "Bonjour,\n\n"
            . "Une demande de changement d'email a été effectuée sur votre compte, "
            . "vers l'adresse : $nouvelEmail.\n\n"
            . "Si c'est bien vous, aucune action n'est nécessaire.\n\n"
            . "Si ce n'est PAS vous, contactez un administrateur du club dès que possible : "
            . "votre compte pourrait être compromis."
        );

        return $this->json(['message' => 'Un email de confirmation a été envoyé à votre nouvelle adresse.']);
    }

    // Confirme le changement d'email via le lien reçu, c'est ici que ça change vraiment.
    // Route publique (lien cliqué depuis la boîte mail), le jeton sert de preuve d'identité
    #[Route('/api/profil/confirmer-email/{token}', name: 'api_profil_confirmer_email', methods: ['POST'])]
    public function confirmerChangementEmail(string $token, EntityManagerInterface $em): JsonResponse
    {
        $utilisateur = $em->getRepository(Utilisateur::class)->findOneBy(['token_changement_email' => $token]);

        if (!$utilisateur) {
            return $this->json(['message' => 'Lien invalide ou déjà utilisé.'], 404);
        }

        if ($utilisateur->getTokenChangementEmailExpireLe() < new \DateTime()) {
            return $this->json(['message' => 'Ce lien a expiré. Merci de refaire la demande depuis votre profil.'], 410);
        }

        $utilisateur->setEmail($utilisateur->getNouvelEmailEnAttente());
        $utilisateur->setNouvelEmailEnAttente(null);
        $utilisateur->setTokenChangementEmail(null);
        $utilisateur->setTokenChangementEmailExpireLe(null);

        $em->flush();

        return $this->json(['message' => 'Votre email a bien été mis à jour. Vous pouvez maintenant vous connecter avec.']);
    }
}
