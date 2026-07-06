<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260706130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add guest access levels to project guests and invitations';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE project_guests ADD COLUMN IF NOT EXISTS access VARCHAR(20) DEFAULT 'content' NOT NULL");
        $this->addSql("ALTER TABLE project_invitations ADD COLUMN IF NOT EXISTS access VARCHAR(20) DEFAULT 'content' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project_guests DROP COLUMN IF EXISTS access');
        $this->addSql('ALTER TABLE project_invitations DROP COLUMN IF EXISTS access');
    }
}