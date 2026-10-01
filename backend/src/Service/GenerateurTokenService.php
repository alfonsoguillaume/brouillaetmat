<?php

namespace App\Service;

// jeton à usage unique (reset mdp, changement email), 32 octets aléatoires
class GenerateurTokenService
{
    public function generer(): string
    {
        return bin2hex(random_bytes(32));
    }
}
