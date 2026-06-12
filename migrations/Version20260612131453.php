<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260612131453 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE project_members DROP CONSTRAINT fk_252df8f3166d1f9c');
        $this->addSql('ALTER TABLE project_members DROP CONSTRAINT fk_252df8f3a76ed395');
        $this->addSql('DROP TABLE project_members');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE project_members (project_id UUID NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (project_id, user_id))');
        $this->addSql('CREATE INDEX idx_d3bede9aa76ed395 ON project_members (user_id)');
        $this->addSql('CREATE INDEX idx_d3bede9a166d1f9c ON project_members (project_id)');
        $this->addSql('ALTER TABLE project_members ADD CONSTRAINT fk_252df8f3166d1f9c FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE project_members ADD CONSTRAINT fk_252df8f3a76ed395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
