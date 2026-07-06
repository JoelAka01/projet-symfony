<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260706123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add target word count control to V2 topic research runs.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE topic_researches ADD target_word_count INT DEFAULT 1400 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE topic_researches DROP target_word_count');
    }
}
