<?php

namespace App\Controller;

use App\Entity\Document;
use App\Entity\Dossier;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Toutes les routes de ce contrôleur sont déjà réservées aux admins via
 * security.yaml : { path: ^/api/documents, roles: ROLE_ADMIN }.
 */
class DocumentController extends AbstractController
{
    // Volontairement HORS de public/ : ce dossier n'est donc jamais
    // accessible par une simple URL, même en devinant le nom du fichier.
    // Le seul moyen d'obtenir un fichier est de passer par la route
    // /telecharger ci-dessous, qui vérifie le rôle avant de le servir.
    private const DOSSIER_DOCUMENTS = __DIR__ . '/../../var/uploads/documents';
    private const TYPES_AUTORISES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'image/jpeg',
        'image/png',
    ];
    private const TAILLE_MAX_OCTETS = 10 * 1024 * 1024; // 10 Mo

    /**
     * Liste les documents d'un dossier précis (?dossier_id=5), ou ceux "à
     * la racine" (hors de tout dossier) si aucun paramètre n'est fourni.
     */
    #[Route('/api/documents', name: 'api_documents_liste', methods: ['GET'])]
    public function liste(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $dossierId = $request->query->get('dossier_id');

        $qb = $em->getRepository(Document::class)->createQueryBuilder('d')
            ->orderBy('d.date_ajout', 'DESC');

        if ($dossierId) {
            $qb->where('d.dossier_id = :dossierId')->setParameter('dossierId', $dossierId);
        } else {
            $qb->where('d.dossier_id IS NULL');
        }

        $documents = $qb->getQuery()->getResult();

        $resultat = array_map(fn(Document $document) => [
            'id' => $document->getId(),
            'nom' => $document->getNom(),
            'fichier' => $document->getFichier(),
            'date_ajout' => $document->getDateAjout()->format('Y-m-d H:i:s'),
            'ajoute_par' => $document->getAjouteParId()?->getPrenom() . ' ' . $document->getAjouteParId()?->getNom(),
        ], $documents);

        return $this->json($resultat);
    }

    /**
     * Liste les dossiers, avec le nombre de documents qu'ils contiennent.
     */
    #[Route('/api/documents/dossiers', name: 'api_dossiers_liste', methods: ['GET'])]
    public function listeDossiers(EntityManagerInterface $em): JsonResponse
    {
        $dossiers = $em->getRepository(Dossier::class)->findBy([], ['nom' => 'ASC']);

        $resultat = array_map(fn(Dossier $dossier) => [
            'id' => $dossier->getId(),
            'nom' => $dossier->getNom(),
            'nombre_documents' => $dossier->getDocuments()->count(),
        ], $dossiers);

        return $this->json($resultat);
    }

    /**
     * Crée un nouveau dossier, vide au départ.
     */
    #[Route('/api/documents/dossiers', name: 'api_dossier_creer', methods: ['POST'])]
    public function creerDossier(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['nom'])) {
            return $this->json(['message' => 'Le nom du dossier est obligatoire.'], 400);
        }

        /** @var Utilisateur $utilisateur */
        $utilisateur = $this->getUser();

        $dossier = new Dossier();
        $dossier->setNom($data['nom']);
        $dossier->setDateCreation(new \DateTime());
        $dossier->setCreeParId($utilisateur);

        $em->persist($dossier);
        $em->flush();

        return $this->json(['message' => 'Dossier créé.', 'id' => $dossier->getId()], 201);
    }

    /**
     * Supprime un dossier ET tout son contenu (documents + fichiers sur le
     * disque). Décision assumée : plutôt que de bloquer la suppression
     * d'un dossier non vide, on la permet, mais seulement après
     * confirmation explicite côté Angular (le message prévient bien que
     * le contenu sera perdu).
     */
    #[Route('/api/documents/dossiers/{id<\d+>}', name: 'api_dossier_supprimer', methods: ['DELETE'])]
    public function supprimerDossier(int $id, EntityManagerInterface $em): JsonResponse
    {
        $dossier = $em->getRepository(Dossier::class)->find($id);
        if (!$dossier) {
            return $this->json(['message' => 'Dossier introuvable.'], 404);
        }

        foreach ($dossier->getDocuments() as $document) {
            $cheminFichier = self::DOSSIER_DOCUMENTS . '/' . $document->getFichier();
            if (file_exists($cheminFichier)) {
                unlink($cheminFichier);
            }
            $em->remove($document);
        }

        $em->remove($dossier);
        $em->flush();

        return $this->json(['message' => 'Dossier et son contenu supprimés.']);
    }

    /**
     * Ajoute un nouveau document (multipart/form-data : "nom" + "fichier").
     */
    #[Route('/api/documents', name: 'api_document_ajouter', methods: ['POST'])]
    public function ajouter(Request $request, EntityManagerInterface $em, SluggerInterface $slugger): JsonResponse
    {
        $nom = $request->request->get('nom');
        $fichier = $request->files->get('fichier');

        if (empty($nom)) {
            return $this->json(['message' => 'Le nom du document est obligatoire.'], 400);
        }

        if (!$fichier) {
            return $this->json(['message' => 'Un fichier est obligatoire.'], 400);
        }

        if (!in_array($fichier->getMimeType(), self::TYPES_AUTORISES, true)) {
            return $this->json(['message' => 'Format de fichier non autorisé (PDF, Word, Excel ou image uniquement).'], 400);
        }

        if ($fichier->getSize() > self::TAILLE_MAX_OCTETS) {
            return $this->json(['message' => 'Le fichier ne doit pas dépasser 10 Mo.'], 400);
        }

        $nomOriginal = pathinfo($fichier->getClientOriginalName(), PATHINFO_FILENAME);
        $nomSecurise = $slugger->slug($nomOriginal);
        $nouveauNom = $nomSecurise . '-' . uniqid() . '.' . $fichier->guessExtension();

        try {
            $fichier->move(self::DOSSIER_DOCUMENTS, $nouveauNom);
        } catch (FileException $e) {
            return $this->json(['message' => 'Erreur lors de l\'enregistrement du fichier.'], 500);
        }

        /** @var Utilisateur $utilisateur */
        $utilisateur = $this->getUser();

        $document = new Document();
        $document->setNom($nom);
        $document->setFichier($nouveauNom);
        $document->setDateAjout(new \DateTime());
        $document->setAjouteParId($utilisateur);

        $dossierId = $request->request->get('dossier_id');
        if ($dossierId) {
            $dossier = $em->getRepository(Dossier::class)->find($dossierId);
            if ($dossier) {
                $document->setDossierId($dossier);
            }
        }

        $em->persist($document);
        $em->flush();

        return $this->json(['message' => 'Document ajouté.', 'id' => $document->getId()], 201);
    }

    /**
     * Sert le fichier d'un document, uniquement si l'appelant a le rôle
     * requis (vérifié par le firewall avant même d'entrer ici). C'est le
     * SEUL moyen d'obtenir le contenu d'un document.
     */
    #[Route('/api/documents/{id<\d+>}/telecharger', name: 'api_document_telecharger', methods: ['GET'])]
    public function telecharger(int $id, EntityManagerInterface $em): Response
    {
        $document = $em->getRepository(Document::class)->find($id);
        if (!$document) {
            return $this->json(['message' => 'Document introuvable.'], 404);
        }

        $chemin = self::DOSSIER_DOCUMENTS . '/' . $document->getFichier();
        if (!file_exists($chemin)) {
            return $this->json(['message' => 'Fichier introuvable sur le serveur.'], 404);
        }

        $reponse = new BinaryFileResponse($chemin);
        $reponse->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $document->getNom() . '.' . pathinfo($document->getFichier(), PATHINFO_EXTENSION)
        );

        return $reponse;
    }

    /**
     * Supprime un document (et son fichier sur le disque).
     */
    #[Route('/api/documents/{id<\d+>}', name: 'api_document_supprimer', methods: ['DELETE'])]
    public function supprimer(int $id, EntityManagerInterface $em): JsonResponse
    {
        $document = $em->getRepository(Document::class)->find($id);
        if (!$document) {
            return $this->json(['message' => 'Document introuvable.'], 404);
        }

        $cheminFichier = self::DOSSIER_DOCUMENTS . '/' . $document->getFichier();
        if (file_exists($cheminFichier)) {
            unlink($cheminFichier);
        }

        $em->remove($document);
        $em->flush();

        return $this->json(['message' => 'Document supprimé.']);
    }
}
