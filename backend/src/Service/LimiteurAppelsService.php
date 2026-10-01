<?php

namespace App\Service;

use Symfony\Component\RateLimiter\RateLimiterFactory;

// limiteur anti-spam générique, message d'erreur décidé par chaque contrôleur
class LimiteurAppelsService
{
    public function depasse(RateLimiterFactory $limiteur, string $identifiant): bool
    {
        return !$limiteur->create($identifiant)->consume(1)->isAccepted();
    }
}
