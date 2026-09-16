<?php

namespace App\Security;

use App\Entity\Utilisateur;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UtilisateurChecker implements UserCheckerInterface
{
    /**
     * Appelée par Symfony AVANT de vérifier le mot de passe.
     * On en profite pour bloquer les comptes pas encore validés par un admin.
     */
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof Utilisateur) {
            return;
        }

        if ($user->getStatutInscription() === 'bloque') {
            throw new CustomUserMessageAccountStatusException(
                'Votre compte a été bloqué. Contactez un administrateur pour plus d\'informations.'
            );
        }

        if ($user->getStatutInscription() !== 'valide') {
            throw new CustomUserMessageAccountStatusException(
                'Votre inscription est en attente de validation par un administrateur.'
            );
        }
    }

    /**
     * Appelée par Symfony APRÈS une authentification réussie.
     * Rien à vérifier ici pour l'instant, mais la méthode est obligatoire
     * (imposée par l'interface UserCheckerInterface).
     */
    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
    }
}
