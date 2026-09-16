<?php

namespace App\Controller;

use App\Entity\Document;
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
     * Liste tous les documents, du plus récent au plus ancien.
     */
    #[Route('/api/documents', name: 'api_documents_liste', methods: ['GET'])]
    public function liste(EntityManagerInterface $em): JsonResponse
    {
        $documents = $em->getRepository(Document::class)->findBy([], ['date_ajout' => 'DESC']);

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
