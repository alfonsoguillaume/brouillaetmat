<?php

namespace App\Service;

use App\Repository\UtilisateurRepository;

// unicité de l'email, même principe que PseudoUniciteService
class EmailUniciteService
{
    public function __construct(
        private readonly UtilisateurRepository $utilisateurRepository,
    ) {
    }

    public function estDisponible(string $email): bool
    {
        return $this->utilisateurRepository->findOneBy(['email' => $email]) === null;
    }
}
