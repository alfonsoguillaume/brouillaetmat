<?php

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

// envoi des emails du site, expéditeur fixe (Brevo rejette sinon)
// html optionnel, certains mails sont texte seulement
class NotificationEmailService
{
    private const EXPEDITEUR = 'brouillaetmat@gmail.com';

    public function __construct(
        private readonly MailerInterface $mailer,
    ) {
    }

    public function envoyer(string $destinataire, string $sujet, string $texte, ?string $html = null): void
    {
        $email = (new Email())
            ->from(self::EXPEDITEUR)
            ->to($destinataire)
            ->subject($sujet)
            ->text($texte);

        if ($html !== null) {
            $email->html($html);
        }

        $this->mailer->send($email);
    }
}
