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

// GET ouvert à tout membre connecté. POST/PATCH/DELETE réservé aux admins
// (pas les gestionnaires, contrairement aux articles/bibliothèque)
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

    // Archives: tournois annulés/terminés, avec motif et date
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

    // Membres validés, version light pour le menu d'inscription
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

    // vrai si chaque paire a joué une fois (vérifie chaque paire, pas juste le total de lignes)
    private function tousLesMatchsJoues(Tournois $tournoi, EntityManagerInterface $em): bool
    {
        $participations = $em->getRepository(Participation::class)->findBy(['tournois_id' => $tournoi]);
        $joueurs = array_map(fn(Participation $p) => $p->getUtilisateurId(), $participations);

        $nombreJoueurs = count($joueurs);
        if ($nombreJoueurs < 2) {
            // moins de 2 participants, rien à terminer automatiquement
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
                    // cette paire n'a pas joué, pas terminé
                    return false;
                }
            }
        }

        return true;
    }

    // Liste complète des matchs round-robin, croisée avec les matchs déjà saisis
    #[Route('/api/tournois/{id<\d+>}/matchs', name: 'api_tournoi_matchs_liste', methods: ['GET'])]
    public function listeMatchs(int $id, EntityManagerInterface $em): JsonResponse
    {
        $tournoi = $em->getRepository(Tournois::class)->find($id);
        if (!$tournoi) {
            return $this->json(['message' => 'Tournoi introuvable.'], 404);
        }

        $participations = $em->getRepository(Participation::class)->findBy(['tournois_id' => $tournoi]);
        $joueurs = array_map(fn(Participation $p) => $p->getUtilisateurId(), $participations);

        // matchs déjà joués, indexés par clé min-max (peu importe qui était joueur1/2)
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

    // Enregistre un résultat, met à jour les points (victoire=1, nul=0,5, défaite=0)
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

        // empêche de rejouer la même paire deux fois
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

        // points selon résultat
        [$pointsJoueur1, $pointsJoueur2] = match ($resultat) {
            'joueur1' => [1.0, 0.0],
            'joueur2' => [0.0, 1.0],
            'nul' => [0.5, 0.5],
        };

        $participation1->setResultat((string)((float)($participation1->getResultat() ?? 0) + $pointsJoueur1));
        $participation2->setResultat((string)((float)($participation2->getResultat() ?? 0) + $pointsJoueur2));

        // flush avant de vérifier la fin, sinon ce match ne serait pas encore visible en base
        $em->flush();

        if ($this->tousLesMatchsJoues($tournoi, $em)) {
            $tournoi->setStatut('termine');
            $em->flush();
        }

        return $this->detail($id, $em);
    }

    // Termine un tournoi, classement déjà calculé au fil des matchs (rien à recalculer)
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

    // Annule un tournoi (pas supprimé, reste dans les archives avec le motif)
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
