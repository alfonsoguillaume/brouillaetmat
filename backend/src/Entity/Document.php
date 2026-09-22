<?php

namespace App\Entity;

use App\Repository\DocumentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DocumentRepository::class)]
class Document
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column(length: 255)]
    private ?string $fichier = null;

    #[ORM\Column]
    private ?\DateTime $date_ajout = null;

    #[ORM\ManyToOne(inversedBy: 'documentsAjoutes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $ajoute_par_id = null;

    // null = document "à la racine", pas rangé dans un dossier.
    #[ORM\ManyToOne(inversedBy: 'documents')]
    private ?Dossier $dossier_id = null;

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

    public function getFichier(): ?string
    {
        return $this->fichier;
    }

    public function setFichier(string $fichier): static
    {
        $this->fichier = $fichier;

        return $this;
    }

    public function getDateAjout(): ?\DateTime
    {
        return $this->date_ajout;
    }

    public function setDateAjout(\DateTime $date_ajout): static
    {
        $this->date_ajout = $date_ajout;

        return $this;
    }

    public function getAjouteParId(): ?Utilisateur
    {
        return $this->ajoute_par_id;
    }

    public function setAjouteParId(?Utilisateur $ajoute_par_id): static
    {
        $this->ajoute_par_id = $ajoute_par_id;

        return $this;
    }

    public function getDossierId(): ?Dossier
    {
        return $this->dossier_id;
    }

    public function setDossierId(?Dossier $dossier_id): static
    {
        $this->dossier_id = $dossier_id;

        return $this;
    }
}
