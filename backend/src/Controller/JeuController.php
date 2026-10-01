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

// Proposer/accepter/refuser une partie, voir sa partie en cours.
// Connecté = suffisant, pas de rôle particulier requis.
class JeuController extends AbstractController
{
    // Classement Elo du membre, créé à 800 pts si pas encore de ligne
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

    // Formule Elo classique, K=32 (au lieu de 16-24 en compet officielle,
    // pour que ça bouge plus vite vu qu'on est un club loisir)
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

        // score attendu = proba de victoire selon l'écart de elo
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

    // Point unique pour terminer une partie (mat, temps écoulé, déco).
    // Évite de dupliquer le calcul Elo trois fois.
    private function marquerTerminee(Partie $partie, string $resultat, EntityManagerInterface $em): void
    {
        $partie->setStatut('terminee');
        $partie->setResultat($resultat);

        if ($partie->isClassee()) {
            $this->mettreAJourElo($partie, $resultat, $em);
        }

        $em->flush();
    }

    // Abandon immédiat, pas de confirmation adversaire (contrairement au nul)
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

    // Propose nulle, en attente de réponse adversaire
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

    // Répond à une proposition de nulle, seul l'adversaire peut répondre
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

    // Partie active d'un utilisateur (en_attente/en_cours).
    // Règle: une seule partie active par membre.
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

    // seuil hors ligne (signal envoyé toutes les 5s, marge pour onglet en arrière-plan)
    private const SEUIL_EN_LIGNE_SECONDES = 60;

    private function estEnLigne(Utilisateur $utilisateur): bool
    {
        $derniere = $utilisateur->getDerniereActivite();
        if (!$derniere) {
            return false;
        }
        return (new \DateTime())->getTimestamp() - $derniere->getTimestamp() < self::SEUIL_EN_LIGNE_SECONDES;
    }

    // Signal de présence, envoyé en continu par le header.
    // Même principe que le signal pendant une partie, mais pour tout le site.
    #[Route('/api/jeu/signal-presence', name: 'api_jeu_signal_presence', methods: ['POST'])]
    public function signalPresence(EntityManagerInterface $em): JsonResponse
    {
        /** @var Utilisateur $moi */
        $moi = $this->getUser();
        $moi->setDerniereActivite(new \DateTime());
        $em->flush();

        return $this->json(['message' => 'Signal reçu.']);
    }

    // Liste des membres à défier (validés, sauf soi-même)
    #[Route('/api/jeu/membres', name: 'api_jeu_membres', methods: ['GET'])]
    public function membresDisponibles(EntityManagerInterface $em): JsonResponse
    {
        /** @var Utilisateur $moi */
        $moi = $this->getUser();

        $membres = $em->getRepository(Utilisateur::class)->findAdversairesDisponibles($moi->getId());

        $resultat = array_map(fn(Utilisateur $u) => [
            'id' => $u->getId(),
            'nom' => $u->getNom(),
            'prenom' => $u->getPrenom(),
            'en_ligne' => $this->estEnLigne($u),
        ], $membres);

        return $this->json($resultat);
    }

    // Classement Elo, tous les membres validés inclus
    // (800 pts par défaut si jamais joué, pas de ligne en base)
    #[Route('/api/jeu/classement', name: 'api_jeu_classement', methods: ['GET'])]
    public function classement(EntityManagerInterface $em): JsonResponse
    {
        $membres = $em->getRepository(Utilisateur::class)->findMembresValides();

        $resultat = array_map(function (Utilisateur $u) use ($em) {
            $classement = $em->getRepository(Classement::class)->findOneBy(['utilisateur_id' => $u]);

            return [
                'nom' => $u->getNom(),
                'prenom' => $u->getPrenom(),
                'elo' => $classement?->getValeurElo() ?? 800,
                'en_ligne' => $this->estEnLigne($u),
            ];
        }, $membres);

        usort($resultat, fn(array $a, array $b) => $b['elo'] <=> $a['elo']);

        return $this->json($resultat);
    }

