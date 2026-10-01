<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Service\GenerateurTokenService;
use App\Service\LimiteurAppelsService;
use App\Service\NotificationEmailService;
use App\Service\PolitiqueMotDePasseService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

class MotDePasseOublieController extends AbstractController
{
    // Demande reset mot de passe, réponse identique que l'email existe ou non (anti-énumération)
    #[Route('/api/mot-de-passe-oublie', name: 'api_mot_de_passe_oublie', methods: ['POST'])]
    public function demander(
        Request                                                                 $request,
        EntityManagerInterface                                                  $em,
        NotificationEmailService                                                $notificationEmailService,
        GenerateurTokenService                                                  $generateurTokenService,
        LimiteurAppelsService                                                   $limiteurAppelsService,
        #[Autowire(service: 'limiter.mot_de_passe_oublie')] RateLimiterFactory $motDePasseOublieLimiter
    ): JsonResponse
    {
        // anti-spam, limite les demandes par IP
        if ($limiteurAppelsService->depasse($motDePasseOublieLimiter, $request->getClientIp())) {
            return $this->json(['message' => 'Trop de tentatives. Réessayez plus tard.'], 429);
        }

        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;

        $messageGenerique = 'Si cette adresse est associée à un compte, un email de réinitialisation vient d\'être envoyé.';

        if (empty($email)) {
            return $this->json(['message' => 'L\'email est obligatoire.'], 400);
        }

        $utilisateur = $em->getRepository(Utilisateur::class)->findOneBy(['email' => $email]);

        // compte introuvable, on répond pareil sans envoyer d'email (volontaire)
        if (!$utilisateur) {
            return $this->json(['message' => $messageGenerique]);
        }

        $token = $generateurTokenService->generer();
        $utilisateur->setTokenReinitialisation($token);
        $utilisateur->setTokenReinitialisationExpireLe((new \DateTime())->modify('+1 hour'));
        $em->flush();

        $lienReinitialisation = 'http://localhost:4200/reinitialiser-mot-de-passe/' . $token;

        $notificationEmailService->envoyer(
            $utilisateur->getEmail(),
            'Réinitialisation de votre mot de passe — Brouilla&Mat',
            "Bonjour,\n\n"
            . "Une demande de réinitialisation de mot de passe a été faite pour ce compte.\n\n"
            . "Pour choisir un nouveau mot de passe, cliquez sur ce lien (valable 1 heure) :\n$lienReinitialisation\n\n"
            . "Si vous n'êtes pas à l'origine de cette demande, ignorez simplement cet email : "
            . "votre mot de passe actuel reste inchangé.",
            '<p>Bonjour,</p>'
            . '<p>Une demande de réinitialisation de mot de passe a été faite pour ce compte.</p>'
            . '<p><a href="' . $lienReinitialisation . '" '
            . 'style="display:inline-block;padding:12px 24px;background-color:#CE865A;'
            . 'color:#2D302C;text-decoration:none;border-radius:8px;font-weight:bold;">'
            . 'Choisir un nouveau mot de passe</a></p>'
            . '<p>Ce lien est valable 1 heure.</p>'
            . '<p>Si vous n\'êtes pas à l\'origine de cette demande, ignorez simplement cet email : '
            . 'votre mot de passe actuel reste inchangé.</p>'
        );

        return $this->json(['message' => $messageGenerique]);
    }

    // Applique le nouveau mot de passe, le jeton sert de preuve d'identité, même règles de complexité
    #[Route('/api/reinitialiser-mot-de-passe/{token}', name: 'api_reinitialiser_mot_de_passe', methods: ['POST'])]
    public function reinitialiser(
        string                      $token,
        Request                     $request,
        EntityManagerInterface      $em,
        UserPasswordHasherInterface $passwordHasher,
        PolitiqueMotDePasseService  $politiqueMotDePasseService
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
        $erreurs = $politiqueMotDePasseService->erreurs($nouveauMotDePasse);

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
