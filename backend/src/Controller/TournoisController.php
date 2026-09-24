<?php

namespace App\Controller;

use App\Entity\MatchTournoi;
use App\Entity\Participation;
use App\Entity\Tournois;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Consultation (GET) ouverte à tout membre connecté (ROLE_USER, règle
 * générale de security.yaml). Écriture (POST/PATCH/DELETE) réservée aux
 * admins uniquement — pas même les gestionnaires, contrairement aux
 * articles/bibliothèque (voir security.yaml : ^/api/tournois).
 *
 * NOTE IMPORTANTE : la saisie des scores et le calcul du classement ne sont
 * pas encore implémentés — en attente de la méthode de notation à confirmer.
 * Cette version couvre : créer/modifier/supprimer un tournoi, inscrire ou
 * désinscrire des membres avant le lancement, et lancer le tournoi.
 */
class TournoisController extends AbstractController
{
    // ---------- Liste et détail ----------

    #[Route('/api/tournois', name: 'api_tournois_liste', methods: ['GET'])]
    public function liste(EntityManagerInterface $em): JsonResponse
    {
        $tournois = $em->getRepository(Tournois::class)->createQueryBuilder('t')
            ->where('t.statut NOT IN (:statutsArchives)')
            ->setParameter('statutsArchives', ['annule', 'termine'])
            ->orderBy('t.date', 'DESC')
            ->getQuery()
            ->getResult();

        $resultat = array_map(function (Tournois $t) use ($em) {
            $nombreParticipants = $em->getRepository(Participation::class)->count(['tournois_id' => $t]);

            return [
                'id' => $t->getId(),
                'nom' => $t->getNom(),
                'date' => $t->getDate()->format('Y-m-d'),
                'statut' => $t->getStatut(),
                'nombre_participants' => $nombreParticipants,
            ];
        }, $tournois);

        return $this->json($resultat);
    }

    /**
     * Archives : tournois annulés, avec leur motif et la date d'annulation.
     */
    #[Route('/api/tournois/annules', name: 'api_tournois_annules_liste', methods: ['GET'])]
    public function listeAnnules(EntityManagerInterface $em): JsonResponse
    {
        $tournois = $em->getRepository(Tournois::class)->createQueryBuilder('t')
            ->where('t.statut IN (:statutsArchives)')
            ->setParameter('statutsArchives', ['annule', 'termine'])
            ->orderBy('t.date', 'DESC')
            ->getQuery()
            ->getResult();

        $resultat = array_map(fn(Tournois $t) => [
            'id' => $t->getId(),
            'nom' => $t->getNom(),
            'date' => $t->getDate()->format('Y-m-d'),
            'statut' => $t->getStatut(),
            'motif_annulation' => $t->getMotifAnnulation(),
            'date_annulation' => $t->getDateAnnulation()?->format('Y-m-d'),
        ], $tournois);

        return $this->json($resultat);
    }

    #[Route('/api/tournois/{id<\d+>}', name: 'api_tournoi_detail', methods: ['GET'])]
    public function detail(int $id, EntityManagerInterface $em): JsonResponse
    {
        $tournoi = $em->getRepository(Tournois::class)->find($id);
        if (!$tournoi) {
            return $this->json(['message' => 'Tournoi introuvable.'], 404);
        }

        $participations = $em->getRepository(Participation::class)->findBy(['tournois_id' => $tournoi]);

        $participants = array_map(fn(Participation $p) => [
            'utilisateur_id' => $p->getUtilisateurId()?->getId(),
            'nom' => $p->getUtilisateurId()?->getNom(),
            'prenom' => $p->getUtilisateurId()?->getPrenom(),
            'resultat' => $p->getResultat(),
        ], $participations);

        return $this->json([
            'id' => $tournoi->getId(),
            'nom' => $tournoi->getNom(),
            'date' => $tournoi->getDate()->format('Y-m-d'),
            'statut' => $tournoi->getStatut(),
            'participants' => $participants,
        ]);
    }

