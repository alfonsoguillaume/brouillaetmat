<?php

namespace App\Entity;

use App\Repository\ClassementRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClassementRepository::class)]
class Classement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $utilisateur_id = null;

    #[ORM\Column]
    private ?int $valeur_elo = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $date_maj = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getUtilisateurId(): ?Utilisateur
    {
        return $this->utilisateur_id;
    }

    public function setUtilisateurId(Utilisateur $utilisateur_id): static
    {
        $this->utilisateur_id = $utilisateur_id;

        return $this;
    }

    public function getValeurElo(): ?int
    {
        return $this->valeur_elo;
    }

    public function setValeurElo(int $valeur_elo): static
    {
        $this->valeur_elo = $valeur_elo;

        return $this;
    }

    public function getDateMaj(): ?\DateTimeInterface
    {
        return $this->date_maj;
    }

    public function setDateMaj(?\DateTimeInterface $date_maj): static    {
        $this->date_maj = $date_maj;

        return $this;
    }
}
