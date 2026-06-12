<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260612130856 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE project_invitations (email VARCHAR(255) NOT NULL, token VARCHAR(64) NOT NULL, status VARCHAR(20) NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, id UUID NOT NULL, project_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_EBE8E755166D1F9C ON project_invitations (project_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_project_invitations_token ON project_invitations (token)');
        $this->addSql('CREATE TABLE project_guests (project_id UUID NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (project_id, user_id))');
        $this->addSql('CREATE INDEX IDX_5AB557F8166D1F9C ON project_guests (project_id)');
        $this->addSql('CREATE INDEX IDX_5AB557F8A76ED395 ON project_guests (user_id)');
        $this->addSql('ALTER TABLE project_invitations ADD CONSTRAINT FK_EBE8E755166D1F9C FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE project_guests ADD CONSTRAINT FK_5AB557F8166D1F9C FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_guests ADD CONSTRAINT FK_5AB557F8A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE project_invitations DROP CONSTRAINT FK_EBE8E755166D1F9C');
        $this->addSql('ALTER TABLE project_guests DROP CONSTRAINT FK_5AB557F8166D1F9C');
        $this->addSql('ALTER TABLE project_guests DROP CONSTRAINT FK_5AB557F8A76ED395');
        $this->addSql('DROP TABLE project_invitations');
        $this->addSql('DROP TABLE project_guests');
    }
}
