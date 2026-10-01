<?php

namespace App\Repository;

use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Utilisateur>
 */
class UtilisateurRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Utilisateur::class);
    }

    /**
     * Membres validés, comptes techniques exclus. Utilisé pour le classement Elo.
     *
     * @return Utilisateur[]
     */
    public function findMembresValides(): array
    {
        return $this->findBy(['statut_inscription' => 'valide', 'compte_technique' => false]);
    }

    /**
     * Adversaires possibles: membres validés, sauf soi-même et comptes techniques.
     *
     * @return Utilisateur[]
     */
    public function findAdversairesDisponibles(int $idUtilisateurConnecte): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.statut_inscription = :valide')
            ->andWhere('u.id != :moi')
            ->andWhere('u.compte_technique = :technique')
            ->setParameter('valide', 'valide')
            ->setParameter('moi', $idUtilisateurConnecte)
            ->setParameter('technique', false)
            ->orderBy('u.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Membres gérés en admin: statut différent de en_attente, comptes techniques exclus.
     *
     * @return Utilisateur[]
     */
    public function findMembresGeres(): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.statut_inscription != :enAttente')
            ->andWhere('u.compte_technique = :technique')
            ->setParameter('enAttente', 'en_attente')
            ->setParameter('technique', false)
            ->getQuery()
            ->getResult();
    }
}
