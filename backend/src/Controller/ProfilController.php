<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
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

    /**
     * Demande un changement d'email — n'applique RIEN tout de suite. Exige
     * le mot de passe actuel (empêche quelqu'un qui aurait volé une session
     * de détourner le compte sans connaître le mot de passe). Envoie un
     * email de confirmation à la NOUVELLE adresse (le changement ne devient
     * réel qu'au clic dessus) et un email d'alerte à l'ANCIENNE adresse
     * (purement informatif, pour prévenir le vrai propriétaire en cas de
     * demande frauduleuse).
     */
    #[Route('/api/profil/changer-email', name: 'api_profil_changer_email', methods: ['POST'])]
    public function demanderChangementEmail(
        Request                     $request,
        EntityManagerInterface      $em,
        UserPasswordHasherInterface $passwordHasher,
        MailerInterface             $mailer
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

        $emailExistant = $em->getRepository(Utilisateur::class)->findOneBy(['email' => $nouvelEmail]);
        if ($emailExistant) {
            return $this->json(['message' => 'Cet email est déjà utilisé par un autre compte.'], 409);
        }

        // 32 octets aléatoires -> 64 caractères hexadécimaux, impossible à deviner.
        $token = bin2hex(random_bytes(32));

        $utilisateur->setNouvelEmailEnAttente($nouvelEmail);
        $utilisateur->setTokenChangementEmail($token);
        $utilisateur->setTokenChangementEmailExpireLe((new \DateTime())->modify('+1 hour'));

        $em->flush();

        $lienConfirmation = 'http://localhost:4200/confirmer-email/' . $token;

        $mailer->send((new Email())
            ->from('brouillaetmat@gmail.com')
            ->to($nouvelEmail)
            ->subject('Confirmez votre nouvel email — Brouilla&Mat')
            ->text(
                "Bonjour,\n\n"
                . "Vous avez demandé à utiliser cette adresse comme nouvel email de connexion "
                . "sur le site du club Brouilla&Mat.\n\n"
                . "Pour confirmer, cliquez sur ce lien (valable 1 heure) :\n$lienConfirmation\n\n"
                . "Si vous n'êtes pas à l'origine de cette demande, ignorez simplement cet email."
            )
            ->html(
                '<p>Bonjour,</p>'
                . '<p>Vous avez demandé à utiliser cette adresse comme nouvel email de connexion '
                . 'sur le site du club Brouilla&amp;Mat.</p>'
                . '<p><a href="' . $lienConfirmation . '" '
                . 'style="display:inline-block;padding:12px 24px;background-color:#CE865A;'
                . 'color:#2D302C;text-decoration:none;border-radius:8px;font-weight:bold;">'
                . 'Confirmer mon nouvel email</a></p>'
                . '<p>Ce lien est valable 1 heure.</p>'
                . '<p>Si vous n\'êtes pas à l\'origine de cette demande, ignorez simplement cet email.</p>'
            ));

        $mailer->send((new Email())
            ->from('brouillaetmat@gmail.com')
            ->to($utilisateur->getEmail())
            ->subject('Alerte : changement d\'email demandé sur votre compte — Brouilla&Mat')
            ->text(
                "Bonjour,\n\n"
                . "Une demande de changement d'email a été effectuée sur votre compte, "
                . "vers l'adresse : $nouvelEmail.\n\n"
                . "Si c'est bien vous, aucune action n'est nécessaire.\n\n"
                . "Si ce n'est PAS vous, contactez un administrateur du club dès que possible : "
                . "votre compte pourrait être compromis."
            ));

        return $this->json(['message' => 'Un email de confirmation a été envoyé à votre nouvelle adresse.']);
    }

    /**
     * Confirme un changement d'email via le lien reçu — c'est SEULEMENT à
     * cet instant que l'email officiel change réellement. Route publique
     * (le lien est cliqué depuis une boîte mail, pas forcément dans une
     * session connectée) : la preuve d'identité est le jeton lui-même,
     * impossible à deviner.
     */
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
