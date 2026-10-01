<?php

namespace App\Security;

use App\Entity\Utilisateur;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UtilisateurChecker implements UserCheckerInterface
{
    // Appelée avant de vérifier le mot de passe, bloque les comptes pas encore validés
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

    // Appelée après authentification réussie, rien à faire ici mais obligatoire (interface)
    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
    }
}
