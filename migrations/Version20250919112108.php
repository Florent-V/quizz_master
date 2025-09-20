<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250919112108 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE category (id INT AUTO_INCREMENT NOT NULL, created_by INT DEFAULT NULL, updated_by INT DEFAULT NULL, tree_root INT DEFAULT NULL, parent_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, icon VARCHAR(255) DEFAULT NULL, is_active TINYINT(1) DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, deleted_at DATETIME DEFAULT NULL, lft INT NOT NULL, rgt INT NOT NULL, lvl INT NOT NULL, image_name VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_64C19C1989D9B62 (slug), INDEX IDX_64C19C1DE12AB56 (created_by), INDEX IDX_64C19C116FE72E1 (updated_by), INDEX IDX_64C19C1A977936C (tree_root), INDEX IDX_64C19C1727ACA70 (parent_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE difficulty (id INT AUTO_INCREMENT NOT NULL, created_by INT DEFAULT NULL, updated_by INT DEFAULT NULL, name VARCHAR(255) NOT NULL, level INT NOT NULL, color VARCHAR(7) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_BB6B6FEF9AEACC13 (level), INDEX IDX_BB6B6FEFDE12AB56 (created_by), INDEX IDX_BB6B6FEF16FE72E1 (updated_by), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE ext_log_entries (id INT AUTO_INCREMENT NOT NULL, action VARCHAR(8) NOT NULL, logged_at DATETIME NOT NULL, object_id VARCHAR(64) DEFAULT NULL, object_class VARCHAR(191) NOT NULL, version INT NOT NULL, data LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:array)\', username VARCHAR(191) DEFAULT NULL, INDEX log_class_lookup_idx (object_class), INDEX log_date_lookup_idx (logged_at), INDEX log_user_lookup_idx (username), INDEX log_version_lookup_idx (object_id, object_class, version), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB ROW_FORMAT = DYNAMIC');
        $this->addSql('CREATE TABLE ext_translations (id INT AUTO_INCREMENT NOT NULL, locale VARCHAR(8) NOT NULL, object_class VARCHAR(191) NOT NULL, field VARCHAR(32) NOT NULL, foreign_key VARCHAR(64) NOT NULL, content LONGTEXT DEFAULT NULL, INDEX translations_lookup_idx (locale, object_class, foreign_key), INDEX general_translations_lookup_idx (object_class, foreign_key), UNIQUE INDEX lookup_unique_idx (locale, object_class, field, foreign_key), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB ROW_FORMAT = DYNAMIC');
        $this->addSql('CREATE TABLE oauth_account (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, provider VARCHAR(255) NOT NULL, provider_id VARCHAR(255) NOT NULL, INDEX IDX_6E30F9D1A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE proposal (id INT AUTO_INCREMENT NOT NULL, question_id INT NOT NULL, created_by INT DEFAULT NULL, updated_by INT DEFAULT NULL, content VARCHAR(255) NOT NULL, is_correct TINYINT(1) NOT NULL, image_name VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, deleted_at DATETIME DEFAULT NULL, INDEX IDX_BFE594721E27F6BF (question_id), INDEX IDX_BFE59472DE12AB56 (created_by), INDEX IDX_BFE5947216FE72E1 (updated_by), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE question (id INT AUTO_INCREMENT NOT NULL, category_id INT NOT NULL, difficulty_id INT NOT NULL, created_by INT DEFAULT NULL, updated_by INT DEFAULT NULL, content LONGTEXT NOT NULL, explanation LONGTEXT DEFAULT NULL, hint LONGTEXT DEFAULT NULL, image_name VARCHAR(255) DEFAULT NULL, is_active TINYINT(1) DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, deleted_at DATETIME DEFAULT NULL, INDEX IDX_B6F7494E12469DE2 (category_id), INDEX IDX_B6F7494EFCFA9DAE (difficulty_id), INDEX IDX_B6F7494EDE12AB56 (created_by), INDEX IDX_B6F7494E16FE72E1 (updated_by), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE quiz_session (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', user_id INT DEFAULT NULL, category_id INT DEFAULT NULL, sub_category_id INT DEFAULT NULL, created_by INT DEFAULT NULL, updated_by INT DEFAULT NULL, score INT NOT NULL, started_at DATETIME NOT NULL, finished_at DATETIME DEFAULT NULL, status VARCHAR(255) DEFAULT NULL, pseudo VARCHAR(255) NOT NULL, game_mode VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, deleted_at DATETIME DEFAULT NULL, INDEX IDX_C21E7874A76ED395 (user_id), INDEX IDX_C21E787412469DE2 (category_id), INDEX IDX_C21E7874F7BFE87C (sub_category_id), INDEX IDX_C21E7874DE12AB56 (created_by), INDEX IDX_C21E787416FE72E1 (updated_by), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE quiz_session_difficulty (quiz_session_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', difficulty_id INT NOT NULL, INDEX IDX_2FC4A2232850CBE3 (quiz_session_id), INDEX IDX_2FC4A223FCFA9DAE (difficulty_id), PRIMARY KEY(quiz_session_id, difficulty_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE quiz_session_answer (id INT AUTO_INCREMENT NOT NULL, quiz_session_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', question_id INT NOT NULL, proposal_id INT DEFAULT NULL, created_by INT DEFAULT NULL, updated_by INT DEFAULT NULL, is_correct TINYINT(1) DEFAULT NULL, time INT DEFAULT NULL, asked_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', answered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, deleted_at DATETIME DEFAULT NULL, INDEX IDX_DF2CD95C2850CBE3 (quiz_session_id), INDEX IDX_DF2CD95C1E27F6BF (question_id), INDEX IDX_DF2CD95CF4792058 (proposal_id), INDEX IDX_DF2CD95CDE12AB56 (created_by), INDEX IDX_DF2CD95C16FE72E1 (updated_by), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE reset_password_request (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, selector VARCHAR(20) NOT NULL, hashed_token VARCHAR(100) NOT NULL, requested_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', expires_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_7CE748AA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, first_name VARCHAR(255) DEFAULT NULL, last_name VARCHAR(255) DEFAULT NULL, user_name VARCHAR(255) NOT NULL, is_verified TINYINT(1) NOT NULL, phone VARCHAR(255) DEFAULT NULL, picture VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', available_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE category ADD CONSTRAINT FK_64C19C1DE12AB56 FOREIGN KEY (created_by) REFERENCES user (id)');
        $this->addSql('ALTER TABLE category ADD CONSTRAINT FK_64C19C116FE72E1 FOREIGN KEY (updated_by) REFERENCES user (id)');
        $this->addSql('ALTER TABLE category ADD CONSTRAINT FK_64C19C1A977936C FOREIGN KEY (tree_root) REFERENCES category (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE category ADD CONSTRAINT FK_64C19C1727ACA70 FOREIGN KEY (parent_id) REFERENCES category (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE difficulty ADD CONSTRAINT FK_BB6B6FEFDE12AB56 FOREIGN KEY (created_by) REFERENCES user (id)');
        $this->addSql('ALTER TABLE difficulty ADD CONSTRAINT FK_BB6B6FEF16FE72E1 FOREIGN KEY (updated_by) REFERENCES user (id)');
        $this->addSql('ALTER TABLE oauth_account ADD CONSTRAINT FK_6E30F9D1A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE proposal ADD CONSTRAINT FK_BFE594721E27F6BF FOREIGN KEY (question_id) REFERENCES question (id)');
        $this->addSql('ALTER TABLE proposal ADD CONSTRAINT FK_BFE59472DE12AB56 FOREIGN KEY (created_by) REFERENCES user (id)');
        $this->addSql('ALTER TABLE proposal ADD CONSTRAINT FK_BFE5947216FE72E1 FOREIGN KEY (updated_by) REFERENCES user (id)');
        $this->addSql('ALTER TABLE question ADD CONSTRAINT FK_B6F7494E12469DE2 FOREIGN KEY (category_id) REFERENCES category (id)');
        $this->addSql('ALTER TABLE question ADD CONSTRAINT FK_B6F7494EFCFA9DAE FOREIGN KEY (difficulty_id) REFERENCES difficulty (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE question ADD CONSTRAINT FK_B6F7494EDE12AB56 FOREIGN KEY (created_by) REFERENCES user (id)');
        $this->addSql('ALTER TABLE question ADD CONSTRAINT FK_B6F7494E16FE72E1 FOREIGN KEY (updated_by) REFERENCES user (id)');
        $this->addSql('ALTER TABLE quiz_session ADD CONSTRAINT FK_C21E7874A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE quiz_session ADD CONSTRAINT FK_C21E787412469DE2 FOREIGN KEY (category_id) REFERENCES category (id)');
        $this->addSql('ALTER TABLE quiz_session ADD CONSTRAINT FK_C21E7874F7BFE87C FOREIGN KEY (sub_category_id) REFERENCES category (id)');
        $this->addSql('ALTER TABLE quiz_session ADD CONSTRAINT FK_C21E7874DE12AB56 FOREIGN KEY (created_by) REFERENCES user (id)');
        $this->addSql('ALTER TABLE quiz_session ADD CONSTRAINT FK_C21E787416FE72E1 FOREIGN KEY (updated_by) REFERENCES user (id)');
        $this->addSql('ALTER TABLE quiz_session_difficulty ADD CONSTRAINT FK_2FC4A2232850CBE3 FOREIGN KEY (quiz_session_id) REFERENCES quiz_session (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE quiz_session_difficulty ADD CONSTRAINT FK_2FC4A223FCFA9DAE FOREIGN KEY (difficulty_id) REFERENCES difficulty (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE quiz_session_answer ADD CONSTRAINT FK_DF2CD95C2850CBE3 FOREIGN KEY (quiz_session_id) REFERENCES quiz_session (id)');
        $this->addSql('ALTER TABLE quiz_session_answer ADD CONSTRAINT FK_DF2CD95C1E27F6BF FOREIGN KEY (question_id) REFERENCES question (id)');
        $this->addSql('ALTER TABLE quiz_session_answer ADD CONSTRAINT FK_DF2CD95CF4792058 FOREIGN KEY (proposal_id) REFERENCES proposal (id)');
        $this->addSql('ALTER TABLE quiz_session_answer ADD CONSTRAINT FK_DF2CD95CDE12AB56 FOREIGN KEY (created_by) REFERENCES user (id)');
        $this->addSql('ALTER TABLE quiz_session_answer ADD CONSTRAINT FK_DF2CD95C16FE72E1 FOREIGN KEY (updated_by) REFERENCES user (id)');
        $this->addSql('ALTER TABLE reset_password_request ADD CONSTRAINT FK_7CE748AA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE category DROP FOREIGN KEY FK_64C19C1DE12AB56');
        $this->addSql('ALTER TABLE category DROP FOREIGN KEY FK_64C19C116FE72E1');
        $this->addSql('ALTER TABLE category DROP FOREIGN KEY FK_64C19C1A977936C');
        $this->addSql('ALTER TABLE category DROP FOREIGN KEY FK_64C19C1727ACA70');
        $this->addSql('ALTER TABLE difficulty DROP FOREIGN KEY FK_BB6B6FEFDE12AB56');
        $this->addSql('ALTER TABLE difficulty DROP FOREIGN KEY FK_BB6B6FEF16FE72E1');
        $this->addSql('ALTER TABLE oauth_account DROP FOREIGN KEY FK_6E30F9D1A76ED395');
        $this->addSql('ALTER TABLE proposal DROP FOREIGN KEY FK_BFE594721E27F6BF');
        $this->addSql('ALTER TABLE proposal DROP FOREIGN KEY FK_BFE59472DE12AB56');
        $this->addSql('ALTER TABLE proposal DROP FOREIGN KEY FK_BFE5947216FE72E1');
        $this->addSql('ALTER TABLE question DROP FOREIGN KEY FK_B6F7494E12469DE2');
        $this->addSql('ALTER TABLE question DROP FOREIGN KEY FK_B6F7494EFCFA9DAE');
        $this->addSql('ALTER TABLE question DROP FOREIGN KEY FK_B6F7494EDE12AB56');
        $this->addSql('ALTER TABLE question DROP FOREIGN KEY FK_B6F7494E16FE72E1');
        $this->addSql('ALTER TABLE quiz_session DROP FOREIGN KEY FK_C21E7874A76ED395');
        $this->addSql('ALTER TABLE quiz_session DROP FOREIGN KEY FK_C21E787412469DE2');
        $this->addSql('ALTER TABLE quiz_session DROP FOREIGN KEY FK_C21E7874F7BFE87C');
        $this->addSql('ALTER TABLE quiz_session DROP FOREIGN KEY FK_C21E7874DE12AB56');
        $this->addSql('ALTER TABLE quiz_session DROP FOREIGN KEY FK_C21E787416FE72E1');
        $this->addSql('ALTER TABLE quiz_session_difficulty DROP FOREIGN KEY FK_2FC4A2232850CBE3');
        $this->addSql('ALTER TABLE quiz_session_difficulty DROP FOREIGN KEY FK_2FC4A223FCFA9DAE');
        $this->addSql('ALTER TABLE quiz_session_answer DROP FOREIGN KEY FK_DF2CD95C2850CBE3');
        $this->addSql('ALTER TABLE quiz_session_answer DROP FOREIGN KEY FK_DF2CD95C1E27F6BF');
        $this->addSql('ALTER TABLE quiz_session_answer DROP FOREIGN KEY FK_DF2CD95CF4792058');
        $this->addSql('ALTER TABLE quiz_session_answer DROP FOREIGN KEY FK_DF2CD95CDE12AB56');
        $this->addSql('ALTER TABLE quiz_session_answer DROP FOREIGN KEY FK_DF2CD95C16FE72E1');
        $this->addSql('ALTER TABLE reset_password_request DROP FOREIGN KEY FK_7CE748AA76ED395');
        $this->addSql('DROP TABLE category');
        $this->addSql('DROP TABLE difficulty');
        $this->addSql('DROP TABLE ext_log_entries');
        $this->addSql('DROP TABLE ext_translations');
        $this->addSql('DROP TABLE oauth_account');
        $this->addSql('DROP TABLE proposal');
        $this->addSql('DROP TABLE question');
        $this->addSql('DROP TABLE quiz_session');
        $this->addSql('DROP TABLE quiz_session_difficulty');
        $this->addSql('DROP TABLE quiz_session_answer');
        $this->addSql('DROP TABLE reset_password_request');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
