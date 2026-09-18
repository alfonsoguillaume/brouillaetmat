<?php

namespace App\Controller;

use App\Entity\Partie;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Étape 1 : proposer une partie, l'accepter/refuser, consulter sa partie
 * active. Toutes les routes nécessitent d'être connecté (ROLE_USER, règle
 * générale de security.yaml) — pas de restriction de rôle particulière,
 * n'importe quel membre peut jouer.
 */
class JeuController extends AbstractController
{
    /**
     * Cherche une partie active (en_attente ou en_cours) impliquant cet
     * utilisateur, peu importe sa couleur. Centralise la règle "un membre
     * ne peut avoir qu'une seule partie active à la fois".
     */
    private function partieActivePour(Utilisateur $utilisateur, EntityManagerInterface $em): ?Partie
    {
        return $em->getRepository(Partie::class)->createQueryBuilder('p')
            ->where('(p.joueur_blanc_id = :u OR p.joueur_noir_id = :u)')
            ->andWhere('p.statut IN (:statuts)')
            ->setParameter('u', $utilisateur)
            ->setParameter('statuts', ['en_attente', 'en_cours'])
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Liste des membres qu'on peut défier (tous les membres validés, sauf
     * soi-même).
     */
    #[Route('/api/jeu/membres', name: 'api_jeu_membres', methods: ['GET'])]
    public function membresDisponibles(EntityManagerInterface $em): JsonResponse
    {
        /** @var Utilisateur $moi */
        $moi = $this->getUser();

        $membres = $em->getRepository(Utilisateur::class)->createQueryBuilder('u')
            ->where('u.statut_inscription = :valide')
            ->andWhere('u.id != :moi')
            ->setParameter('valide', 'valide')
            ->setParameter('moi', $moi->getId())
            ->orderBy('u.nom', 'ASC')
            ->getQuery()
            ->getResult();

        $resultat = array_map(fn(Utilisateur $u) => [
            'id' => $u->getId(),
            'nom' => $u->getNom(),
            'prenom' => $u->getPrenom(),
        ], $membres);

        return $this->json($resultat);
    }

    /**
     * Renvoie la partie active de l'utilisateur connecté (en attente ou en
     * cours), ou null s'il n'en a aucune.
     */
    #[Route('/api/jeu/ma-partie', name: 'api_jeu_ma_partie', methods: ['GET'])]
    public function maPartie(EntityManagerInterface $em): JsonResponse
    {
        /** @var Utilisateur $moi */
        $moi = $this->getUser();

        $partie = $this->partieActivePour($moi, $em);
        if (!$partie) {
            return $this->json(null);
        }

        return $this->json($this->formaterPartie($partie, $moi));
    }

    private function formaterPartie(Partie $partie, Utilisateur $moi): array
    {
        $blanc = $partie->getJoueurBlancId();
        $noir = $partie->getJoueurNoirId();

        return [
            'id' => $partie->getId(),
            'statut' => $partie->getStatut(),
            'classee' => $partie->isClassee(),
            'joueur_blanc' => ['id' => $blanc?->getId(), 'nom' => $blanc?->getNom(), 'prenom' => $blanc?->getPrenom()],
            'joueur_noir' => ['id' => $noir?->getId(), 'nom' => $noir?->getNom(), 'prenom' => $noir?->getPrenom()],
            // Pratique pour le frontend : est-ce moi qui ai proposé cette
            // partie (donc j'attends une réponse), ou est-ce à moi de
            // répondre à une invitation reçue ?
            'je_suis_blanc' => $blanc?->getId() === $moi->getId(),
        ];
    }