    // Partie active de l'utilisateur connecté, ou null
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
            // pour le frontend: est-ce moi qui ai proposé (j'attends) ou dois-je répondre
            'je_suis_blanc' => $blanc?->getId() === $moi->getId(),
            // null tant que pas en_cours (pas de plateau avant acceptation)
            'fen' => $partie->getFen(),
            'coups' => $partie->getCoups(),
            'trait' => $partie->getTrait(),
            'resultat' => $partie->getResultat(),
            'temps_restant_blanc' => $partie->getTempsRestantBlanc(),
            'temps_restant_noir' => $partie->getTempsRestantNoir(),
            // format ISO, facile à parser en Angular avec new Date()
            'dernier_coup_le' => $partie->getDernierCoupLe()?->format('c'),
            'dernier_signal_blanc' => $partie->getDernierSignalBlanc()?->format('c'),
            'dernier_signal_noir' => $partie->getDernierSignalNoir()?->format('c'),
            'nul_propose_par' => $partie->getNulProposePar(),
        ];
    }

    // Propose une partie à un membre
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

    // Accepte invitation, démarre la partie (10 min chacun)
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

        // position de départ, format FEN
        $partie->setFen('rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1');
        $partie->setCoups([]);
        $partie->setTrait('blanc');
        $partie->setTempsRestantBlanc(600);
        $partie->setTempsRestantNoir(600);
        // +5s de marge, le temps que le plateau se charge avant le décompte
        $partie->setDernierCoupLe((new \DateTime())->modify('+5 seconds'));
        $partie->setDernierSignalBlanc(new \DateTime());
        $partie->setDernierSignalNoir(new \DateTime());
        $partie->setStatut('en_cours');

        $em->flush();

        return $this->json(['message' => 'Partie commencée.']);
    }

    // 4 cas selon qui appelle et le statut de la partie :
    // 1. proposeur annule son invitation en_attente -> supprimée direct
    // 2. adversaire refuse -> passe en "refusee" (pas supprimée tout de suite)
    // 3. proposeur ferme le message de refus -> supprimée
    // 4. nettoyage d'une partie terminee une fois vue
    #[Route('/api/jeu/{id<\d+>}', name: 'api_jeu_annuler_invitation', methods: ['DELETE'])]
    public function annulerInvitation(int $id, EntityManagerInterface $em): JsonResponse
    {
        /** @var Utilisateur $moi */
        $moi = $this->getUser();

        $partie = $em->getRepository(Partie::class)->find($id);
        if (!$partie) {
            // déjà supprimée, probablement par l'adversaire en premier. Pas une erreur.
            return $this->json(['message' => 'Déjà supprimée.']);
        }

        $estProposeur = $partie->getJoueurBlancId()?->getId() === $moi->getId();
        $estImplique = $estProposeur || $partie->getJoueurNoirId()?->getId() === $moi->getId();
        if (!$estImplique) {
            return $this->json(['message' => 'Cette partie ne vous concerne pas.'], 403);
        }

        if ($partie->getStatut() === 'en_attente') {
            if ($estProposeur) {
                // cas 1 : j'annule ma propre invitation
                $em->remove($partie);
                $em->flush();
                return $this->json(['message' => 'Invitation annulée.']);
            }

            // cas 2 : je refuse une invitation reçue
            $partie->setStatut('refusee');
            $em->flush();
            return $this->json(['message' => 'Invitation refusée.']);
        }

        if ($partie->getStatut() === 'refusee') {
            // cas 3 : seul le proposeur peut fermer ce message
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

        // cas 4 : nettoyage d'une partie terminée
        $em->remove($partie);
        $em->flush();

        return $this->json(['message' => 'Supprimée.']);
    }

    // Joue un coup. Règles déjà validées côté Angular (chess.js),
    // ici on vérifie juste que c'est le bon tour
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

        // temps réel écoulé depuis le dernier coup, déduit du chrono de celui qui joue
        $maintenant = new \DateTime();
        $dernierCoup = $partie->getDernierCoupLe() ?? $maintenant;
        $secondesEcoulees = $maintenant->getTimestamp() - $dernierCoup->getTimestamp();

        $tempsAvant = $estBlanc ? $partie->getTempsRestantBlanc() : $partie->getTempsRestantNoir();
        $nouveauTemps = ($tempsAvant ?? 600) - $secondesEcoulees;

        if ($nouveauTemps <= 0) {
            // temps déjà écoulé, coup ignoré, partie perdue au chrono
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

        // jouer un coup = preuve de présence, met à jour le signal aussi
        if ($estBlanc) {
            $partie->setDernierSignalBlanc($maintenant);
        } else {
            $partie->setDernierSignalNoir($maintenant);
        }

        $em->flush();

        return $this->json($this->formaterPartie($partie, $moi));
    }

    // signal de présence, dit "je suis là". Met à jour le signal de sa propre couleur seulement
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

    // signale adversaire déco (pas de signal depuis 20s)
    // revérifié côté serveur avant de faire gagner
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

        // signal de l'adversaire, pas le sien
        $dernierSignalAdversaire = $estBlanc ? $partie->getDernierSignalNoir() : $partie->getDernierSignalBlanc();
        $reference = $dernierSignalAdversaire ?? $partie->getDernierCoupLe() ?? new \DateTime();
        $secondesDepuisSignal = (new \DateTime())->getTimestamp() - $reference->getTimestamp();

        if ($secondesDepuisSignal < 20) {
            // fausse alerte, pas vraiment déco
            return $this->json($this->formaterPartie($partie, $moi));
        }

        $gagnant = $estBlanc ? 'blanc' : 'noir';
        $this->marquerTerminee($partie, $gagnant, $em);

        return $this->json($this->formaterPartie($partie, $moi));
    }

    // signale temps écoulé. N'importe quel joueur peut l'appeler
    // (utile si l'autre a fermé l'onglet)
    // revérifié côté serveur, jamais confiance au navigateur
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
            // déjà terminée (mat ou autre signal juste avant), pas une erreur
            return $this->json($this->formaterPartie($partie, $moi));
        }

        $couleurAuTrait = $partie->getTrait();
        $tempsStocke = $couleurAuTrait === 'blanc' ? $partie->getTempsRestantBlanc() : $partie->getTempsRestantNoir();
        $dernierCoup = $partie->getDernierCoupLe() ?? new \DateTime();
        $secondesEcoulees = (new \DateTime())->getTimestamp() - $dernierCoup->getTimestamp();
        $tempsReel = ($tempsStocke ?? 600) - $secondesEcoulees;

        if ($tempsReel > 0) {
            // fausse alerte, horloge du navigateur en avance
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

    // Marque une partie terminée (mat/pat/nul détecté côté Angular, pas revérifié ici)
    // Pas supprimée tout de suite, le 2e joueur doit voir le résultat avant
    // (supprimée seulement quand les 2 ont fermé le message)
    // appelée 2x sans problème: si déjà terminee, renvoie juste l'état actuel
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
