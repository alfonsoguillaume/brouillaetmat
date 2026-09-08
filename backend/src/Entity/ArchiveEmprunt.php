<?php

namespace App\Entity;

use App\Repository\ArchiveEmpruntRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ArchiveEmpruntRepository::class)]
class ArchiveEmprunt
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'archivesEmprunts')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Ouvrage $ouvrage_id = null;

    #[ORM\ManyToOne(inversedBy: 'archiveEmprunts')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $utilisateur_id = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $date_emprunt = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $date_retour = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getOuvrageId(): ?Ouvrage
    {
        return $this->ouvrage_id;
    }

    public function setOuvrageId(?Ouvrage $ouvrage_id): static
    {
        $this->ouvrage_id = $ouvrage_id;

        return $this;
    }

    public function getUtilisateurId(): ?Utilisateur
    {
        return $this->utilisateur_id;
    }

    public function setUtilisateurId(?Utilisateur $utilisateur_id): static
    {
        $this->utilisateur_id = $utilisateur_id;

        return $this;
    }

    public function getDateEmprunt(): ?\DateTime
    {
        return $this->date_emprunt;
    }

    public function setDateEmprunt(\DateTime $date_emprunt): static
    {
        $this->date_emprunt = $date_emprunt;

        return $this;
    }

    public function getDateRetour(): ?\DateTime
    {
        return $this->date_retour;
    }

    public function setDateRetour(?\DateTime $date_retour): static
    {
        $this->date_retour = $date_retour;

        return $this;
    }
}
