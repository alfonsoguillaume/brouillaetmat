<?php

namespace App\Service;

// politique mot de passe (12 car, maj, min, chiffre, spécial), utilisée partout
class PolitiqueMotDePasseService
{
    /**
     * @return string[] règles non respectées (vide si valide)
     */
    public function erreurs(string $motDePasse): array
    {
        $erreurs = [];

        if (strlen($motDePasse) < 12) {
            $erreurs[] = '12 caractères minimum';
        }
        if (!preg_match('/[A-Z]/', $motDePasse)) {
            $erreurs[] = 'une majuscule';
        }
        if (!preg_match('/[a-z]/', $motDePasse)) {
            $erreurs[] = 'une minuscule';
        }
        if (!preg_match('/\d/', $motDePasse)) {
            $erreurs[] = 'un chiffre';
        }
        if (!preg_match('/[^A-Za-z0-9]/', $motDePasse)) {
            $erreurs[] = 'un caractère spécial';
        }

        return $erreurs;
    }

    public function estValide(string $motDePasse): bool
    {
        return empty($this->erreurs($motDePasse));
    }
}
