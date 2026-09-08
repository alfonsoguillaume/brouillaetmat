<?php

namespace App\Entity;

use App\Repository\UtilisateurRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UtilisateurRepository::class)]
class Utilisateur
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column(length: 255)]
    private ?string $prenom = null;

    #[ORM\Column(length: 255)]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    private ?string $mot_de_passe = null;

    #[ORM\Column(length: 20)]
    private ?string $role = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $date_naissance = null;

    #[ORM\Column(length: 20)]
    private ?string $statut_inscription = null;

    #[ORM\Column]
    private ?bool $consentement_parental = null;

    /**
     * @var Collection<int, Article>
     */
    #[ORM\OneToMany(targetEntity: Article::class, mappedBy: 'auteur_id')]
    private Collection $articles;

    /**
     * @var Collection<int, Article>
     */
    #[ORM\OneToMany(targetEntity: Article::class, mappedBy: 'modificateur_id')]
    private Collection $articlesModifies;

    /**
     * @var Collection<int, Document>
     */
    #[ORM\OneToMany(targetEntity: Document::class, mappedBy: 'ajoute_par_id')]
    private Collection $documentsAjoutes;

    /**
     * @var Collection<int, Emprunt>
     */
    #[ORM\OneToMany(targetEntity: Emprunt::class, mappedBy: 'utilisateur_id')]
    private Collection $emprunts;

    /**
     * @var Collection<int, ArchiveEmprunt>
     */
    #[ORM\OneToMany(targetEntity: ArchiveEmprunt::class, mappedBy: 'utilisateur_id')]
    private Collection $archiveEmprunts;

    public function __construct()
    {
        $this->articles = new ArrayCollection();
        $this->articlesModifies = new ArrayCollection();
        $this->documentsAjoutes = new ArrayCollection();
        $this->emprunts = new ArrayCollection();
        $this->archiveEmprunts = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getMotDePasse(): ?string
    {
        return $this->mot_de_passe;
    }

    public function setMotDePasse(string $mot_de_passe): static
    {
        $this->mot_de_passe = $mot_de_passe;

        return $this;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRole(string $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function getDateNaissance(): ?\DateTime
    {
        return $this->date_naissance;
    }

    public function setDateNaissance(\DateTime $date_naissance): static
    {
        $this->date_naissance = $date_naissance;

        return $this;
    }

    public function getStatutInscription(): ?string
    {
        return $this->statut_inscription;
    }

    public function setStatutInscription(string $statut_inscription): static
    {
        $this->statut_inscription = $statut_inscription;

        return $this;
    }

    public function isConsentementParental(): ?bool
    {
        return $this->consentement_parental;
    }

    public function setConsentementParental(bool $consentement_parental): static
    {
        $this->consentement_parental = $consentement_parental;

        return $this;
    }

    /**
     * @return Collection<int, Article>
     */
    public function getArticles(): Collection
    {
        return $this->articles;
    }

    public function addArticle(Article $article): static
    {
        if (!$this->articles->contains($article)) {
            $this->articles->add($article);
            $article->setAuteurId($this);
        }

        return $this;
    }

    public function removeArticle(Article $article): static
    {
        if ($this->articles->removeElement($article)) {
            // set the owning side to null (unless already changed)
            if ($article->getAuteurId() === $this) {
                $article->setAuteurId(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Article>
     */
    public function getArticlesModifies(): Collection
    {
        return $this->articlesModifies;
    }

    public function addArticlesModifie(Article $articlesModifie): static
    {
        if (!$this->articlesModifies->contains($articlesModifie)) {
            $this->articlesModifies->add($articlesModifie);
            $articlesModifie->setModificateurId($this);
        }

        return $this;
    }

    public function removeArticlesModifie(Article $articlesModifie): static
    {
        if ($this->articlesModifies->removeElement($articlesModifie)) {
            // set the owning side to null (unless already changed)
            if ($articlesModifie->getModificateurId() === $this) {
                $articlesModifie->setModificateurId(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Document>
     */
    public function getDocumentsAjoutes(): Collection
    {
        return $this->documentsAjoutes;
    }

    public function addDocumentsAjoute(Document $documentsAjoute): static
    {
        if (!$this->documentsAjoutes->contains($documentsAjoute)) {
            $this->documentsAjoutes->add($documentsAjoute);
            $documentsAjoute->setAjouteParId($this);
        }

        return $this;
    }

    public function removeDocumentsAjoute(Document $documentsAjoute): static
    {
        if ($this->documentsAjoutes->removeElement($documentsAjoute)) {
            // set the owning side to null (unless already changed)
            if ($documentsAjoute->getAjouteParId() === $this) {
                $documentsAjoute->setAjouteParId(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Emprunt>
     */
    public function getEmprunts(): Collection
    {
        return $this->emprunts;
    }

    public function addEmprunt(Emprunt $emprunt): static
    {
        if (!$this->emprunts->contains($emprunt)) {
            $this->emprunts->add($emprunt);
            $emprunt->setUtilisateurId($this);
        }

        return $this;
    }

    public function removeEmprunt(Emprunt $emprunt): static
    {
        if ($this->emprunts->removeElement($emprunt)) {
            // set the owning side to null (unless already changed)
            if ($emprunt->getUtilisateurId() === $this) {
                $emprunt->setUtilisateurId(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ArchiveEmprunt>
     */
    public function getArchiveEmprunts(): Collection
    {
        return $this->archiveEmprunts;
    }

    public function addArchiveEmprunt(ArchiveEmprunt $archiveEmprunt): static
    {
        if (!$this->archiveEmprunts->contains($archiveEmprunt)) {
            $this->archiveEmprunts->add($archiveEmprunt);
            $archiveEmprunt->setUtilisateurId($this);
        }

        return $this;
    }

    public function removeArchiveEmprunt(ArchiveEmprunt $archiveEmprunt): static
    {
        if ($this->archiveEmprunts->removeElement($archiveEmprunt)) {
            // set the owning side to null (unless already changed)
            if ($archiveEmprunt->getUtilisateurId() === $this) {
                $archiveEmprunt->setUtilisateurId(null);
            }
        }

        return $this;
    }
}
