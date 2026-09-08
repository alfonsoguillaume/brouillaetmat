<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260907183552 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE document (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, fichier VARCHAR(255) NOT NULL, date_ajout DATETIME NOT NULL, ajoute_par_id_id INT NOT NULL, INDEX IDX_D8698A76BE4AB750 (ajoute_par_id_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A76BE4AB750 FOREIGN KEY (ajoute_par_id_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE archive_emprunt ADD CONSTRAINT FK_5B6090287233FE77 FOREIGN KEY (ouvrage_id_id) REFERENCES ouvrage (id)');
        $this->addSql('ALTER TABLE archive_emprunt ADD CONSTRAINT FK_5B609028B981C689 FOREIGN KEY (utilisateur_id_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE article ADD date_modification DATETIME DEFAULT NULL, ADD modificateur_id_id INT DEFAULT NULL, CHANGE date date_creation DATETIME NOT NULL');
        $this->addSql('ALTER TABLE article ADD CONSTRAINT FK_23A0E6675F8742E FOREIGN KEY (auteur_id_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE article ADD CONSTRAINT FK_23A0E661E60B01F FOREIGN KEY (modificateur_id_id) REFERENCES utilisateur (id)');
        $this->addSql('CREATE INDEX IDX_23A0E661E60B01F ON article (modificateur_id_id)');
        $this->addSql('ALTER TABLE classement ADD CONSTRAINT FK_55EE9D6DB981C689 FOREIGN KEY (utilisateur_id_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE emprunt ADD CONSTRAINT FK_364071D77233FE77 FOREIGN KEY (ouvrage_id_id) REFERENCES ouvrage (id)');
        $this->addSql('ALTER TABLE emprunt ADD CONSTRAINT FK_364071D7B981C689 FOREIGN KEY (utilisateur_id_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE participation ADD CONSTRAINT FK_AB55E24FB328FDC FOREIGN KEY (tournois_id_id) REFERENCES tournois (id)');
        $this->addSql('ALTER TABLE participation ADD CONSTRAINT FK_AB55E24FB981C689 FOREIGN KEY (utilisateur_id_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE partie CHANGE classeee classee TINYINT NOT NULL');
        $this->addSql('ALTER TABLE partie ADD CONSTRAINT FK_59B1F3DBAC58BCD FOREIGN KEY (joueur_blanc_id_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE partie ADD CONSTRAINT FK_59B1F3D3ADD3C66 FOREIGN KEY (joueur_noir_id_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE tournois ADD format VARCHAR(30) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_D8698A76BE4AB750');
        $this->addSql('DROP TABLE document');
        $this->addSql('ALTER TABLE archive_emprunt DROP FOREIGN KEY FK_5B6090287233FE77');
        $this->addSql('ALTER TABLE archive_emprunt DROP FOREIGN KEY FK_5B609028B981C689');
        $this->addSql('ALTER TABLE article DROP FOREIGN KEY FK_23A0E6675F8742E');
        $this->addSql('ALTER TABLE article DROP FOREIGN KEY FK_23A0E661E60B01F');
        $this->addSql('DROP INDEX IDX_23A0E661E60B01F ON article');
        $this->addSql('ALTER TABLE article DROP date_modification, DROP modificateur_id_id, CHANGE date_creation date DATETIME NOT NULL');
        $this->addSql('ALTER TABLE classement DROP FOREIGN KEY FK_55EE9D6DB981C689');
        $this->addSql('ALTER TABLE emprunt DROP FOREIGN KEY FK_364071D77233FE77');
        $this->addSql('ALTER TABLE emprunt DROP FOREIGN KEY FK_364071D7B981C689');
        $this->addSql('ALTER TABLE participation DROP FOREIGN KEY FK_AB55E24FB328FDC');
        $this->addSql('ALTER TABLE participation DROP FOREIGN KEY FK_AB55E24FB981C689');
        $this->addSql('ALTER TABLE partie DROP FOREIGN KEY FK_59B1F3DBAC58BCD');
        $this->addSql('ALTER TABLE partie DROP FOREIGN KEY FK_59B1F3D3ADD3C66');
        $this->addSql('ALTER TABLE partie CHANGE classee classeee TINYINT NOT NULL');
        $this->addSql('ALTER TABLE tournois DROP format');
    }
}
