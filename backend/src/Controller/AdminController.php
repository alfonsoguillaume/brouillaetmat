<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class AdminController extends AbstractController
{
    /**
     * Liste tous les comptes en attente de validation.
     * Route déjà protégée globalement (voir security.yaml : ^/api/admin => ROLE_ADMIN).
     */
    #[Route('/api/admin/inscriptions', name: 'api_admin_inscriptions_liste', methods: ['GET'])]
    public function listeInscriptionsEnAttente(EntityManagerInterface $em): JsonResponse
    {
        $utilisateurs = $em->getRepository(Utilisateur::class)->findBy([
            'statut_inscription' => 'en_attente',
        ]);

        $resultat = array_map(function (Utilisateur $utilisateur) {
            return [
                'id' => $utilisateur->getId(),
                'nom' => $utilisateur->getNom(),
                'prenom' => $utilisateur->getPrenom(),
                'pseudo' => $utilisateur->getPseudo(),
                'email' => $utilisateur->getEmail(),
                'date_naissance' => $utilisateur->getDateNaissance()->format('Y-m-d'),
                'telephone' => $utilisateur->getTelephone(),
                'email_tuteur' => $utilisateur->getEmailTuteur(),
            ];
        }, $utilisateurs);

        return $this->json($resultat);
    }

    /**
     * Valide un compte : passe son statut à "valide".
     */
    #[Route('/api/admin/inscriptions/{id<\d+>}/valider', name: 'api_admin_inscription_valider', methods: ['PATCH'])]
    public function validerInscription(int $id, EntityManagerInterface $em): JsonResponse
    {
        $utilisateur = $em->getRepository(Utilisateur::class)->find($id);

        if (!$utilisateur) {
            return $this->json(['message' => 'Utilisateur introuvable.'], 404);
        }

        $utilisateur->setStatutInscription('valide');
        $em->flush();

        return $this->json(['message' => 'Compte validé.']);
    }

    /**
     * Refuse un compte : supprime définitivement la demande d'inscription.
     */
    #[Route('/api/admin/inscriptions/{id<\d+>}/refuser', name: 'api_admin_inscription_refuser', methods: ['DELETE'])]
    public function refuserInscription(int $id, EntityManagerInterface $em): JsonResponse
    {
        $utilisateur = $em->getRepository(Utilisateur::class)->find($id);

        if (!$utilisateur) {
            return $this->json(['message' => 'Utilisateur introuvable.'], 404);
        }

        $em->remove($utilisateur);
        $em->flush();

        return $this->json(['message' => 'Demande refusée et supprimée.']);
    }
}
