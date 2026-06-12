<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260612131927 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Delete projects with status ARCHIVED to clean up after removing archiving feature';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("DELETE FROM projects WHERE status = 'ARCHIVED'");
    }

    public function down(Schema $schema): void
    {
        // No down migration possible because deleted data is gone.
    }
}
