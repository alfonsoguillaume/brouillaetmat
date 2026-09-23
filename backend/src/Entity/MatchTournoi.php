<?php


namespace App\Entity;

use App\Repository\MatchTournoiRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MatchTournoiRepository::class)]
class MatchTournoi
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Tournois $tournois_id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $joueur1_id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $joueur2_id = null;

    // 'joueur1', 'joueur2' ou 'nul'.
    #[ORM\Column(length: 10)]
    private ?string $resultat = null;

    #[ORM\Column]
    private ?\DateTime $date_saisie = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTournoisId(): ?Tournois
    {
        return $this->tournois_id;
    }

    public function setTournoisId(?Tournois $tournois_id): static
    {
        $this->tournois_id = $tournois_id;

        return $this;
    }

    public function getJoueur1Id(): ?Utilisateur
    {
        return $this->joueur1_id;
    }

    public function setJoueur1Id(?Utilisateur $joueur1_id): static
    {
        $this->joueur1_id = $joueur1_id;

        return $this;
    }

    public function getJoueur2Id(): ?Utilisateur
    {
        return $this->joueur2_id;
    }

    public function setJoueur2Id(?Utilisateur $joueur2_id): static
    {
        $this->joueur2_id = $joueur2_id;

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

    public function getDateSaisie(): ?\DateTime
    {
        return $this->date_saisie;
    }

    public function setDateSaisie(\DateTime $date_saisie): static
    {
        $this->date_saisie = $date_saisie;

        return $this;
    }
}
