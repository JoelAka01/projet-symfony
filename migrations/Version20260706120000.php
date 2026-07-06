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

        // Seed required defaults so the admin UI and pipelines work immediately after migrate
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:sP');
        $rows = [
            ['ARTICLE_GENERATION', 'Article Generation', true, true, 'required'],
            ['HTML_SANITIZATION', 'HTML Sanitization', true, true, 'required'],
            ['QUALITY_GATE', 'Quality Gate', true, true, 'required'],
            ['ERROR_LOGGING', 'Error Logging', true, true, 'required'],
            ['SERP_INTELLIGENCE', 'SERP Intelligence', true, false, 'reuse_or_empty'],
            ['QUESTION_INTELLIGENCE', 'Question Intelligence', true, false, 'reuse_or_empty'],
            ['INTENT_DETECTION', 'Intent Detection', true, false, 'reuse_or_empty'],
            ['ENTITY_EXTRACTION', 'Entity Extraction', true, false, 'reuse_or_empty'],
            ['CONTENT_BRIEF', 'Content Brief', true, false, 'reuse_or_empty'],
            ['OUTLINE_BUILDER', 'Outline Builder', true, false, 'reuse_or_empty'],
            ['INTERNAL_LINKING', 'Internal Linking', true, false, 'continue'],
            ['SEO_SCORE', 'SEO Score', true, false, 'continue'],
            ['EEAT_OPTIMIZER', 'EEAT Optimizer', true, false, 'continue'],
            ['GEO_OPTIMIZER', 'GEO Optimizer', true, false, 'continue'],
            ['AUTO_PUBLISH', 'Auto Publish', true, false, 'continue'],
        ];
        foreach ($rows as [$key, $label, $enabled, $required, $fallback]) {
            $this->addSql(sprintf(
                "INSERT INTO pipeline_step_configs (id, step_key, label, is_enabled, is_required, fallback_mode, updated_at) VALUES (gen_random_uuid(), '%s', '%s', %s, %s, '%s', '%s') ON CONFLICT (step_key) DO NOTHING",
                $key,
                str_replace("'", "''", $label),
                $enabled ? 'true' : 'false',
                $required ? 'true' : 'false',
                $fallback,
                $now
            ));
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE pipeline_step_configs');
    }
}