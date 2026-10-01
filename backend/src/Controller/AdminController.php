<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Service\EmailUniciteService;
use App\Service\PseudoUniciteService;
use App\Service\TelephoneService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class AdminController extends AbstractController
{
    // Liste des comptes en attente de validation. Route déjà protégée (security.yaml)
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

    // Valide un compte
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

    // Refuse un compte, supprime la demande définitivement
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

    // Membres valides/bloqués (en_attente géré par la liste au-dessus)
    #[Route('/api/admin/membres', name: 'api_admin_membres_liste', methods: ['GET'])]
    public function listeMembres(EntityManagerInterface $em): JsonResponse
    {
        $utilisateurs = $em->getRepository(Utilisateur::class)->findMembresGeres();

        $resultat = array_map(function (Utilisateur $utilisateur) {
            return [
                'id' => $utilisateur->getId(),
                'nom' => $utilisateur->getNom(),
                'prenom' => $utilisateur->getPrenom(),
                'pseudo' => $utilisateur->getPseudo(),
                'email' => $utilisateur->getEmail(),
                'telephone' => $utilisateur->getTelephone(),
                'email_tuteur' => $utilisateur->getEmailTuteur(),
                'role' => $utilisateur->getRole(),
                'statut_inscription' => $utilisateur->getStatutInscription(),
                'commentaire' => $utilisateur->getCommentaire(),
            ];
        }, $utilisateurs);

        return $this->json($resultat);
    }

    /**
     * Récupère un membre modifiable, refuse les comptes admin (même pour un autre admin)
     *
     * @return Utilisateur|JsonResponse erreur directe si introuvable ou admin
     */
    private function recupererMembreModifiable(int $id, EntityManagerInterface $em): Utilisateur|JsonResponse
    {
        $utilisateur = $em->getRepository(Utilisateur::class)->find($id);

        if (!$utilisateur) {
            return $this->json(['message' => 'Utilisateur introuvable.'], 404);
        }

        if ($utilisateur->getRole() === 'admin') {
            return $this->json(['message' => 'Impossible de modifier un compte administrateur.'], 403);
        }

        return $utilisateur;
    }

    // Modifie un membre (mêmes champs/règles que /api/profil, mais pour un autre utilisateur)
    #[Route('/api/admin/membres/{id<\d+>}', name: 'api_admin_membre_modifier', methods: ['PATCH'])]
    public function modifierMembre(int $id, Request $request, EntityManagerInterface $em, PseudoUniciteService $pseudoUniciteService, EmailUniciteService $emailUniciteService, TelephoneService $telephoneService): JsonResponse
    {
        $utilisateur = $this->recupererMembreModifiable($id, $em);
        if ($utilisateur instanceof JsonResponse) {
            return $utilisateur;
        }

        $data = json_decode($request->getContent(), true);

        $champsRequis = ['nom', 'prenom', 'pseudo', 'email'];
        foreach ($champsRequis as $champ) {
            if (empty($data[$champ])) {
                return $this->json(['message' => "Le champ \"$champ\" est obligatoire."], 400);
            }
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->json(['message' => 'Le format de l\'email est invalide.'], 400);
        }

        if ($data['email'] !== $utilisateur->getEmail() && !$emailUniciteService->estDisponible($data['email'])) {
            return $this->json(['message' => 'Cet email est déjà utilisé.'], 409);
        }

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
        $utilisateur->setEmail($data['email']);
        $utilisateur->setTelephone($data['telephone'] ?? null);
        $utilisateur->setEmailTuteur($data['email_tuteur'] ?? null);
        $utilisateur->setCommentaire($data['commentaire'] ?? null);

        $em->flush();

        return $this->json(['message' => 'Membre mis à jour.']);
    }

    // Change le rôle (membre/gestionnaire/admin)
    #[Route('/api/admin/membres/{id<\d+>}/role', name: 'api_admin_membre_role', methods: ['PATCH'])]
    public function changerRole(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $utilisateur = $this->recupererMembreModifiable($id, $em);
        if ($utilisateur instanceof JsonResponse) {
            return $utilisateur;
        }

        $data = json_decode($request->getContent(), true);
        $rolesAutorises = ['membre', 'gestionnaire', 'admin'];

        if (empty($data['role']) || !in_array($data['role'], $rolesAutorises, true)) {
            return $this->json(['message' => 'Rôle invalide.'], 400);
        }

        $utilisateur->setRole($data['role']);
        $em->flush();

        return $this->json(['message' => 'Rôle mis à jour.']);
    }

    // Bloque un membre, ne pourra plus se connecter (UtilisateurChecker)
    #[Route('/api/admin/membres/{id<\d+>}/bloquer', name: 'api_admin_membre_bloquer', methods: ['PATCH'])]
    public function bloquerMembre(int $id, EntityManagerInterface $em): JsonResponse
    {
        $utilisateur = $this->recupererMembreModifiable($id, $em);
        if ($utilisateur instanceof JsonResponse) {
            return $utilisateur;
        }

        $utilisateur->setStatutInscription('bloque');
        $em->flush();

        return $this->json(['message' => 'Membre bloqué.']);
    }

    // Débloque un membre
    #[Route('/api/admin/membres/{id<\d+>}/debloquer', name: 'api_admin_membre_debloquer', methods: ['PATCH'])]
    public function debloquerMembre(int $id, EntityManagerInterface $em): JsonResponse
    {
        $utilisateur = $this->recupererMembreModifiable($id, $em);
        if ($utilisateur instanceof JsonResponse) {
            return $utilisateur;
        }

        $utilisateur->setStatutInscription('valide');
        $em->flush();

        return $this->json(['message' => 'Membre débloqué.']);
    }

    // Supprime un membre. Refuse si des données sont liées (articles, emprunts, parties...)
    // bloquer à la place dans ce cas
    #[Route('/api/admin/membres/{id<\d+>}', name: 'api_admin_membre_supprimer', methods: ['DELETE'])]
    public function supprimerMembre(int $id, EntityManagerInterface $em): JsonResponse
    {
        $utilisateur = $this->recupererMembreModifiable($id, $em);
        if ($utilisateur instanceof JsonResponse) {
            return $utilisateur;
        }

        try {
            $em->remove($utilisateur);
            $em->flush();
        } catch (\Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException $e) {
            return $this->json([
                'message' => 'Impossible de supprimer ce membre : il est lié à des données existantes (articles, prêts, parties...). Bloquez son compte à la place.',
            ], 409);
        }

        return $this->json(['message' => 'Membre supprimé.']);
    }
}
