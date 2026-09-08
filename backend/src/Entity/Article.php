<?php

namespace App\Entity;

use App\Repository\ArticleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ArticleRepository::class)]
class Article
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $titre = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $contenu = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $photo = null;

    #[ORM\Column]
    private ?\DateTime $date_creation = null;

    #[ORM\ManyToOne(inversedBy: 'articles')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $auteur_id = null;

    #[ORM\ManyToOne(inversedBy: 'articlesModifies')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Utilisateur $modificateur_id = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $date_modification = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = $titre;

        return $this;
    }

    public function getContenu(): ?string
    {
        return $this->contenu;
    }

    public function setContenu(string $contenu): static
    {
        $this->contenu = $contenu;

        return $this;
    }

    public function getPhoto(): ?string
    {
        return $this->photo;
    }

    public function setPhoto(?string $photo): static
    {
        $this->photo = $photo;

        return $this;
    }

    public function getDateCreation(): ?\DateTime
    {
        return $this->date_creation;
    }

    public function setDateCreation(\DateTime $date_creation): static
    {
        $this->date_creation = $date_creation;

        return $this;
    }

    public function getAuteurId(): ?Utilisateur
    {
        return $this->auteur_id;
    }

    public function setAuteurId(?Utilisateur $auteur_id): static
    {
        $this->auteur_id = $auteur_id;

        return $this;
    }

    public function getModificateurId(): ?Utilisateur
    {
        return $this->modificateur_id;
    }

    public function setModificateurId(?Utilisateur $modificateur_id): static
    {
        $this->modificateur_id = $modificateur_id;

        return $this;
    }

    public function getDateModification(): ?\DateTime
    {
        return $this->date_modification;
    }

    public function setDateModification(?\DateTime $date_modification): static
    {
        $this->date_modification = $date_modification;

        return $this;
    }
}
