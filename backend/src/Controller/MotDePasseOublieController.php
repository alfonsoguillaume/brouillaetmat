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

class MotDePasseOublieController extends AbstractController
{
    /**
     * Demande de réinitialisation. Réponse volontairement IDENTIQUE que
     * l'email existe ou non dans la base — sinon, quelqu'un de malveillant
     * pourrait s'en servir pour deviner quels emails sont inscrits sur le
     * site (une info qu'on ne veut jamais révéler par ce biais).
     */
    #[Route('/api/mot-de-passe-oublie', name: 'api_mot_de_passe_oublie', methods: ['POST'])]
    public function demander(Request $request, EntityManagerInterface $em, MailerInterface $mailer): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;

        $messageGenerique = 'Si cette adresse est associée à un compte, un email de réinitialisation vient d\'être envoyé.';

        if (empty($email)) {
            return $this->json(['message' => 'L\'email est obligatoire.'], 400);
        }

        $utilisateur = $em->getRepository(Utilisateur::class)->findOneBy(['email' => $email]);

        // Compte introuvable : on répond quand même comme si tout allait
        // bien, sans envoyer d'email — c'est le comportement volontaire.
        if (!$utilisateur) {
            return $this->json(['message' => $messageGenerique]);
        }

        $token = bin2hex(random_bytes(32));
        $utilisateur->setTokenReinitialisation($token);
        $utilisateur->setTokenReinitialisationExpireLe((new \DateTime())->modify('+1 hour'));
        $em->flush();

        $lienReinitialisation = 'http://localhost:4200/reinitialiser-mot-de-passe/' . $token;

        $mailer->send((new Email())
            ->from('brouillaetmat@gmail.com')
            ->to($utilisateur->getEmail())
            ->subject('Réinitialisation de votre mot de passe — Brouilla&Mat')
            ->text(
                "Bonjour,\n\n"
                . "Une demande de réinitialisation de mot de passe a été faite pour ce compte.\n\n"
                . "Pour choisir un nouveau mot de passe, cliquez sur ce lien (valable 1 heure) :\n$lienReinitialisation\n\n"
                . "Si vous n'êtes pas à l'origine de cette demande, ignorez simplement cet email : "
                . "votre mot de passe actuel reste inchangé."
            )
            ->html(
                '<p>Bonjour,</p>'
                . '<p>Une demande de réinitialisation de mot de passe a été faite pour ce compte.</p>'
                . '<p><a href="' . $lienReinitialisation . '" '
                . 'style="display:inline-block;padding:12px 24px;background-color:#CE865A;'
                . 'color:#2D302C;text-decoration:none;border-radius:8px;font-weight:bold;">'
                . 'Choisir un nouveau mot de passe</a></p>'
                . '<p>Ce lien est valable 1 heure.</p>'
                . '<p>Si vous n\'êtes pas à l\'origine de cette demande, ignorez simplement cet email : '
                . 'votre mot de passe actuel reste inchangé.</p>'
            ));

        return $this->json(['message' => $messageGenerique]);
    }

    /**
     * Applique le nouveau mot de passe — le jeton (reçu uniquement par
     * email) sert de preuve d'identité, la même règle de force de mot de
     * passe que sur le changement classique s'applique.
     */
    #[Route('/api/reinitialiser-mot-de-passe/{token}', name: 'api_reinitialiser_mot_de_passe', methods: ['POST'])]
    public function reinitialiser(
        string                      $token,
        Request                     $request,
        EntityManagerInterface      $em,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse
    {
        $utilisateur = $em->getRepository(Utilisateur::class)->findOneBy(['token_reinitialisation' => $token]);

        if (!$utilisateur) {
            return $this->json(['message' => 'Lien invalide ou déjà utilisé.'], 404);
        }

        if ($utilisateur->getTokenReinitialisationExpireLe() < new \DateTime()) {
            return $this->json(['message' => 'Ce lien a expiré. Merci de refaire une demande.'], 410);
        }

        $data = json_decode($request->getContent(), true);
        $nouveauMotDePasse = $data['nouveau_mot_de_passe'] ?? '';
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

        $utilisateur->setMotDePasse($passwordHasher->hashPassword($utilisateur, $nouveauMotDePasse));
        $utilisateur->setTokenReinitialisation(null);
        $utilisateur->setTokenReinitialisationExpireLe(null);
        $em->flush();

        return $this->json(['message' => 'Votre mot de passe a bien été réinitialisé. Vous pouvez maintenant vous connecter.']);
    }
}
