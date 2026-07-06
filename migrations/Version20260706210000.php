<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Creates the join table for the Project <-> User members ManyToMany relation.
 * This ensures we have at least 2 explicit ManyToMany relations (Article<->Keyword + Project<->User).
 */
final class Version20260706210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create project_members join table for Project<->User ManyToMany (collaborators)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE project_members (project_id UUID NOT NULL, user_id UUID NOT NULL, PRIMARY KEY(project_id, user_id))');
        $this->addSql('CREATE INDEX IDX_PROJECT_MEMBERS_PROJECT ON project_members (project_id)');
        $this->addSql('CREATE INDEX IDX_PROJECT_MEMBERS_USER ON project_members (user_id)');
        $this->addSql('ALTER TABLE project_members ADD CONSTRAINT FK_PROJECT_MEMBERS_PROJECT FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE project_members ADD CONSTRAINT FK_PROJECT_MEMBERS_USER FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project_members DROP CONSTRAINT FK_PROJECT_MEMBERS_USER');
        $this->addSql('ALTER TABLE project_members DROP CONSTRAINT FK_PROJECT_MEMBERS_PROJECT');
        $this->addSql('DROP TABLE project_members');
    }
}
