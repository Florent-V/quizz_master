<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250919065944 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE category ADD is_active TINYINT(1) DEFAULT 1 NOT NULL');
        $this->addSql('UPDATE category SET is_active = 1');
        $this->addSql('ALTER TABLE question ADD is_active TINYINT(1) DEFAULT 1 NOT NULL');
        $this->addSql('UPDATE question SET is_active = 1');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE category DROP is_active');
        $this->addSql('ALTER TABLE question DROP is_active');
    }
}
