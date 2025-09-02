<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250901162640 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE quiz_session_difficulty (quiz_session_id INT NOT NULL, difficulty_id INT NOT NULL, INDEX IDX_2FC4A2232850CBE3 (quiz_session_id), INDEX IDX_2FC4A223FCFA9DAE (difficulty_id), PRIMARY KEY(quiz_session_id, difficulty_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE quiz_session_difficulty ADD CONSTRAINT FK_2FC4A2232850CBE3 FOREIGN KEY (quiz_session_id) REFERENCES quiz_session (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE quiz_session_difficulty ADD CONSTRAINT FK_2FC4A223FCFA9DAE FOREIGN KEY (difficulty_id) REFERENCES difficulty (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE quiz_session ADD category_id INT DEFAULT NULL, ADD sub_category_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE quiz_session ADD CONSTRAINT FK_C21E787412469DE2 FOREIGN KEY (category_id) REFERENCES category (id)');
        $this->addSql('ALTER TABLE quiz_session ADD CONSTRAINT FK_C21E7874F7BFE87C FOREIGN KEY (sub_category_id) REFERENCES category (id)');
        $this->addSql('CREATE INDEX IDX_C21E787412469DE2 ON quiz_session (category_id)');
        $this->addSql('CREATE INDEX IDX_C21E7874F7BFE87C ON quiz_session (sub_category_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE quiz_session_difficulty DROP FOREIGN KEY FK_2FC4A2232850CBE3');
        $this->addSql('ALTER TABLE quiz_session_difficulty DROP FOREIGN KEY FK_2FC4A223FCFA9DAE');
        $this->addSql('DROP TABLE quiz_session_difficulty');
        $this->addSql('ALTER TABLE quiz_session DROP FOREIGN KEY FK_C21E787412469DE2');
        $this->addSql('ALTER TABLE quiz_session DROP FOREIGN KEY FK_C21E7874F7BFE87C');
        $this->addSql('DROP INDEX IDX_C21E787412469DE2 ON quiz_session');
        $this->addSql('DROP INDEX IDX_C21E7874F7BFE87C ON quiz_session');
        $this->addSql('ALTER TABLE quiz_session DROP category_id, DROP sub_category_id');
    }
}
