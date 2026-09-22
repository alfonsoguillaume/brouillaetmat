<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260922082454 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE dossier (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, date_creation DATETIME NOT NULL, cree_par_id_id INT NOT NULL, INDEX IDX_3D48E037F16997C7 (cree_par_id_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE dossier ADD CONSTRAINT FK_3D48E037F16997C7 FOREIGN KEY (cree_par_id_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE archive_emprunt ADD CONSTRAINT FK_5B6090287233FE77 FOREIGN KEY (ouvrage_id_id) REFERENCES ouvrage (id)');
        $this->addSql('ALTER TABLE archive_emprunt ADD CONSTRAINT FK_5B609028B981C689 FOREIGN KEY (utilisateur_id_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE article ADD CONSTRAINT FK_23A0E6675F8742E FOREIGN KEY (auteur_id_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE article ADD CONSTRAINT FK_23A0E661E60B01F FOREIGN KEY (modificateur_id_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE classement ADD CONSTRAINT FK_55EE9D6DB981C689 FOREIGN KEY (utilisateur_id_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE document ADD dossier_id_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A76BE4AB750 FOREIGN KEY (ajoute_par_id_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A76B6C48410 FOREIGN KEY (dossier_id_id) REFERENCES dossier (id)');
        $this->addSql('CREATE INDEX IDX_D8698A76B6C48410 ON document (dossier_id_id)');
        $this->addSql('ALTER TABLE emprunt ADD CONSTRAINT FK_364071D77233FE77 FOREIGN KEY (ouvrage_id_id) REFERENCES ouvrage (id)');
        $this->addSql('ALTER TABLE emprunt ADD CONSTRAINT FK_364071D7B981C689 FOREIGN KEY (utilisateur_id_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE participation ADD CONSTRAINT FK_AB55E24FB328FDC FOREIGN KEY (tournois_id_id) REFERENCES tournois (id)');
        $this->addSql('ALTER TABLE participation ADD CONSTRAINT FK_AB55E24FB981C689 FOREIGN KEY (utilisateur_id_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE partie ADD CONSTRAINT FK_59B1F3DBAC58BCD FOREIGN KEY (joueur_blanc_id_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE partie ADD CONSTRAINT FK_59B1F3D3ADD3C66 FOREIGN KEY (joueur_noir_id_id) REFERENCES utilisateur (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE dossier DROP FOREIGN KEY FK_3D48E037F16997C7');
        $this->addSql('DROP TABLE dossier');
        $this->addSql('ALTER TABLE archive_emprunt DROP FOREIGN KEY FK_5B6090287233FE77');
        $this->addSql('ALTER TABLE archive_emprunt DROP FOREIGN KEY FK_5B609028B981C689');
        $this->addSql('ALTER TABLE article DROP FOREIGN KEY FK_23A0E6675F8742E');
        $this->addSql('ALTER TABLE article DROP FOREIGN KEY FK_23A0E661E60B01F');
        $this->addSql('ALTER TABLE classement DROP FOREIGN KEY FK_55EE9D6DB981C689');
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_D8698A76BE4AB750');
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_D8698A76B6C48410');
        $this->addSql('DROP INDEX IDX_D8698A76B6C48410 ON document');
        $this->addSql('ALTER TABLE document DROP dossier_id_id');
        $this->addSql('ALTER TABLE emprunt DROP FOREIGN KEY FK_364071D77233FE77');
        $this->addSql('ALTER TABLE emprunt DROP FOREIGN KEY FK_364071D7B981C689');
        $this->addSql('ALTER TABLE participation DROP FOREIGN KEY FK_AB55E24FB328FDC');
        $this->addSql('ALTER TABLE participation DROP FOREIGN KEY FK_AB55E24FB981C689');
        $this->addSql('ALTER TABLE partie DROP FOREIGN KEY FK_59B1F3DBAC58BCD');
        $this->addSql('ALTER TABLE partie DROP FOREIGN KEY FK_59B1F3D3ADD3C66');
    }
}
