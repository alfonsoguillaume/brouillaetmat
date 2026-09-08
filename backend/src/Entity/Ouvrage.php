<?php

namespace App\Entity;

use App\Repository\OuvrageRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OuvrageRepository::class)]
class Ouvrage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $titre = null;

    #[ORM\Column(length: 255)]
    private ?string $auteur = null;

    /**
     * @var Collection<int, Emprunt>
     */
    #[ORM\OneToMany(targetEntity: Emprunt::class, mappedBy: 'ouvrage_id')]
    private Collection $emprunts;

    /**
     * @var Collection<int, ArchiveEmprunt>
     */
    #[ORM\OneToMany(targetEntity: ArchiveEmprunt::class, mappedBy: 'ouvrage_id')]
    private Collection $archivesEmprunts;

    public function __construct()
    {
        $this->emprunts = new ArrayCollection();
        $this->archivesEmprunts = new ArrayCollection();
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

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = $titre;

        return $this;
    }

    public function getAuteur(): ?string
    {
        return $this->auteur;
    }

    public function setAuteur(string $auteur): static
    {
        $this->auteur = $auteur;

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
            $emprunt->setOuvrageId($this);
        }

        return $this;
    }

    public function removeEmprunt(Emprunt $emprunt): static
    {
        if ($this->emprunts->removeElement($emprunt)) {
            // set the owning side to null (unless already changed)
            if ($emprunt->getOuvrageId() === $this) {
                $emprunt->setOuvrageId(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ArchiveEmprunt>
     */
    public function getArchivesEmprunts(): Collection
    {
        return $this->archivesEmprunts;
    }

    public function addArchivesEmprunt(ArchiveEmprunt $archivesEmprunt): static
    {
        if (!$this->archivesEmprunts->contains($archivesEmprunt)) {
            $this->archivesEmprunts->add($archivesEmprunt);
            $archivesEmprunt->setOuvrageId($this);
        }

        return $this;
    }

    public function removeArchivesEmprunt(ArchiveEmprunt $archivesEmprunt): static
    {
        if ($this->archivesEmprunts->removeElement($archivesEmprunt)) {
            // set the owning side to null (unless already changed)
            if ($archivesEmprunt->getOuvrageId() === $this) {
                $archivesEmprunt->setOuvrageId(null);
            }
        }

        return $this;
    }
}
