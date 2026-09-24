<?php

namespace App\Controller;

use App\Entity\Classement;
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
     * Renvoie le classement Elo d'un membre, en le créant à 800 points
     * s'il n'en a pas encore (première partie classée de sa "carrière").
     */
    private function obtenirOuCreerClassement(Utilisateur $utilisateur, EntityManagerInterface $em): Classement
    {
        $classement = $em->getRepository(Classement::class)->findOneBy(['utilisateur_id' => $utilisateur]);

        if (!$classement) {
            $classement = new Classement();
            $classement->setUtilisateurId($utilisateur);
            $classement->setValeurElo(800);
            $classement->setDateMaj(new \DateTime());
            $em->persist($classement);
        }

        return $classement;
    }

    /**
     * Formule Elo classique. K = 32 (facteur de volatilité — plus élevé
     * que la valeur "compétition officielle" habituelle de 16-24, choisi
     * ici pour que les scores bougent de façon visible sur un club de
     * loisir, où les joueurs ne disputent pas des centaines de parties).
     */
    private function mettreAJourElo(Partie $partie, string $resultat, EntityManagerInterface $em): void
    {
        $blanc = $partie->getJoueurBlancId();
        $noir = $partie->getJoueurNoirId();
        if (!$blanc || !$noir) {
            return;
        }

        $classementBlanc = $this->obtenirOuCreerClassement($blanc, $em);
        $classementNoir = $this->obtenirOuCreerClassement($noir, $em);

        $eloBlanc = $classementBlanc->getValeurElo();
        $eloNoir = $classementNoir->getValeurElo();

        $scoreBlanc = match ($resultat) {
            'blanc' => 1.0,
            'noir' => 0.0,
            default => 0.5,
        };
        $scoreNoir = 1.0 - $scoreBlanc;

        // Score "attendu" : probabilité théorique de victoire selon
        // l'écart de classement actuel entre les deux joueurs.
        $attenduBlanc = 1 / (1 + (10 ** (($eloNoir - $eloBlanc) / 400)));
        $attenduNoir = 1 - $attenduBlanc;

        $k = 32;
        $nouvelEloBlanc = (int)round($eloBlanc + $k * ($scoreBlanc - $attenduBlanc));
        $nouvelEloNoir = (int)round($eloNoir + $k * ($scoreNoir - $attenduNoir));

        $classementBlanc->setValeurElo($nouvelEloBlanc);
        $classementBlanc->setDateMaj(new \DateTime());
        $classementNoir->setValeurElo($nouvelEloNoir);
        $classementNoir->setDateMaj(new \DateTime());
    }

    /**
     * Point d'entrée UNIQUE pour terminer une partie, réutilisé par les 3
     * routes qui peuvent y mener (mat/pat/nul, temps écoulé, déconnexion)
     * — évite de dupliquer la logique de mise à jour Elo trois fois.
     */
    private function marquerTerminee(Partie $partie, string $resultat, EntityManagerInterface $em): void
    {
        $partie->setStatut('terminee');
        $partie->setResultat($resultat);

        if ($partie->isClassee()) {
            $this->mettreAJourElo($partie, $resultat, $em);
        }

        $em->flush();
    }

    /**
     * Abandon immédiat — défaite pour celui qui clique, sans confirmation
     * de l'adversaire nécessaire (contrairement à la nulle).
     */
    #[Route('/api/jeu/{id<\d+>}/abandonner', name: 'api_jeu_abandonner', methods: ['POST'])]
    public function abandonner(int $id, EntityManagerInterface $em): JsonResponse
    {
        /** @var Utilisateur $moi */
        $moi = $this->getUser();

        $partie = $em->getRepository(Partie::class)->find($id);
        if (!$partie) {
            return $this->json(['message' => 'Partie introuvable.'], 404);
        }

        $estBlanc = $partie->getJoueurBlancId()?->getId() === $moi->getId();
        $estNoir = $partie->getJoueurNoirId()?->getId() === $moi->getId();
        if (!$estBlanc && !$estNoir) {
            return $this->json(['message' => 'Cette partie ne vous concerne pas.'], 403);
        }

        if ($partie->getStatut() !== 'en_cours') {
            return $this->json($this->formaterPartie($partie, $moi));
        }

        $this->marquerTerminee($partie, $estBlanc ? 'noir' : 'blanc', $em);

        return $this->json($this->formaterPartie($partie, $moi));
    }

    /**
     * Propose une nulle à l'adversaire — ne termine rien tout de suite,
     * juste une demande en attente de réponse.
     */
    #[Route('/api/jeu/{id<\d+>}/proposer-nul', name: 'api_jeu_proposer_nul', methods: ['POST'])]
    public function proposerNul(int $id, EntityManagerInterface $em): JsonResponse
    {
        /** @var Utilisateur $moi */
        $moi = $this->getUser();

        $partie = $em->getRepository(Partie::class)->find($id);
        if (!$partie) {
            return $this->json(['message' => 'Partie introuvable.'], 404);
        }

        $estBlanc = $partie->getJoueurBlancId()?->getId() === $moi->getId();
        $estNoir = $partie->getJoueurNoirId()?->getId() === $moi->getId();
        if (!$estBlanc && !$estNoir) {
            return $this->json(['message' => 'Cette partie ne vous concerne pas.'], 403);
        }

        if ($partie->getStatut() !== 'en_cours') {
            return $this->json(['message' => 'Cette partie n\'est pas en cours.'], 409);
        }

        if ($partie->getNulProposePar() !== null) {
            return $this->json(['message' => 'Une proposition de nulle est déjà en attente.'], 409);
        }

        $partie->setNulProposePar($estBlanc ? 'blanc' : 'noir');
        $em->flush();

        return $this->json($this->formaterPartie($partie, $moi));
    }

    /**
     * Répond à une proposition de nulle reçue — seul l'adversaire de celui
     * qui a proposé peut répondre (pas soi-même).
     */
    #[Route('/api/jeu/{id<\d+>}/repondre-nul', name: 'api_jeu_repondre_nul', methods: ['POST'])]
    public function repondreNul(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var Utilisateur $moi */
        $moi = $this->getUser();

        $partie = $em->getRepository(Partie::class)->find($id);
        if (!$partie) {
            return $this->json(['message' => 'Partie introuvable.'], 404);
        }

        $estBlanc = $partie->getJoueurBlancId()?->getId() === $moi->getId();
        $estNoir = $partie->getJoueurNoirId()?->getId() === $moi->getId();
        if (!$estBlanc && !$estNoir) {
            return $this->json(['message' => 'Cette partie ne vous concerne pas.'], 403);
        }

        $maCouleur = $estBlanc ? 'blanc' : 'noir';
        if ($partie->getNulProposePar() === null) {
            return $this->json(['message' => 'Aucune proposition de nulle en attente.'], 409);
        }
        if ($partie->getNulProposePar() === $maCouleur) {
            return $this->json(['message' => 'Vous ne pouvez pas répondre à votre propre proposition.'], 403);
        }

        $data = json_decode($request->getContent(), true);
        $accepter = $data['accepter'] ?? false;

        if ($accepter) {
            $partie->setNulProposePar(null);
            $this->marquerTerminee($partie, 'nul', $em);
        } else {
            $partie->setNulProposePar(null);
            $em->flush();
        }

        return $this->json($this->formaterPartie($partie, $moi));
    }

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
            ->setParameter('statuts', ['en_attente', 'en_cours', 'terminee', 'refusee'])
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
     * Classement Elo du club, du plus fort au plus faible. Contrairement à
     * une version précédente, inclut TOUS les membres validés — ceux qui
     * n'ont encore jamais joué de partie classée apparaissent à 800
     * points (la valeur de départ), sans qu'une ligne Classement existe
     * réellement pour eux en base (elle n'est créée qu'à leur première
     * partie classée terminée, voir obtenirOuCreerClassement).
     */
    #[Route('/api/jeu/classement', name: 'api_jeu_classement', methods: ['GET'])]
    public function classement(EntityManagerInterface $em): JsonResponse
    {
        $membres = $em->getRepository(Utilisateur::class)->findBy(['statut_inscription' => 'valide']);

        $resultat = array_map(function (Utilisateur $u) use ($em) {
            $classement = $em->getRepository(Classement::class)->findOneBy(['utilisateur_id' => $u]);

            return [
                'nom' => $u->getNom(),
                'prenom' => $u->getPrenom(),
                'elo' => $classement?->getValeurElo() ?? 800,
            ];
        }, $membres);

        usort($resultat, fn(array $a, array $b) => $b['elo'] <=> $a['elo']);

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
            // Ces champs restent "null" tant que la partie n'est pas
            // "en_cours" (une invitation en attente n'a pas encore de plateau).
            'fen' => $partie->getFen(),
            'coups' => $partie->getCoups(),
            'trait' => $partie->getTrait(),
            'resultat' => $partie->getResultat(),
            'temps_restant_blanc' => $partie->getTempsRestantBlanc(),
            'temps_restant_noir' => $partie->getTempsRestantNoir(),
            // Format ISO 8601 ("c") — facile à parser côté Angular avec
            // new Date(...).
            'dernier_coup_le' => $partie->getDernierCoupLe()?->format('c'),
            'dernier_signal_blanc' => $partie->getDernierSignalBlanc()?->format('c'),
            'dernier_signal_noir' => $partie->getDernierSignalNoir()?->format('c'),
            'nul_propose_par' => $partie->getNulProposePar(),
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
        // +5 secondes de battement : laisse le temps au plateau de se
        // charger côté Angular avant que le chrono ne commence vraiment
        // à décompter (tout le calcul du temps se base sur cette valeur,
        // donc ce seul changement suffit à tout décaler proprement).
        $partie->setDernierCoupLe((new \DateTime())->modify('+5 seconds'));
        $partie->setDernierSignalBlanc(new \DateTime());
        $partie->setDernierSignalNoir(new \DateTime());
        $partie->setStatut('en_cours');

        $em->flush();

        return $this->json(['message' => 'Partie commencée.']);
    }

    /**
     * Plusieurs usages pour cette même route, selon qui appelle et le
     * statut actuel de la partie :
     * 1. Le PROPOSEUR annule sa propre invitation encore "en_attente" →
     *    suppression immédiate (rien à archiver, jamais jouée, personne
     *    d'autre n'a besoin d'être informé puisque c'est lui-même qui agit).
     * 2. L'ADVERSAIRE refuse une invitation reçue "en_attente" → on ne
     *    supprime PAS tout de suite, on marque juste "refusee" — le temps
     *    que le proposeur la voie à son prochain rafraîchissement (même
     *    principe que "terminee" pour la fin d'une partie).
     * 3. Le PROPOSEUR ferme le message "refusée" une fois qu'il l'a vu →
     *    suppression définitive, réservée à lui seul (l'adversaire n'a
     *    plus rien à faire ici, il a déjà agi à l'étape 2).
     * 4. Fermer/nettoyer une partie "terminee" (une fois que le joueur a
     *    vu le résultat affiché) — la ligne n'est supprimée qu'à ce
     *    moment-là, jamais automatiquement à la détection du mat (voir
     *    terminer() ci-dessus pour l'explication complète).
     */
    #[Route('/api/jeu/{id<\d+>}', name: 'api_jeu_annuler_invitation', methods: ['DELETE'])]
    public function annulerInvitation(int $id, EntityManagerInterface $em): JsonResponse
    {
        /** @var Utilisateur $moi */
        $moi = $this->getUser();

        $partie = $em->getRepository(Partie::class)->find($id);
        if (!$partie) {
            // Déjà supprimée — probablement par l'adversaire, qui a fermé
            // le résultat de son côté en premier. Pas une erreur.
            return $this->json(['message' => 'Déjà supprimée.']);
        }

        $estProposeur = $partie->getJoueurBlancId()?->getId() === $moi->getId();
        $estImplique = $estProposeur || $partie->getJoueurNoirId()?->getId() === $moi->getId();
        if (!$estImplique) {
            return $this->json(['message' => 'Cette partie ne vous concerne pas.'], 403);
        }

        if ($partie->getStatut() === 'en_attente') {
            if ($estProposeur) {
                // Cas 1 : j'annule ma propre invitation.
                $em->remove($partie);
                $em->flush();
                return $this->json(['message' => 'Invitation annulée.']);
            }

            // Cas 2 : je refuse une invitation reçue.
            $partie->setStatut('refusee');
            $em->flush();
            return $this->json(['message' => 'Invitation refusée.']);
        }

        if ($partie->getStatut() === 'refusee') {
            // Cas 3 : seul le proposeur peut fermer ce message.
            if (!$estProposeur) {
                return $this->json(['message' => 'Cette action ne vous concerne pas.'], 403);
            }
            $em->remove($partie);
            $em->flush();
            return $this->json(['message' => 'Supprimée.']);
        }

        if ($partie->getStatut() !== 'terminee') {
            return $this->json(['message' => 'Impossible d\'annuler une partie en cours.'], 409);
        }

        // Cas 4 : nettoyage d'une partie terminée.
        $em->remove($partie);
        $em->flush();

        return $this->json(['message' => 'Supprimée.']);
    }

    /**
     * Joue un coup. La validité du coup lui-même (règles d'échecs) est déjà
     * vérifiée côté Angular via chess.js — ici, on vérifie seulement que
     * c'est bien le tour de la personne qui envoie la requête, puis on
     * enregistre le nouvel état.
     */
    #[Route('/api/jeu/{id<\d+>}/coup', name: 'api_jeu_jouer_coup', methods: ['POST'])]
    public function jouerCoup(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var Utilisateur $moi */
        $moi = $this->getUser();

        $partie = $em->getRepository(Partie::class)->find($id);
        if (!$partie) {
            return $this->json(['message' => 'Partie introuvable.'], 404);
        }

        if ($partie->getStatut() !== 'en_cours') {
            return $this->json(['message' => 'Cette partie n\'est pas en cours.'], 409);
        }

        $estBlanc = $partie->getJoueurBlancId()?->getId() === $moi->getId();
        $estNoir = $partie->getJoueurNoirId()?->getId() === $moi->getId();
        if (!$estBlanc && !$estNoir) {
            return $this->json(['message' => 'Cette partie ne vous concerne pas.'], 403);
        }

        $maCouleur = $estBlanc ? 'blanc' : 'noir';
        if ($partie->getTrait() !== $maCouleur) {
            return $this->json(['message' => 'Ce n\'est pas votre tour.'], 409);
        }

        $data = json_decode($request->getContent(), true);
        if (empty($data['fen']) || empty($data['coup'])) {
            return $this->json(['message' => 'Coup invalide.'], 400);
        }

        // Calcule le temps RÉELLEMENT écoulé depuis le dernier coup, et le
        // déduit du chrono de celui qui vient de jouer (pas de celui qui
        // attendait — son chrono à lui ne tournait pas).
        $maintenant = new \DateTime();
        $dernierCoup = $partie->getDernierCoupLe() ?? $maintenant;
        $secondesEcoulees = $maintenant->getTimestamp() - $dernierCoup->getTimestamp();

        $tempsAvant = $estBlanc ? $partie->getTempsRestantBlanc() : $partie->getTempsRestantNoir();
        $nouveauTemps = ($tempsAvant ?? 600) - $secondesEcoulees;

        if ($nouveauTemps <= 0) {
            // Le temps était déjà écoulé au moment où ce coup a été reçu —
            // trop tard, la partie est perdue au chrono, ce coup est
            // ignoré (pas appliqué).
            if ($estBlanc) {
                $partie->setTempsRestantBlanc(0);
            } else {
                $partie->setTempsRestantNoir(0);
            }
            $this->marquerTerminee($partie, $estBlanc ? 'noir' : 'blanc', $em);

            return $this->json($this->formaterPartie($partie, $moi));
        }

        if ($estBlanc) {
            $partie->setTempsRestantBlanc($nouveauTemps);
        } else {
            $partie->setTempsRestantNoir($nouveauTemps);
        }

        $coups = $partie->getCoups() ?? [];
        $coups[] = $data['coup'];

        $partie->setFen($data['fen']);
        $partie->setCoups($coups);
        $partie->setTrait($maCouleur === 'blanc' ? 'noir' : 'blanc');
        $partie->setDernierCoupLe($maintenant);

        // Jouer un coup est en soi la preuve la plus fiable qu'on est
        // toujours présent — met aussi à jour le "battement de cœur"
        // (utile dès que la détection de déconnexion sera activée).
        if ($estBlanc) {
            $partie->setDernierSignalBlanc($maintenant);
        } else {
            $partie->setDernierSignalNoir($maintenant);
        }

        $em->flush();

        return $this->json($this->formaterPartie($partie, $moi));
    }

    /**
     * "Battement de cœur" — appelée régulièrement par le navigateur de
     * chaque joueur pendant qu'une partie est en cours, pour dire "je suis
     * toujours là". Met à jour uniquement le signal de SA PROPRE couleur.
     */
    #[Route('/api/jeu/{id<\d+>}/signal', name: 'api_jeu_signal', methods: ['POST'])]
    public function signal(int $id, EntityManagerInterface $em): JsonResponse
    {
        /** @var Utilisateur $moi */
        $moi = $this->getUser();

        $partie = $em->getRepository(Partie::class)->find($id);
        if (!$partie || $partie->getStatut() !== 'en_cours') {
            return $this->json(['message' => 'Rien à signaler.']);
        }

        $estBlanc = $partie->getJoueurBlancId()?->getId() === $moi->getId();
        $estNoir = $partie->getJoueurNoirId()?->getId() === $moi->getId();
        if (!$estBlanc && !$estNoir) {
            return $this->json(['message' => 'Cette partie ne vous concerne pas.'], 403);
        }

        if ($estBlanc) {
            $partie->setDernierSignalBlanc(new \DateTime());
        } else {
            $partie->setDernierSignalNoir(new \DateTime());
        }
        $em->flush();

        return $this->json(['message' => 'Signal reçu.']);
    }

    /**
     * Signale que l'ADVERSAIRE semble déconnecté (aucun signal depuis plus
     * de 20 secondes). Peut être appelée par n'importe lequel des deux
     * joueurs, mais ne fait gagner QUE si c'est vraiment l'autre camp qui
     * est silencieux depuis trop longtemps — revérifié côté serveur.
     */
    #[Route('/api/jeu/{id<\d+>}/deconnexion', name: 'api_jeu_deconnexion', methods: ['POST'])]
    public function declarerDeconnexion(int $id, EntityManagerInterface $em): JsonResponse
    {
        /** @var Utilisateur $moi */
        $moi = $this->getUser();

        $partie = $em->getRepository(Partie::class)->find($id);
        if (!$partie) {
            return $this->json(['message' => 'Partie introuvable.'], 404);
        }

        $estBlanc = $partie->getJoueurBlancId()?->getId() === $moi->getId();
        $estNoir = $partie->getJoueurNoirId()?->getId() === $moi->getId();
        if (!$estBlanc && !$estNoir) {
            return $this->json(['message' => 'Cette partie ne vous concerne pas.'], 403);
        }

        if ($partie->getStatut() !== 'en_cours') {
            return $this->json($this->formaterPartie($partie, $moi));
        }

        // On vérifie le signal de L'ADVERSAIRE (pas le sien).
        $dernierSignalAdversaire = $estBlanc ? $partie->getDernierSignalNoir() : $partie->getDernierSignalBlanc();
        $reference = $dernierSignalAdversaire ?? $partie->getDernierCoupLe() ?? new \DateTime();
        $secondesDepuisSignal = (new \DateTime())->getTimestamp() - $reference->getTimestamp();

        if ($secondesDepuisSignal < 20) {
            // Pas (encore) réellement déconnecté — fausse alerte.
            return $this->json($this->formaterPartie($partie, $moi));
        }

        $gagnant = $estBlanc ? 'blanc' : 'noir';
        $this->marquerTerminee($partie, $gagnant, $em);

        return $this->json($this->formaterPartie($partie, $moi));
    }

    /**
     * Signale qu'un chrono est tombé à zéro. Peut être appelée par
     * N'IMPORTE LEQUEL des deux joueurs (pas seulement celui dont le
     * temps est écoulé) — utile si c'est justement CE joueur-là qui a
     * fermé son navigateur et ne peut plus rien signaler lui-même.
     *
     * Revérifié côté serveur (jamais fait confiance à l'horloge locale du
     * navigateur) : recalcule le temps réel à partir de ce qui est stocké
     * + le temps écoulé depuis le dernier coup.
     */
    #[Route('/api/jeu/{id<\d+>}/temps-ecoule', name: 'api_jeu_temps_ecoule', methods: ['POST'])]
    public function tempsEcoule(int $id, EntityManagerInterface $em): JsonResponse
    {
        /** @var Utilisateur $moi */
        $moi = $this->getUser();

        $partie = $em->getRepository(Partie::class)->find($id);
        if (!$partie) {
            return $this->json(['message' => 'Partie introuvable.'], 404);
        }

        $estImplique = $partie->getJoueurBlancId()?->getId() === $moi->getId()
            || $partie->getJoueurNoirId()?->getId() === $moi->getId();
        if (!$estImplique) {
            return $this->json(['message' => 'Cette partie ne vous concerne pas.'], 403);
        }

        if ($partie->getStatut() !== 'en_cours') {
            // Déjà terminée (par mat, ou par un autre signal de temps
            // écoulé arrivé juste avant) — pas une erreur, on renvoie
            // simplement l'état actuel.
            return $this->json($this->formaterPartie($partie, $moi));
        }

        $couleurAuTrait = $partie->getTrait();
        $tempsStocke = $couleurAuTrait === 'blanc' ? $partie->getTempsRestantBlanc() : $partie->getTempsRestantNoir();
        $dernierCoup = $partie->getDernierCoupLe() ?? new \DateTime();
        $secondesEcoulees = (new \DateTime())->getTimestamp() - $dernierCoup->getTimestamp();
        $tempsReel = ($tempsStocke ?? 600) - $secondesEcoulees;

        if ($tempsReel > 0) {
            // Fausse alerte (horloge locale du navigateur légèrement en
            // avance, par exemple) — le temps n'est pas réellement écoulé.
            return $this->json($this->formaterPartie($partie, $moi));
        }

        $gagnant = $couleurAuTrait === 'blanc' ? 'noir' : 'blanc';
        if ($couleurAuTrait === 'blanc') {
            $partie->setTempsRestantBlanc(0);
        } else {
            $partie->setTempsRestantNoir(0);
        }
        $this->marquerTerminee($partie, $gagnant, $em);

        return $this->json($this->formaterPartie($partie, $moi));
    }

    /**
     * Marque une partie comme terminée (mat, pat, nul) — détecté côté
     * Angular via chess.js, jamais revérifié ici (cohérent avec notre
     * choix de scope : validation des règles uniquement côté client).
     *
     * IMPORTANT : ne supprime PAS la ligne tout de suite — les deux
     * joueurs ne rafraîchissent pas au même instant (toutes les 3s au
     * mieux), donc une suppression immédiate risquerait de faire
     * disparaître la partie avant que l'adversaire n'ait eu la moindre
     * chance de voir le résultat. La suppression réelle n'a lieu que
     * lorsque CHAQUE joueur ferme le message de son côté (voir
     * fermerPartieTerminee ci-dessous, qui réutilise la route DELETE).
     *
     * Idempotent : si déjà "terminee", renvoie simplement l'état actuel
     * plutôt que d'échouer — les deux joueurs peuvent détecter la fin de
     * partie chacun de leur côté et appeler cette route en double.
     */
    #[Route('/api/jeu/{id<\d+>}/terminer', name: 'api_jeu_terminer', methods: ['POST'])]
    public function terminer(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var Utilisateur $moi */
        $moi = $this->getUser();

        $partie = $em->getRepository(Partie::class)->find($id);
        if (!$partie) {
            return $this->json(['message' => 'Partie introuvable.'], 404);
        }

        $estImplique = $partie->getJoueurBlancId()?->getId() === $moi->getId()
            || $partie->getJoueurNoirId()?->getId() === $moi->getId();
        if (!$estImplique) {
            return $this->json(['message' => 'Cette partie ne vous concerne pas.'], 403);
        }

        if ($partie->getStatut() === 'terminee') {
            return $this->json($this->formaterPartie($partie, $moi));
        }

        $data = json_decode($request->getContent(), true);
        $resultat = $data['resultat'] ?? null;
        if (!in_array($resultat, ['blanc', 'noir', 'nul'], true)) {
            return $this->json(['message' => 'Résultat invalide.'], 400);
        }

        $this->marquerTerminee($partie, $resultat, $em);

        return $this->json($this->formaterPartie($partie, $moi));
    }
}
