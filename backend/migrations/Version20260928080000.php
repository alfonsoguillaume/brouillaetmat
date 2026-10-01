<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute le champ compte_technique sur utilisateur : marque les comptes
 * techniques créés par le développeur/mainteneur (via la commande
 * app:creer-compte-technique) pour qu'ils restent invisibles des listes
 * tournées vers les membres du club et du tableau de gestion des membres.
 */
final class Version20260928080000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute utilisateur.compte_technique (comptes techniques invisibles du mainteneur)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE utilisateur ADD compte_technique TINYINT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE utilisateur DROP compte_technique');
    }
}
