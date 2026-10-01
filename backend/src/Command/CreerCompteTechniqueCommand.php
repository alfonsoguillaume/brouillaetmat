<?php

namespace App\Command;

use App\Entity\Utilisateur;
use App\Service\EmailUniciteService;
use App\Service\PolitiqueMotDePasseService;
use App\Service\PseudoUniciteService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

// Crée un compte "technique" (droits d'un rôle donné, invisible partout,
// voir Utilisateur::$compte_technique). Sert aussi à créer le tout premier
// admin du site (sans ça, personne ne peut valider la première inscription)
//
// usage: php bin/console app:creer-compte-technique admin mon@email.fr
#[AsCommand(
    name: 'app:creer-compte-technique',
    description: 'Crée un compte technique (invisible des membres) pour tester un rôle donné'
)]
class CreerCompteTechniqueCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly PseudoUniciteService $pseudoUniciteService,
        private readonly EmailUniciteService $emailUniciteService,
        private readonly PolitiqueMotDePasseService $politiqueMotDePasseService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('role', InputArgument::REQUIRED, 'Rôle à tester : membre, gestionnaire ou admin')
            ->addArgument('email', InputArgument::REQUIRED, 'Email de connexion du compte technique');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $role = $input->getArgument('role');
        $email = $input->getArgument('email');

        $rolesValides = ['membre', 'gestionnaire', 'admin'];
        if (!in_array($role, $rolesValides, true)) {
            $io->error(sprintf(
                'Rôle invalide : "%s". Valeurs acceptées : %s.',
                $role,
                implode(', ', $rolesValides)
            ));

            return Command::FAILURE;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $io->error('Le format de l\'email est invalide.');

            return Command::FAILURE;
        }

        if (!$this->emailUniciteService->estDisponible($email)) {
            $io->error('Cet email est déjà utilisé par un compte existant.');

            return Command::FAILURE;
        }

        $pseudo = 'technique_' . $role;
        if (!$this->pseudoUniciteService->estDisponible($pseudo)) {
            $io->error(sprintf(
                'Un compte technique existe déjà pour le rôle "%s" (pseudo "%s").',
                $role,
                $pseudo
            ));

            return Command::FAILURE;
        }

        // mot de passe masqué, demandé 2 fois, même politique que le reste du site
        $motDePasse = $io->askHidden('Mot de passe du compte technique (12 caractères minimum, majuscule, minuscule, chiffre, caractère spécial)');
        $confirmation = $io->askHidden('Confirmez le mot de passe');

        if ($motDePasse !== $confirmation) {
            $io->error('Les deux mots de passe ne correspondent pas.');

            return Command::FAILURE;
        }

        $erreursMotDePasse = $this->politiqueMotDePasseService->erreurs((string) $motDePasse);
        if (!empty($erreursMotDePasse)) {
            $io->error('Le mot de passe doit contenir : ' . implode(', ', $erreursMotDePasse) . '.');

            return Command::FAILURE;
        }

        $utilisateur = new Utilisateur();
        $utilisateur->setNom('Compte technique');
        $utilisateur->setPrenom(ucfirst($role));
        $utilisateur->setPseudo($pseudo);
        $utilisateur->setEmail($email);
        $utilisateur->setRole($role);
        // date arbitraire, pas de sens pour un compte technique mais champ obligatoire
        $utilisateur->setDateNaissance(new \DateTime('2000-01-01'));
        $utilisateur->setStatutInscription('valide');
        $utilisateur->setConsentementParental(false);
        $utilisateur->setCompteTechnique(true);
        $utilisateur->setMotDePasse(
            $this->passwordHasher->hashPassword($utilisateur, $motDePasse)
        );

        $this->em->persist($utilisateur);
        $this->em->flush();

        $io->success(sprintf(
            'Compte technique "%s" créé avec le rôle "%s" (invisible des membres du club).',
            $email,
            $role
        ));

        return Command::SUCCESS;
    }
}
