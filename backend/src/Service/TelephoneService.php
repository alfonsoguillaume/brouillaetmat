<?php

namespace App\Service;

// format téléphone: 10 chiffres, commence par 0, espaces tolérés
class TelephoneService
{
    public function estValide(string $telephone): bool
    {
        $nettoye = preg_replace('/\s+/', '', $telephone);

        return (bool) preg_match('/^0\d{9}$/', $nettoye);
    }
}
