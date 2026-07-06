<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260706120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add admin pipeline step configuration switches.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE pipeline_step_configs (id UUID NOT NULL, step_key VARCHAR(80) NOT NULL, label VARCHAR(120) NOT NULL, is_enabled BOOLEAN NOT NULL, is_required BOOLEAN NOT NULL, fallback_mode VARCHAR(40) NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_pipeline_step_configs_step_key ON pipeline_step_configs (step_key)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE pipeline_step_configs');
    }
}