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
     * Appelée par Symfony AVANT la vérif du mot de passe.
     * Bloque les comptes pas validés par un admin.
     */
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof Utilisateur) {
            return;
        }

        if ($user->getStatutInscription() !== 'valide') {
            throw new CustomUserMessageAccountStatusException(
                'Votre inscription est en attente de validation par un administrateur.'
            );
        }
    }

    /**
     * Appelée par Symfony APRÈS une authentification réussie.
     * Rien à vérifier, mais la méthode est obligatoire
     */
    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {

    }
}
