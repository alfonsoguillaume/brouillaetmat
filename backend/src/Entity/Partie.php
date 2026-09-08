<?php

namespace App\Entity;

use App\Repository\PartieRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PartieRepository::class)]
class Partie
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $joueur_blanc_id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $joueur_noir_id = null;

    #[ORM\Column(length: 10)]
    private ?string $resultat = null;

    #[ORM\Column]
    private ?\DateTime $date = null;

    #[ORM\Column]
    private ?bool $classee = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getJoueurBlancId(): ?Utilisateur
    {
        return $this->joueur_blanc_id;
    }

    public function setJoueurBlancId(?Utilisateur $joueur_blanc_id): static
    {
        $this->joueur_blanc_id = $joueur_blanc_id;

        return $this;
    }

    public function getJoueurNoirId(): ?Utilisateur
    {
        return $this->joueur_noir_id;
    }

    public function setJoueurNoirId(?Utilisateur $joueur_noir_id): static
    {
        $this->joueur_noir_id = $joueur_noir_id;

        return $this;
    }

    public function getResultat(): ?string
    {
        return $this->resultat;
    }

    public function setResultat(string $resultat): static
    {
        $this->resultat = $resultat;

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

    public function isClassee(): ?bool
    {
        return $this->classee;
    }

    public function setClassee(bool $classee): static
    {
        $this->classee = $classee;

        return $this;
    }
}
