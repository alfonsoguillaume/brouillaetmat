<?php

namespace App\Controller;

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
            ->where('t.statut != :annule')
            ->setParameter('annule', 'annule')
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
        $tournois = $em->getRepository(Tournois::class)->findBy(['statut' => 'annule'], ['date_annulation' => 'DESC']);

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
