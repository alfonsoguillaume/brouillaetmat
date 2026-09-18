<?php

namespace App\Entity;

use App\Repository\PartieRepository;
use Doctrine\DBAL\Types\Types;
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

    // Rempli seulement juste avant suppression (partie terminée), pour que
    // le dernier "GET" du frontend puisse afficher le résultat avant que
    // la ligne ne disparaisse.
    #[ORM\Column(length: 10, nullable: true)]
    private ?string $resultat = null;

    #[ORM\Column]
    private ?\DateTime $date = null;

    #[ORM\Column]
    private ?bool $classee = null;

    // 'en_attente' (invitation envoyée, pas encore acceptée) ou 'en_cours'
    // (partie en train de se jouer). Pas de statut "terminée" : la ligne
    // est supprimée dès que la partie se termine (voir Classement pour la
    // trace durable de l'impact sur l'Elo).
    #[ORM\Column(length: 15)]
    private ?string $statut = null;

    // Position actuelle du plateau au format FEN (notation standard
    // d'échecs) — permet de recharger l'état exact sans rejouer tous les
    // coups depuis le début.
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $fen = null;

    // Liste des coups joués (ex: ["e4", "e5", "Nf3"]), pour l'affichage de
    // l'historique pendant la partie. Types::JSON : Doctrine convertit
    // automatiquement un tableau PHP en JSON pour le stockage, et inversement.
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $coups = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $trait = null;

    #[ORM\Column(nullable: true)]
    private ?int $temps_restant_blanc = null;

    #[ORM\Column(nullable: true)]
    private ?int $temps_restant_noir = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $dernier_coup_le = null;

    // "Battement de cœur" de chaque joueur, pour détecter une déconnexion.
    #[ORM\Column(nullable: true)]
    private ?\DateTime $dernier_signal_blanc = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $dernier_signal_noir = null;

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

    public function setResultat(?string $resultat): static
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

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getFen(): ?string
    {
        return $this->fen;
    }

    public function setFen(?string $fen): static
    {
        $this->fen = $fen;

        return $this;
    }

    public function getCoups(): ?array
    {
        return $this->coups;
    }

    public function setCoups(?array $coups): static
    {
        $this->coups = $coups;

        return $this;
    }

    public function getTrait(): ?string
    {
        return $this->trait;
    }

    public function setTrait(?string $trait): static
    {
        $this->trait = $trait;

        return $this;
    }

    public function getTempsRestantBlanc(): ?int
    {
        return $this->temps_restant_blanc;
    }

    public function setTempsRestantBlanc(?int $temps_restant_blanc): static
    {
        $this->temps_restant_blanc = $temps_restant_blanc;

        return $this;
    }

    public function getTempsRestantNoir(): ?int
    {
        return $this->temps_restant_noir;
    }

    public function setTempsRestantNoir(?int $temps_restant_noir): static
    {
        $this->temps_restant_noir = $temps_restant_noir;

        return $this;
    }

    public function getDernierCoupLe(): ?\DateTime
    {
        return $this->dernier_coup_le;
    }

    public function setDernierCoupLe(?\DateTime $dernier_coup_le): static
    {
        $this->dernier_coup_le = $dernier_coup_le;

        return $this;
    }

    public function getDernierSignalBlanc(): ?\DateTime
    {
        return $this->dernier_signal_blanc;
    }

    public function setDernierSignalBlanc(?\DateTime $dernier_signal_blanc): static
    {
        $this->dernier_signal_blanc = $dernier_signal_blanc;

        return $this;
    }

    public function getDernierSignalNoir(): ?\DateTime
    {
        return $this->dernier_signal_noir;
    }

    public function setDernierSignalNoir(?\DateTime $dernier_signal_noir): static
    {
        $this->dernier_signal_noir = $dernier_signal_noir;

        return $this;
    }
}