    /**
     * Propose une partie à un autre membre.
     */
    #[Route('/api/jeu/proposer', name: 'api_jeu_proposer', methods: ['POST'])]
    public function proposer(Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var Utilisateur $moi */
        $moi = $this->getUser();

        if ($this->partieActivePour($moi, $em)) {
            return $this->json(['message' => 'Vous avez déjà une partie en attente ou en cours.'], 409);
        }

        $data = json_decode($request->getContent(), true);
        if (empty($data['adversaire_id'])) {
            return $this->json(['message' => 'Choisissez un adversaire.'], 400);
        }

        $adversaire = $em->getRepository(Utilisateur::class)->find($data['adversaire_id']);
        if (!$adversaire) {
            return $this->json(['message' => 'Adversaire introuvable.'], 404);
        }

        if ($adversaire->getId() === $moi->getId()) {
            return $this->json(['message' => 'Vous ne pouvez pas vous défier vous-même.'], 400);
        }

        if ($this->partieActivePour($adversaire, $em)) {
            return $this->json(['message' => 'Ce membre a déjà une partie en attente ou en cours.'], 409);
        }

        $partie = new Partie();
        $partie->setJoueurBlancId($moi);
        $partie->setJoueurNoirId($adversaire);
        $partie->setDate(new \DateTime());
        $partie->setClassee(!empty($data['classee']));
        $partie->setStatut('en_attente');

        $em->persist($partie);
        $em->flush();

        return $this->json(['message' => 'Invitation envoyée.', 'id' => $partie->getId()], 201);
    }

    /**
     * Accepte une invitation reçue — démarre réellement la partie (position
     * de départ, chronomètres à 10 minutes chacun).
     */
    #[Route('/api/jeu/{id<\d+>}/accepter', name: 'api_jeu_accepter', methods: ['POST'])]
    public function accepter(int $id, EntityManagerInterface $em): JsonResponse
    {
        /** @var Utilisateur $moi */
        $moi = $this->getUser();

        $partie = $em->getRepository(Partie::class)->find($id);
        if (!$partie) {
            return $this->json(['message' => 'Invitation introuvable.'], 404);
        }

        if ($partie->getJoueurNoirId()?->getId() !== $moi->getId()) {
            return $this->json(['message' => 'Seul le joueur défié peut accepter cette invitation.'], 403);
        }

        if ($partie->getStatut() !== 'en_attente') {
            return $this->json(['message' => 'Cette invitation n\'est plus en attente.'], 409);
        }

        // Position de départ standard, au format FEN.
        $partie->setFen('rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1');
        $partie->setCoups([]);
        $partie->setTrait('blanc');
        $partie->setTempsRestantBlanc(600);
        $partie->setTempsRestantNoir(600);
        $partie->setDernierCoupLe(new \DateTime());
        $partie->setDernierSignalBlanc(new \DateTime());
        $partie->setDernierSignalNoir(new \DateTime());
        $partie->setStatut('en_cours');

        $em->flush();

        return $this->json(['message' => 'Partie commencée.']);
    }

    /**
     * Refuse une invitation reçue, ou annule une invitation qu'on a
     * soi-même envoyée — dans les deux cas, la ligne est simplement
     * supprimée (rien à archiver pour une invitation jamais jouée).
     */
    #[Route('/api/jeu/{id<\d+>}', name: 'api_jeu_annuler_invitation', methods: ['DELETE'])]
    public function annulerInvitation(int $id, EntityManagerInterface $em): JsonResponse
    {
        /** @var Utilisateur $moi */
        $moi = $this->getUser();

        $partie = $em->getRepository(Partie::class)->find($id);
        if (!$partie) {
            return $this->json(['message' => 'Invitation introuvable.'], 404);
        }

        $estImplique = $partie->getJoueurBlancId()?->getId() === $moi->getId()
            || $partie->getJoueurNoirId()?->getId() === $moi->getId();
        if (!$estImplique) {
            return $this->json(['message' => 'Cette invitation ne vous concerne pas.'], 403);
        }

        if ($partie->getStatut() !== 'en_attente') {
            return $this->json(['message' => 'Impossible d\'annuler une partie déjà commencée.'], 409);
        }

        $em->remove($partie);
        $em->flush();

        return $this->json(['message' => 'Invitation annulée.']);
    }
}
