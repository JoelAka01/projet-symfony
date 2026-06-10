<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260610130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'store compact real crawler facts for richer ai seo analysis';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE audit_pages ADD metadata JSONB DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE audit_pages DROP metadata');
    }
}
