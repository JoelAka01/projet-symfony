<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260612180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add AI usage ledger and retain the user who requested each audit.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE audits ADD requested_by_id UUID DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_AUDITS_REQUESTED_BY ON audits (requested_by_id)');
        $this->addSql('ALTER TABLE audits ADD CONSTRAINT FK_AUDITS_REQUESTED_BY FOREIGN KEY (requested_by_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('UPDATE audits SET requested_by_id = projects.owner_id FROM projects WHERE audits.project_id = projects.id AND audits.requested_by_id IS NULL');

        $this->addSql('CREATE TABLE ai_usages (id UUID NOT NULL, user_id UUID DEFAULT NULL, project_id UUID DEFAULT NULL, provider VARCHAR(50) NOT NULL, model VARCHAR(120) NOT NULL, operation VARCHAR(50) NOT NULL, input_tokens INTEGER NOT NULL, output_tokens INTEGER NOT NULL, cached_input_tokens INTEGER NOT NULL, credits INTEGER NOT NULL, resource_id UUID DEFAULT NULL, provider_usage JSONB DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_AI_USAGES_USER ON ai_usages (user_id)');
        $this->addSql('CREATE INDEX IDX_AI_USAGES_PROJECT ON ai_usages (project_id)');
        $this->addSql('CREATE INDEX idx_ai_usages_user_created ON ai_usages (user_id, created_at)');
        $this->addSql('CREATE INDEX idx_ai_usages_project_created ON ai_usages (project_id, created_at)');
        $this->addSql('CREATE INDEX idx_ai_usages_operation ON ai_usages (operation)');
        $this->addSql('ALTER TABLE ai_usages ADD CONSTRAINT FK_AI_USAGES_USER FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE ai_usages ADD CONSTRAINT FK_AI_USAGES_PROJECT FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql(<<<'SQL'
            INSERT INTO ai_usages (
                id,
                user_id,
                project_id,
                provider,
                model,
                operation,
                input_tokens,
                output_tokens,
                cached_input_tokens,
                credits,
                resource_id,
                provider_usage,
                created_at
            )
            SELECT
                audits.id,
                audits.requested_by_id,
                audits.project_id,
                COALESCE(NULLIF(audits.metadata->'ai_analysis'->>'provider', ''), 'anthropic'),
                COALESCE(NULLIF(audits.metadata->'ai_analysis'->>'model', ''), 'unknown'),
                'audit_analysis',
                COALESCE(NULLIF(audits.metadata->'ai_analysis'->'usage'->>'input_tokens', '')::INTEGER, 0),
                COALESCE(NULLIF(audits.metadata->'ai_analysis'->'usage'->>'output_tokens', '')::INTEGER, 0),
                COALESCE(NULLIF(audits.metadata->'ai_analysis'->'usage'->>'cache_creation_input_tokens', '')::INTEGER, 0)
                    + COALESCE(NULLIF(audits.metadata->'ai_analysis'->'usage'->>'cache_read_input_tokens', '')::INTEGER, 0),
                COALESCE(NULLIF(audits.metadata->'ai_analysis'->'usage'->>'input_tokens', '')::INTEGER, 0)
                    + COALESCE(NULLIF(audits.metadata->'ai_analysis'->'usage'->>'output_tokens', '')::INTEGER, 0)
                    + COALESCE(NULLIF(audits.metadata->'ai_analysis'->'usage'->>'cache_creation_input_tokens', '')::INTEGER, 0)
                    + COALESCE(NULLIF(audits.metadata->'ai_analysis'->'usage'->>'cache_read_input_tokens', '')::INTEGER, 0),
                audits.id,
                audits.metadata->'ai_analysis'->'usage',
                audits.created_at
            FROM audits
            WHERE jsonb_typeof(audits.metadata->'ai_analysis'->'usage') = 'object'
              AND (
                COALESCE(NULLIF(audits.metadata->'ai_analysis'->'usage'->>'input_tokens', '')::INTEGER, 0)
                + COALESCE(NULLIF(audits.metadata->'ai_analysis'->'usage'->>'output_tokens', '')::INTEGER, 0)
                + COALESCE(NULLIF(audits.metadata->'ai_analysis'->'usage'->>'cache_creation_input_tokens', '')::INTEGER, 0)
                + COALESCE(NULLIF(audits.metadata->'ai_analysis'->'usage'->>'cache_read_input_tokens', '')::INTEGER, 0)
              ) > 0
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE ai_usages');
        $this->addSql('ALTER TABLE audits DROP CONSTRAINT FK_AUDITS_REQUESTED_BY');
        $this->addSql('DROP INDEX IDX_AUDITS_REQUESTED_BY');
        $this->addSql('ALTER TABLE audits DROP requested_by_id');
    }
}
