<?php


namespace App\Controller;

use App\Entity\Article;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class ArticleController extends AbstractController
{
    private const DOSSIER_PHOTOS = __DIR__ . '/../../public/uploads/articles';
    private const TYPES_AUTORISES = ['image/jpeg', 'image/png', 'image/webp'];
    private const TAILLE_MAX_OCTETS = 5 * 1024 * 1024; // 5 Mo

    /**
     * Liste tous les articles, du plus récent au plus ancien. Route publique
     * (voir security.yaml) : visiteurs, membres et gestionnaires y ont accès,
     * c'est le frontend qui décide de flouter la photo si non connecté.
     */
    #[Route('/api/articles', name: 'api_articles_liste', methods: ['GET'])]
    public function liste(EntityManagerInterface $em): JsonResponse
    {
        $articles = $em->getRepository(Article::class)->findBy([], ['date_creation' => 'DESC']);

        $resultat = array_map(fn(Article $article) => $this->formaterArticle($article), $articles);

        return $this->json($resultat);
    }

    /**
     * Détail d'un article précis.
     */
    #[Route('/api/articles/{id<\d+>}', name: 'api_article_detail', methods: ['GET'])]
    public function detail(int $id, EntityManagerInterface $em): JsonResponse
    {
        $article = $em->getRepository(Article::class)->find($id);

        if (!$article) {
            return $this->json(['message' => 'Article introuvable.'], 404);
        }

        return $this->json($this->formaterArticle($article));
    }

    /**
     * Crée un nouvel article. Réservé aux gestionnaires (voir security.yaml).
     * Envoyé en multipart/form-data (pas en JSON) à cause de la photo.
     */
    #[Route('/api/articles', name: 'api_article_creer', methods: ['POST'])]
    public function creer(Request $request, EntityManagerInterface $em, SluggerInterface $slugger): JsonResponse
    {
        $titre = $request->request->get('titre');
        $contenu = $request->request->get('contenu');

        if (empty($titre) || empty($contenu)) {
            return $this->json(['message' => 'Le titre et le contenu sont obligatoires.'], 400);
        }

        /** @var Utilisateur $utilisateur */
        $utilisateur = $this->getUser();

        $article = new Article();
        $article->setTitre($titre);
        $article->setContenu($contenu);
        $article->setDateCreation(new \DateTime());
        $article->setAuteurId($utilisateur);

        $fichierPhoto = $request->files->get('photo');
        if ($fichierPhoto) {
            $resultat = $this->enregistrerPhoto($fichierPhoto, $slugger);
            if ($resultat instanceof JsonResponse) {
                return $resultat;
            }
            $article->setPhoto($resultat);
        }

        $em->persist($article);
        $em->flush();

        return $this->json(['message' => 'Article publié.', 'id' => $article->getId()], 201);
    }

    /**
     * Modifie un article existant. Réservé aux gestionnaires.
     */
    #[Route('/api/articles/{id<\d+>}', name: 'api_article_modifier', methods: ['POST'])]
    public function modifier(int $id, Request $request, EntityManagerInterface $em, SluggerInterface $slugger): JsonResponse
    {
        $article = $em->getRepository(Article::class)->find($id);
        if (!$article) {
            return $this->json(['message' => 'Article introuvable.'], 404);
        }

        $titre = $request->request->get('titre');
        $contenu = $request->request->get('contenu');

        if (empty($titre) || empty($contenu)) {
            return $this->json(['message' => 'Le titre et le contenu sont obligatoires.'], 400);
        }

        /** @var Utilisateur $utilisateur */
        $utilisateur = $this->getUser();

        $article->setTitre($titre);
        $article->setContenu($contenu);
        $article->setModificateurId($utilisateur);
        $article->setDateModification(new \DateTime());

        $fichierPhoto = $request->files->get('photo');
        if ($fichierPhoto) {
            $resultat = $this->enregistrerPhoto($fichierPhoto, $slugger);
            if ($resultat instanceof JsonResponse) {
                return $resultat;
            }
            $this->supprimerAnciennePhoto($article->getPhoto());
            $article->setPhoto($resultat);
        }

        $em->flush();

        return $this->json(['message' => 'Article mis à jour.']);
    }

    /**
     * Supprime un article (et sa photo sur le disque, le cas échéant).
     */
    #[Route('/api/articles/{id<\d+>}', name: 'api_article_supprimer', methods: ['DELETE'])]
    public function supprimer(int $id, EntityManagerInterface $em): JsonResponse
    {
        $article = $em->getRepository(Article::class)->find($id);
        if (!$article) {
            return $this->json(['message' => 'Article introuvable.'], 404);
        }

        $this->supprimerAnciennePhoto($article->getPhoto());

        $em->remove($article);
        $em->flush();

        return $this->json(['message' => 'Article supprimé.']);
    }

    /**
     * Transforme un objet Article en tableau prêt pour le JSON, avec le nom
     * (pas l'objet complet) de l'auteur/modificateur.
     */
    private function formaterArticle(Article $article): array
    {
        return [
            'id' => $article->getId(),
            'titre' => $article->getTitre(),
            'contenu' => $article->getContenu(),
            'photo' => $article->getPhoto(),
            'date_creation' => $article->getDateCreation()->format('Y-m-d H:i:s'),
            'auteur' => $article->getAuteurId()?->getPrenom() . ' ' . $article->getAuteurId()?->getNom(),
            'modificateur' => $article->getModificateurId()
                ? $article->getModificateurId()->getPrenom() . ' ' . $article->getModificateurId()->getNom()
                : null,
            'date_modification' => $article->getDateModification()?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Valide et enregistre un fichier photo sur le disque, renvoie son nom
     * de fichier généré (à stocker dans la colonne "photo"), ou une réponse
     * d'erreur si le fichier n'est pas valide.
     */
    private function enregistrerPhoto($fichier, SluggerInterface $slugger): string|JsonResponse
    {
        if (!in_array($fichier->getMimeType(), self::TYPES_AUTORISES, true)) {
            return $this->json(['message' => 'La photo doit être au format JPEG, PNG ou WebP.'], 400);
        }

        if ($fichier->getSize() > self::TAILLE_MAX_OCTETS) {
            return $this->json(['message' => 'La photo ne doit pas dépasser 5 Mo.'], 400);
        }

        $nomOriginal = pathinfo($fichier->getClientOriginalName(), PATHINFO_FILENAME);
        $nomSecurise = $slugger->slug($nomOriginal);
        $nouveauNom = $nomSecurise . '-' . uniqid() . '.' . $fichier->guessExtension();

        try {
            $fichier->move(self::DOSSIER_PHOTOS, $nouveauNom);
        } catch (FileException $e) {
            return $this->json(['message' => 'Erreur lors de l\'enregistrement de la photo.'], 500);
        }

        return $nouveauNom;
    }

    private function supprimerAnciennePhoto(?string $nomFichier): void
    {
        if ($nomFichier && file_exists(self::DOSSIER_PHOTOS . '/' . $nomFichier)) {
            unlink(self::DOSSIER_PHOTOS . '/' . $nomFichier);
        }
    }
}