    /**
     * Liste légère des membres validés, pour remplir le menu déroulant
     * d'inscription à un tournoi.
     */
    #[Route('/api/tournois/membres-disponibles', name: 'api_tournois_membres', methods: ['GET'])]
    public function membresDisponibles(EntityManagerInterface $em): JsonResponse
    {
        $membres = $em->getRepository(Utilisateur::class)->findBy(['statut_inscription' => 'valide'], ['nom' => 'ASC']);

        $resultat = array_map(fn(Utilisateur $u) => [
            'id' => $u->getId(),
            'nom' => $u->getNom(),
            'prenom' => $u->getPrenom(),
        ], $membres);

        return $this->json($resultat);
    }

    // ---------- Créer / modifier / supprimer un tournoi ----------

    #[Route('/api/tournois', name: 'api_tournoi_creer', methods: ['POST'])]
    public function creer(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['nom']) || empty($data['date'])) {
            return $this->json(['message' => 'Le nom et la date sont obligatoires.'], 400);
        }

        $dateTournoi = new \DateTime($data['date']);
        if ($dateTournoi < new \DateTime('today')) {
            return $this->json(['message' => 'La date du tournoi ne peut pas être dans le passé.'], 400);
        }

        $tournoi = new Tournois();
        $tournoi->setNom($data['nom']);
        $tournoi->setDate($dateTournoi);
        $tournoi->setStatut('planifie');

        $em->persist($tournoi);
        $em->flush();

        return $this->json(['message' => 'Tournoi créé.', 'id' => $tournoi->getId()], 201);
    }

    #[Route('/api/tournois/{id<\d+>}', name: 'api_tournoi_modifier', methods: ['PATCH'])]
    public function modifier(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $tournoi = $em->getRepository(Tournois::class)->find($id);
        if (!$tournoi) {
            return $this->json(['message' => 'Tournoi introuvable.'], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (empty($data['nom']) || empty($data['date'])) {
            return $this->json(['message' => 'Le nom et la date sont obligatoires.'], 400);
        }

        $dateTournoi = new \DateTime($data['date']);
        if ($dateTournoi < new \DateTime('today')) {
            return $this->json(['message' => 'La date du tournoi ne peut pas être dans le passé.'], 400);
        }

        $tournoi->setNom($data['nom']);
        $tournoi->setDate($dateTournoi);
        $em->flush();

        return $this->json(['message' => 'Tournoi mis à jour.']);
    }

    /**
     * Vrai si chaque paire de participants s'est déjà affrontée une fois —
     * vérifie précisément CHAQUE paire (même logique que listeMatchs()),
     * pas seulement un total de lignes : rejouer 3 fois "A vs B" donne 3
     * lignes en base, mais ne fait avancer AUCUNE des autres paires.
     */
    private function tousLesMatchsJoues(Tournois $tournoi, EntityManagerInterface $em): bool
    {
        $participations = $em->getRepository(Participation::class)->findBy(['tournois_id' => $tournoi]);
        $joueurs = array_map(fn(Participation $p) => $p->getUtilisateurId(), $participations);

        $nombreJoueurs = count($joueurs);
        if ($nombreJoueurs < 2) {
            // Moins de 2 participants : rien à jouer, donc rien à
            // terminer automatiquement (évite un tournoi vide qui se
            // clôturerait tout seul dès son lancement).
            return false;
        }

        $dejaJoues = [];
        foreach ($em->getRepository(MatchTournoi::class)->findBy(['tournois_id' => $tournoi]) as $match) {
            $idA = $match->getJoueur1Id()->getId();
            $idB = $match->getJoueur2Id()->getId();
            $cle = min($idA, $idB) . '-' . max($idA, $idB);
            $dejaJoues[$cle] = true;
        }

        for ($i = 0; $i < $nombreJoueurs; $i++) {
            for ($j = $i + 1; $j < $nombreJoueurs; $j++) {
                $cle = min($joueurs[$i]->getId(), $joueurs[$j]->getId()) . '-' . max($joueurs[$i]->getId(), $joueurs[$j]->getId());
                if (!isset($dejaJoues[$cle])) {
                    // Cette paire précise n'a pas encore joué — pas terminé.
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Calcule la liste COMPLÈTE des matchs d'un tournoi round-robin (chaque
     * participant affronte chaque autre une fois), en croisant avec les
     * matchs déjà saisis pour indiquer lesquels restent à faire — sans
     * cette route, l'organisateur n'a aucun moyen de savoir s'il a oublié
     * une rencontre.
     */
    #[Route('/api/tournois/{id<\d+>}/matchs', name: 'api_tournoi_matchs_liste', methods: ['GET'])]
    public function listeMatchs(int $id, EntityManagerInterface $em): JsonResponse
    {
        $tournoi = $em->getRepository(Tournois::class)->find($id);
        if (!$tournoi) {
            return $this->json(['message' => 'Tournoi introuvable.'], 404);
        }

        $participations = $em->getRepository(Participation::class)->findBy(['tournois_id' => $tournoi]);
        $joueurs = array_map(fn(Participation $p) => $p->getUtilisateurId(), $participations);

        // "Dictionnaire" des matchs déjà joués, indexé par une clé qui ne
        // dépend pas de l'ordre des 2 joueurs (min-max), pour retrouver un
        // match peu importe qui était "joueur1" ou "joueur2" au moment de la saisie.
        $dejaJoues = [];
        foreach ($em->getRepository(MatchTournoi::class)->findBy(['tournois_id' => $tournoi]) as $match) {
            $idA = $match->getJoueur1Id()->getId();
            $idB = $match->getJoueur2Id()->getId();
            $cle = min($idA, $idB) . '-' . max($idA, $idB);
            $dejaJoues[$cle] = $match;
        }

        $resultat = [];
        $nombreJoueurs = count($joueurs);
        for ($i = 0; $i < $nombreJoueurs; $i++) {
            for ($j = $i + 1; $j < $nombreJoueurs; $j++) {
                $joueurA = $joueurs[$i];
                $joueurB = $joueurs[$j];
                $cle = min($joueurA->getId(), $joueurB->getId()) . '-' . max($joueurA->getId(), $joueurB->getId());
                $match = $dejaJoues[$cle] ?? null;

                $resultat[] = [
                    'joueur1_id' => $joueurA->getId(),
                    'joueur1_nom' => $joueurA->getPrenom() . ' ' . $joueurA->getNom(),
                    'joueur2_id' => $joueurB->getId(),
                    'joueur2_nom' => $joueurB->getPrenom() . ' ' . $joueurB->getNom(),
                    'joue' => $match !== null,
                    'resultat' => $match?->getResultat(),
                ];
            }
        }

        return $this->json($resultat);
    }

    /**
     * Enregistre le résultat d'un match entre 2 participants, et met à
     * jour leurs points en conséquence (victoire = 1, nul = 0,5, défaite =
     * 0 — méthode confirmée par la tutrice). Le champ "resultat" de
     * Participation sert de compteur de points cumulés pour ce tournoi.
     */
    #[Route('/api/tournois/{id<\d+>}/matchs', name: 'api_tournoi_match_saisir', methods: ['POST'])]
    public function saisirMatch(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $tournoi = $em->getRepository(Tournois::class)->find($id);
        if (!$tournoi) {
            return $this->json(['message' => 'Tournoi introuvable.'], 404);
        }

        if ($tournoi->getStatut() !== 'en_cours') {
            return $this->json(['message' => 'Le tournoi doit être en cours pour saisir un résultat.'], 409);
        }

        $data = json_decode($request->getContent(), true);
        $joueur1Id = $data['joueur1_id'] ?? null;
        $joueur2Id = $data['joueur2_id'] ?? null;
        $resultat = $data['resultat'] ?? null;

        if (empty($joueur1Id) || empty($joueur2Id) || !in_array($resultat, ['joueur1', 'joueur2', 'nul'], true)) {
            return $this->json(['message' => 'Sélectionnez les 2 joueurs et un résultat valide.'], 400);
        }

        if ($joueur1Id === $joueur2Id) {
            return $this->json(['message' => 'Choisissez deux joueurs différents.'], 400);
        }

        $joueur1 = $em->getRepository(Utilisateur::class)->find($joueur1Id);
        $joueur2 = $em->getRepository(Utilisateur::class)->find($joueur2Id);

        $participation1 = $em->getRepository(Participation::class)->findOneBy(['tournois_id' => $tournoi, 'utilisateur_id' => $joueur1]);
        $participation2 = $em->getRepository(Participation::class)->findOneBy(['tournois_id' => $tournoi, 'utilisateur_id' => $joueur2]);

        if (!$participation1 || !$participation2) {
            return $this->json(['message' => 'Les deux joueurs doivent être inscrits à ce tournoi.'], 400);
        }

        // Empêche de rejouer 2 fois la même paire — sans ça, "A bat B" 3
        // fois de suite compterait comme 3 matchs différents et pourrait
        // faire croire (à tort) que le tournoi est terminé.
        foreach ($em->getRepository(MatchTournoi::class)->findBy(['tournois_id' => $tournoi]) as $matchExistant) {
            $idExistantA = $matchExistant->getJoueur1Id()->getId();
            $idExistantB = $matchExistant->getJoueur2Id()->getId();
            $memePaire = (in_array((int)$joueur1Id, [$idExistantA, $idExistantB], true))
                && (in_array((int)$joueur2Id, [$idExistantA, $idExistantB], true));
            if ($memePaire) {
                return $this->json(['message' => 'Ce match a déjà été joué.'], 409);
            }
        }

        $match = new MatchTournoi();
        $match->setTournoisId($tournoi);
        $match->setJoueur1Id($joueur1);
        $match->setJoueur2Id($joueur2);
        $match->setResultat($resultat);
        $match->setDateSaisie(new \DateTime());
        $em->persist($match);

        // Points selon le résultat : victoire = 1, nul = 0,5, défaite = 0.
        [$pointsJoueur1, $pointsJoueur2] = match ($resultat) {
            'joueur1' => [1.0, 0.0],
            'joueur2' => [0.0, 1.0],
            'nul' => [0.5, 0.5],
        };

        $participation1->setResultat((string)((float)($participation1->getResultat() ?? 0) + $pointsJoueur1));
        $participation2->setResultat((string)((float)($participation2->getResultat() ?? 0) + $pointsJoueur2));

        // Ce flush() enregistre le match qu'on vient de créer AVANT de
        // vérifier s'il ne reste plus rien à jouer — sinon,
        // tousLesMatchsJoues() le compterait comme "pas encore joué"
        // (il ne serait pas encore visible en base au moment du calcul).
        $em->flush();

        if ($this->tousLesMatchsJoues($tournoi, $em)) {
            $tournoi->setStatut('termine');
            $em->flush();
        }

        return $this->detail($id, $em);
    }

    /**
     * Termine un tournoi : verrouille tout, déplace le tournoi dans
     * l'archive avec le classement final (déjà calculé au fil des
     * matchs saisis, rien à recalculer ici).
     */
    #[Route('/api/tournois/{id<\d+>}/terminer', name: 'api_tournoi_terminer', methods: ['POST'])]
    public function terminerTournoi(int $id, EntityManagerInterface $em): JsonResponse
    {
        $tournoi = $em->getRepository(Tournois::class)->find($id);
        if (!$tournoi) {
            return $this->json(['message' => 'Tournoi introuvable.'], 404);
        }

        if ($tournoi->getStatut() !== 'en_cours') {
            return $this->json(['message' => 'Seul un tournoi en cours peut être terminé.'], 409);
        }

        $tournoi->setStatut('termine');
        $em->flush();

        return $this->json(['message' => 'Tournoi terminé.']);
    }

    /**
     * Annule un tournoi : ne le supprime pas, il reste consultable dans les
     * archives avec son motif. Un motif est obligatoire.
     */
    #[Route('/api/tournois/{id<\d+>}/annuler', name: 'api_tournoi_annuler', methods: ['POST'])]
    public function annuler(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $tournoi = $em->getRepository(Tournois::class)->find($id);
        if (!$tournoi) {
            return $this->json(['message' => 'Tournoi introuvable.'], 404);
        }

        if ($tournoi->getStatut() === 'annule') {
            return $this->json(['message' => 'Ce tournoi est déjà annulé.'], 409);
        }

        $data = json_decode($request->getContent(), true);
        if (empty($data['motif'])) {
            return $this->json(['message' => 'Le motif d\'annulation est obligatoire.'], 400);
        }

        $tournoi->setStatut('annule');
        $tournoi->setMotifAnnulation($data['motif']);
        $tournoi->setDateAnnulation(new \DateTime());
        $em->flush();

        return $this->json(['message' => 'Tournoi annulé.']);
    }

    // ---------- Inscriptions (avant lancement uniquement) ----------

    #[Route('/api/tournois/{id<\d+>}/participants', name: 'api_tournoi_inscrire', methods: ['POST'])]
    public function inscrireParticipant(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $tournoi = $em->getRepository(Tournois::class)->find($id);
        if (!$tournoi) {
            return $this->json(['message' => 'Tournoi introuvable.'], 404);
        }

        if ($tournoi->getStatut() !== 'planifie') {
            return $this->json(['message' => 'Ce tournoi est déjà lancé, impossible d\'inscrire un nouveau membre.'], 409);
        }

        $data = json_decode($request->getContent(), true);
        if (empty($data['utilisateur_id'])) {
            return $this->json(['message' => 'Le membre est obligatoire.'], 400);
        }

        $utilisateur = $em->getRepository(Utilisateur::class)->find($data['utilisateur_id']);
        if (!$utilisateur) {
            return $this->json(['message' => 'Membre introuvable.'], 404);
        }

        $dejaInscrit = $em->getRepository(Participation::class)->findOneBy([
            'tournois_id' => $tournoi,
            'utilisateur_id' => $utilisateur,
        ]);
        if ($dejaInscrit) {
            return $this->json(['message' => 'Ce membre est déjà inscrit à ce tournoi.'], 409);
        }

        $participation = new Participation();
        $participation->setTournoisId($tournoi);
        $participation->setUtilisateurId($utilisateur);

        $em->persist($participation);
        $em->flush();

        return $this->json(['message' => 'Membre inscrit.'], 201);
    }

    #[Route('/api/tournois/{id<\d+>}/participants/{utilisateurId<\d+>}', name: 'api_tournoi_desinscrire', methods: ['DELETE'])]
    public function desinscrireParticipant(int $id, int $utilisateurId, EntityManagerInterface $em): JsonResponse
    {
        $tournoi = $em->getRepository(Tournois::class)->find($id);
        if (!$tournoi) {
            return $this->json(['message' => 'Tournoi introuvable.'], 404);
        }

        if ($tournoi->getStatut() !== 'planifie') {
            return $this->json(['message' => 'Ce tournoi est déjà lancé, impossible de désinscrire un membre.'], 409);
        }

        $utilisateur = $em->getRepository(Utilisateur::class)->find($utilisateurId);
        $participation = $em->getRepository(Participation::class)->findOneBy([
            'tournois_id' => $tournoi,
            'utilisateur_id' => $utilisateur,
        ]);

        if (!$participation) {
            return $this->json(['message' => 'Inscription introuvable.'], 404);
        }

        $em->remove($participation);
        $em->flush();

        return $this->json(['message' => 'Membre désinscrit.']);
    }

    // ---------- Lancement ----------

    #[Route('/api/tournois/{id<\d+>}/lancer', name: 'api_tournoi_lancer', methods: ['POST'])]
    public function lancer(int $id, EntityManagerInterface $em): JsonResponse
    {
        $tournoi = $em->getRepository(Tournois::class)->find($id);
        if (!$tournoi) {
            return $this->json(['message' => 'Tournoi introuvable.'], 404);
        }

        if ($tournoi->getStatut() !== 'planifie') {
            return $this->json(['message' => 'Ce tournoi est déjà lancé.'], 409);
        }

        $nombreParticipants = $em->getRepository(Participation::class)->count(['tournois_id' => $tournoi]);
        if ($nombreParticipants < 2) {
            return $this->json(['message' => 'Il faut au moins 2 membres inscrits pour lancer le tournoi.'], 400);
        }

        $tournoi->setStatut('en_cours');
        $em->flush();

        return $this->json(['message' => 'Tournoi lancé. Les inscriptions sont maintenant verrouillées.']);
    }
}
