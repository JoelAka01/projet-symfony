<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260707100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add internationalization fields to users and projects tables';
    }

    public function up(Schema $schema): void
    {
        // User: add locale for interface language
        $this->addSql('ALTER TABLE users ADD COLUMN locale VARCHAR(10) NOT NULL DEFAULT \'fr\'');

        // Project: rename default_language → language
        $this->addSql('ALTER TABLE projects RENAME COLUMN default_language TO language');

        // Project: add content_language for content generation language
        $this->addSql('ALTER TABLE projects ADD COLUMN content_language VARCHAR(10) DEFAULT NULL');

        // Project: add auto_detect_language flag
        $this->addSql('ALTER TABLE projects ADD COLUMN auto_detect_language BOOLEAN NOT NULL DEFAULT true');

        // Project: add language_confidence score
        $this->addSql('ALTER TABLE projects ADD COLUMN language_confidence SMALLINT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP COLUMN locale');
        $this->addSql('ALTER TABLE projects RENAME COLUMN language TO default_language');
        $this->addSql('ALTER TABLE projects DROP COLUMN content_language');
        $this->addSql('ALTER TABLE projects DROP COLUMN auto_detect_language');
        $this->addSql('ALTER TABLE projects DROP COLUMN language_confidence');
    }
}
