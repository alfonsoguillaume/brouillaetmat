<?php

namespace App\Service;

use App\Repository\UtilisateurRepository;

// unicité du pseudo, centralisée (inscription, admin, profil, compte technique)
class PseudoUniciteService
{
    public function __construct(
        private readonly UtilisateurRepository $utilisateurRepository,
    ) {
    }

    public function estDisponible(string $pseudo): bool
    {
        return $this->utilisateurRepository->findOneBy(['pseudo' => $pseudo]) === null;
    }
}
