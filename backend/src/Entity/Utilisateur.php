<?php

namespace App\Entity;

use App\Repository\UtilisateurRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

#[ORM\Entity(repositoryClass: UtilisateurRepository::class)]
class Utilisateur implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column(length: 255)]
    private ?string $prenom = null;

    #[ORM\Column(length: 191, unique: true)]
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

    #[ORM\Column(length: 191, unique: true)]
    private ?string $pseudo = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email_tuteur = null;

    // Note interne visible et modifiable uniquement par les admins (pas
    // les gestionnaires) — jamais exposée sur les routes accessibles au
    // membre lui-même (/api/profil) ni aux gestionnaires.
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $commentaire = null;

    // Changement d'email en attente de confirmation — l'email "officiel"
    // (colonne $email) ne change qu'une fois le lien de confirmation
    // cliqué, jamais avant.
    #[ORM\Column(length: 191, nullable: true)]
    private ?string $nouvel_email_en_attente = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $token_changement_email = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $token_changement_email_expire_le = null;

    // Réinitialisation de mot de passe ("mot de passe oublié") — même
    // principe que le changement d'email : un jeton temporaire, jamais
    // le mot de passe lui-même, envoyé par email.
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $token_reinitialisation = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $token_reinitialisation_expire_le = null;

    // Mis à jour à chaque signal de présence envoyé par le navigateur
    // (voir Header côté Angular) — sert à déterminer qui est "en ligne"
    // (dernière activité récente) sans infrastructure temps réel dédiée.
    #[ORM\Column(nullable: true)]
    private ?\DateTime $derniere_activite = null;

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

    public function getPseudo(): ?string
    {
        return $this->pseudo;
    }

    public function setPseudo(string $pseudo): static
    {
        $this->pseudo = $pseudo;

        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): static
    {
        $this->telephone = $telephone;

        return $this;
    }

    public function getEmailTuteur(): ?string
    {
        return $this->email_tuteur;
    }

    public function setEmailTuteur(?string $email_tuteur): static
    {
        $this->email_tuteur = $email_tuteur;

        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): static
    {
        $this->commentaire = $commentaire;

        return $this;
    }

    public function getNouvelEmailEnAttente(): ?string
    {
        return $this->nouvel_email_en_attente;
    }

    public function setNouvelEmailEnAttente(?string $nouvel_email_en_attente): static
    {
        $this->nouvel_email_en_attente = $nouvel_email_en_attente;

        return $this;
    }

    public function getTokenChangementEmail(): ?string
    {
        return $this->token_changement_email;
    }

    public function setTokenChangementEmail(?string $token_changement_email): static
    {
        $this->token_changement_email = $token_changement_email;

        return $this;
    }

    public function getTokenChangementEmailExpireLe(): ?\DateTime
    {
        return $this->token_changement_email_expire_le;
    }

    public function setTokenChangementEmailExpireLe(?\DateTime $token_changement_email_expire_le): static
    {
        $this->token_changement_email_expire_le = $token_changement_email_expire_le;

        return $this;
    }

    public function getTokenReinitialisation(): ?string
    {
        return $this->token_reinitialisation;
    }

    public function setTokenReinitialisation(?string $token_reinitialisation): static
    {
        $this->token_reinitialisation = $token_reinitialisation;

        return $this;
    }

    public function getTokenReinitialisationExpireLe(): ?\DateTime
    {
        return $this->token_reinitialisation_expire_le;
    }

    public function setTokenReinitialisationExpireLe(?\DateTime $token_reinitialisation_expire_le): static
    {
        $this->token_reinitialisation_expire_le = $token_reinitialisation_expire_le;

        return $this;
    }

    public function getDerniereActivite(): ?\DateTime
    {
        return $this->derniere_activite;
    }

    public function setDerniereActivite(?\DateTime $derniere_activite): static
    {
        $this->derniere_activite = $derniere_activite;

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

    /**
    Email sert à retrouver le bon login
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     *Noms des roles
     */
    public function getRoles(): array
    {
        return match ($this->role) {
            'admin' => ['ROLE_ADMIN'],
            'gestionnaire' => ['ROLE_GESTIONNAIRE'],
            default => ['ROLE_USER'],
        };
    }

    /**
    Rècupèration et comparation du mot de passe (hashé) lors du login
     */
    public function getPassword(): ?string
    {
        return $this->mot_de_passe;
    }

    /**
     * Obligatoire meme si on ne stocke pas le mot de passe en clair. Laissez vide
     */
    public function eraseCredentials(): void
    {
    }
}
