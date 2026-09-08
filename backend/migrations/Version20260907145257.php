<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260907145257 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE archive_emprunt (id INT AUTO_INCREMENT NOT NULL, date_emprunt DATE NOT NULL, date_retour DATE DEFAULT NULL, ouvrage_id_id INT NOT NULL, utilisateur_id_id INT NOT NULL, INDEX IDX_5B6090287233FE77 (ouvrage_id_id), INDEX IDX_5B609028B981C689 (utilisateur_id_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE article (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(255) NOT NULL, contenu LONGTEXT NOT NULL, photo VARCHAR(255) DEFAULT NULL, date DATETIME NOT NULL, auteur_id_id INT NOT NULL, INDEX IDX_23A0E6675F8742E (auteur_id_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE classement (id INT AUTO_INCREMENT NOT NULL, valeur_elo INT NOT NULL, date_maj DATETIME NOT NULL, utilisateur_id_id INT NOT NULL, UNIQUE INDEX UNIQ_55EE9D6DB981C689 (utilisateur_id_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE emprunt (id INT AUTO_INCREMENT NOT NULL, date_emprunt DATE NOT NULL, ouvrage_id_id INT NOT NULL, utilisateur_id_id INT NOT NULL, INDEX IDX_364071D77233FE77 (ouvrage_id_id), INDEX IDX_364071D7B981C689 (utilisateur_id_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE ouvrage (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(255) NOT NULL, auteur VARCHAR(255) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE participation (id INT AUTO_INCREMENT NOT NULL, resultat VARCHAR(255) NOT NULL, tournois_id_id INT NOT NULL, utilisateur_id_id INT NOT NULL, INDEX IDX_AB55E24FB328FDC (tournois_id_id), INDEX IDX_AB55E24FB981C689 (utilisateur_id_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE partie (id INT AUTO_INCREMENT NOT NULL, resultat VARCHAR(10) NOT NULL, date DATETIME NOT NULL, classeee TINYINT NOT NULL, joueur_blanc_id_id INT NOT NULL, joueur_noir_id_id INT NOT NULL, INDEX IDX_59B1F3DBAC58BCD (joueur_blanc_id_id), INDEX IDX_59B1F3D3ADD3C66 (joueur_noir_id_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE tournois (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, date DATE NOT NULL, statut VARCHAR(15) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE utilisateur (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, prenom VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, mot_de_passe VARCHAR(255) NOT NULL, role VARCHAR(20) NOT NULL, date_naissance DATE NOT NULL, statut_inscription VARCHAR(20) NOT NULL, consentement_parental TINYINT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE archive_emprunt ADD CONSTRAINT FK_5B6090287233FE77 FOREIGN KEY (ouvrage_id_id) REFERENCES ouvrage (id)');
        $this->addSql('ALTER TABLE archive_emprunt ADD CONSTRAINT FK_5B609028B981C689 FOREIGN KEY (utilisateur_id_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE article ADD CONSTRAINT FK_23A0E6675F8742E FOREIGN KEY (auteur_id_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE classement ADD CONSTRAINT FK_55EE9D6DB981C689 FOREIGN KEY (utilisateur_id_id) REFERENCES utilisateur (id)');
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
        $this->addSql('ALTER TABLE archive_emprunt DROP FOREIGN KEY FK_5B6090287233FE77');
        $this->addSql('ALTER TABLE archive_emprunt DROP FOREIGN KEY FK_5B609028B981C689');
        $this->addSql('ALTER TABLE article DROP FOREIGN KEY FK_23A0E6675F8742E');
        $this->addSql('ALTER TABLE classement DROP FOREIGN KEY FK_55EE9D6DB981C689');
        $this->addSql('ALTER TABLE emprunt DROP FOREIGN KEY FK_364071D77233FE77');
        $this->addSql('ALTER TABLE emprunt DROP FOREIGN KEY FK_364071D7B981C689');
        $this->addSql('ALTER TABLE participation DROP FOREIGN KEY FK_AB55E24FB328FDC');
        $this->addSql('ALTER TABLE participation DROP FOREIGN KEY FK_AB55E24FB981C689');
        $this->addSql('ALTER TABLE partie DROP FOREIGN KEY FK_59B1F3DBAC58BCD');
        $this->addSql('ALTER TABLE partie DROP FOREIGN KEY FK_59B1F3D3ADD3C66');
        $this->addSql('DROP TABLE archive_emprunt');
        $this->addSql('DROP TABLE article');
        $this->addSql('DROP TABLE classement');
        $this->addSql('DROP TABLE emprunt');
        $this->addSql('DROP TABLE ouvrage');
        $this->addSql('DROP TABLE participation');
        $this->addSql('DROP TABLE partie');
        $this->addSql('DROP TABLE tournois');
        $this->addSql('DROP TABLE utilisateur');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
