<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
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

    /**
     * Liste tous les membres au statut "valide" ou "bloque" (les inscriptions
     * "en_attente" sont gérées par la liste dédiée ci-dessus).
     */
    #[Route('/api/admin/membres', name: 'api_admin_membres_liste', methods: ['GET'])]
    public function listeMembres(EntityManagerInterface $em): JsonResponse
    {
        $utilisateurs = $em->getRepository(Utilisateur::class)->createQueryBuilder('u')
            ->where('u.statut_inscription != :enAttente')
            ->setParameter('enAttente', 'en_attente')
            ->getQuery()
            ->getResult();

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
     * Récupère un membre par son id, en refusant toute action sur un compte
     * admin (bonne pratique : un admin ne peut pas être modifié via cette
     * interface, même par un autre admin).
     *
     * @return Utilisateur|JsonResponse Renvoie directement une réponse d'erreur
     *                                  si le membre n'existe pas ou est admin.
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

    /**
     * Modifie les informations d'un membre (mêmes champs et règles que
     * /api/profil, mais ciblé sur un autre utilisateur que soi-même).
     */
    #[Route('/api/admin/membres/{id<\d+>}', name: 'api_admin_membre_modifier', methods: ['PATCH'])]
    public function modifierMembre(int $id, Request $request, EntityManagerInterface $em): JsonResponse
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

        if ($data['email'] !== $utilisateur->getEmail()) {
            $emailExistant = $em->getRepository(Utilisateur::class)->findOneBy(['email' => $data['email']]);
            if ($emailExistant) {
                return $this->json(['message' => 'Cet email est déjà utilisé.'], 409);
            }
        }

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
        $utilisateur->setEmail($data['email']);
        $utilisateur->setTelephone($data['telephone'] ?? null);
        $utilisateur->setEmailTuteur($data['email_tuteur'] ?? null);
        $utilisateur->setCommentaire($data['commentaire'] ?? null);

        $em->flush();

        return $this->json(['message' => 'Membre mis à jour.']);
    }

    /**
     * Change le rôle d'un membre (membre / gestionnaire / admin).
     */
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

    /**
     * Bloque un membre : il ne pourra plus se connecter (voir UtilisateurChecker).
     */
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

    /**
     * Débloque un membre précédemment bloqué.
     */
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

    /**
     * Supprime définitivement un membre. Refuse si ce membre a des données
     * liées en base (articles écrits, emprunts, parties...), pour ne pas
     * casser l'intégrité des autres tables — dans ce cas, le bloquer plutôt
     * que le supprimer est la bonne alternative.
     */
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
