<?php


namespace App\Controller;

use App\Entity\ArchiveEmprunt;
use App\Entity\Emprunt;
use App\Entity\Ouvrage;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Toutes les routes sont réservées aux gestionnaires (et admins, via la
 * hiérarchie des rôles) — voir security.yaml : ^/api/bibliotheque.
 */
class BibliothequeController extends AbstractController
{
    // ---------- Catalogue des ouvrages ----------

    /**
     * Liste tous les ouvrages, avec un indicateur "disponible" (pas
     * actuellement emprunté), calculé à la volée.
     */
    #[Route('/api/bibliotheque/ouvrages', name: 'api_biblio_ouvrages_liste', methods: ['GET'])]
    public function listeOuvrages(EntityManagerInterface $em): JsonResponse
    {
        $ouvrages = $em->getRepository(Ouvrage::class)->findBy([], ['titre' => 'ASC']);

        $resultat = array_map(fn(Ouvrage $ouvrage) => [
            'id' => $ouvrage->getId(),
            'titre' => $ouvrage->getTitre(),
            'auteur' => $ouvrage->getAuteur(),
            'disponible' => $ouvrage->getEmprunts()->isEmpty(),
        ], $ouvrages);

        return $this->json($resultat);
    }

    #[Route('/api/bibliotheque/ouvrages', name: 'api_biblio_ouvrage_ajouter', methods: ['POST'])]
    public function ajouterOuvrage(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['titre']) || empty($data['auteur'])) {
            return $this->json(['message' => 'Le titre et l\'auteur sont obligatoires.'], 400);
        }

        $ouvrage = new Ouvrage();
        $ouvrage->setTitre($data['titre']);
        $ouvrage->setAuteur($data['auteur']);

        $em->persist($ouvrage);
        $em->flush();

        return $this->json(['message' => 'Ouvrage ajouté.', 'id' => $ouvrage->getId()], 201);
    }

    #[Route('/api/bibliotheque/ouvrages/{id<\d+>}', name: 'api_biblio_ouvrage_modifier', methods: ['PATCH'])]
    public function modifierOuvrage(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $ouvrage = $em->getRepository(Ouvrage::class)->find($id);
        if (!$ouvrage) {
            return $this->json(['message' => 'Ouvrage introuvable.'], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (empty($data['titre']) || empty($data['auteur'])) {
            return $this->json(['message' => 'Le titre et l\'auteur sont obligatoires.'], 400);
        }

        $ouvrage->setTitre($data['titre']);
        $ouvrage->setAuteur($data['auteur']);
        $em->flush();

        return $this->json(['message' => 'Ouvrage mis à jour.']);
    }

    /**
     * Supprime un ouvrage. Refuse s'il est actuellement emprunté ou s'il a
     * un historique de prêts archivés (contrainte de clé étrangère).
     */
    #[Route('/api/bibliotheque/ouvrages/{id<\d+>}', name: 'api_biblio_ouvrage_supprimer', methods: ['DELETE'])]
    public function supprimerOuvrage(int $id, EntityManagerInterface $em): JsonResponse
    {
        $ouvrage = $em->getRepository(Ouvrage::class)->find($id);
        if (!$ouvrage) {
            return $this->json(['message' => 'Ouvrage introuvable.'], 404);
        }

        try {
            $em->remove($ouvrage);
            $em->flush();
        } catch (\Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException $e) {
            return $this->json([
                'message' => 'Impossible de supprimer : cet ouvrage est emprunté ou a un historique de prêts.',
            ], 409);
        }

        return $this->json(['message' => 'Ouvrage supprimé.']);
    }

    // ---------- Membres (pour la liste déroulante) ----------

    /**
     * Liste légère des membres validés (juste de quoi remplir un menu
     * déroulant) — distincte de /api/admin/membres qui est réservée aux
     * admins et renvoie beaucoup plus d'informations.
     */
    #[Route('/api/bibliotheque/membres', name: 'api_biblio_membres_liste', methods: ['GET'])]
    public function listeMembres(EntityManagerInterface $em): JsonResponse
    {
        $membres = $em->getRepository(Utilisateur::class)->findBy(['statut_inscription' => 'valide'], ['nom' => 'ASC']);

        $resultat = array_map(fn(Utilisateur $u) => [
            'id' => $u->getId(),
            'nom' => $u->getNom(),
            'prenom' => $u->getPrenom(),
        ], $membres);

        return $this->json($resultat);
    }

    // ---------- Emprunts en cours ----------

    #[Route('/api/bibliotheque/emprunts', name: 'api_biblio_emprunts_liste', methods: ['GET'])]
    public function listeEmprunts(EntityManagerInterface $em): JsonResponse
    {
        $emprunts = $em->getRepository(Emprunt::class)->findBy([], ['date_emprunt' => 'DESC']);

        $resultat = array_map(fn(Emprunt $emprunt) => [
            'id' => $emprunt->getId(),
            'ouvrage_titre' => $emprunt->getOuvrageId()?->getTitre(),
            'membre' => $emprunt->getUtilisateurId()?->getPrenom() . ' ' . $emprunt->getUtilisateurId()?->getNom(),
            'date_emprunt' => $emprunt->getDateEmprunt()->format('Y-m-d'),
        ], $emprunts);

        return $this->json($resultat);
    }

    #[Route('/api/bibliotheque/emprunts', name: 'api_biblio_emprunt_creer', methods: ['POST'])]
    public function creerEmprunt(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['ouvrage_id']) || empty($data['utilisateur_id'])) {
            return $this->json(['message' => 'L\'ouvrage et le membre sont obligatoires.'], 400);
        }

        $ouvrage = $em->getRepository(Ouvrage::class)->find($data['ouvrage_id']);
        if (!$ouvrage) {
            return $this->json(['message' => 'Ouvrage introuvable.'], 404);
        }

        // Sécurité en plus du filtrage déjà fait côté Angular : un seul
        // exemplaire par titre, donc un ouvrage déjà emprunté ne peut pas
        // l'être une deuxième fois.
        if (!$ouvrage->getEmprunts()->isEmpty()) {
            return $this->json(['message' => 'Cet ouvrage est déjà emprunté.'], 409);
        }

        $membre = $em->getRepository(Utilisateur::class)->find($data['utilisateur_id']);
        if (!$membre) {
            return $this->json(['message' => 'Membre introuvable.'], 404);
        }

        $emprunt = new Emprunt();
        $emprunt->setOuvrageId($ouvrage);
        $emprunt->setUtilisateurId($membre);
        $emprunt->setDateEmprunt(new \DateTime());

        $em->persist($emprunt);
        $em->flush();

        return $this->json(['message' => 'Emprunt enregistré.'], 201);
    }

    /**
     * Marque un emprunt comme rendu : supprime la ligne "en cours" et crée
     * la ligne archivée correspondante, avec la date du jour comme retour.
     */
    #[Route('/api/bibliotheque/emprunts/{id<\d+>}/retour', name: 'api_biblio_emprunt_retour', methods: ['POST'])]
    public function marquerRetour(int $id, EntityManagerInterface $em): JsonResponse
    {
        $emprunt = $em->getRepository(Emprunt::class)->find($id);
        if (!$emprunt) {
            return $this->json(['message' => 'Emprunt introuvable.'], 404);
        }

        $archive = new ArchiveEmprunt();
        $archive->setOuvrageId($emprunt->getOuvrageId());
        $archive->setUtilisateurId($emprunt->getUtilisateurId());
        $archive->setDateEmprunt($emprunt->getDateEmprunt());
        $archive->setDateRetour(new \DateTime());

        $em->persist($archive);
        $em->remove($emprunt);
        $em->flush();

        return $this->json(['message' => 'Retour enregistré.']);
    }

    // ---------- Archives des prêts ----------

    #[Route('/api/bibliotheque/archives', name: 'api_biblio_archives_liste', methods: ['GET'])]
    public function listeArchives(EntityManagerInterface $em): JsonResponse
    {
        $archives = $em->getRepository(ArchiveEmprunt::class)->findBy([], ['date_retour' => 'DESC']);

        $resultat = array_map(fn(ArchiveEmprunt $archive) => [
            'id' => $archive->getId(),
            'ouvrage_titre' => $archive->getOuvrageId()?->getTitre(),
            'membre' => $archive->getUtilisateurId()?->getPrenom() . ' ' . $archive->getUtilisateurId()?->getNom(),
            'date_emprunt' => $archive->getDateEmprunt()->format('Y-m-d'),
            'date_retour' => $archive->getDateRetour()?->format('Y-m-d'),
        ], $archives);

        return $this->json($resultat);
    }
}
