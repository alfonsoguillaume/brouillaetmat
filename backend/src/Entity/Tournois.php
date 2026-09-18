<?php

namespace App\Entity;

use App\Repository\TournoisRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TournoisRepository::class)]
class Tournois
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $date = null;

    // Valeurs possibles : 'planifie', 'en_cours', 'annule'.
    #[ORM\Column(length: 15)]
    private ?string $statut = null;

    // Rempli uniquement si statut = 'annule' — la raison de l'annulation,
    // conservée pour l'historique/archives.
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $motif_annulation = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $date_annulation = null;

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

    public function getDate(): ?\DateTime
    {
        return $this->date;
    }

    public function setDate(\DateTime $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getMotifAnnulation(): ?string
    {
        return $this->motif_annulation;
    }

    public function setMotifAnnulation(?string $motif_annulation): static
    {
        $this->motif_annulation = $motif_annulation;

        return $this;
    }

    public function getDateAnnulation(): ?\DateTime
    {
        return $this->date_annulation;
    }

    public function setDateAnnulation(?\DateTime $date_annulation): static
    {
        $this->date_annulation = $date_annulation;

        return $this;
    }
}
