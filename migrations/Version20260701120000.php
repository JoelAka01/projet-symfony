<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260701120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add API cost optimization caches, budgets, usage logs, and run quality mode.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE serp_caches (id UUID NOT NULL, keyword VARCHAR(500) NOT NULL, country VARCHAR(10) NOT NULL, language VARCHAR(10) NOT NULL, provider VARCHAR(80) NOT NULL, result_hash VARCHAR(64) NOT NULL, raw_data JSONB NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, expires_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_serp_caches_lookup ON serp_caches (keyword, country, language, provider)');
        $this->addSql('CREATE INDEX idx_serp_caches_expires ON serp_caches (expires_at)');

        $this->addSql('CREATE TABLE ai_caches (id UUID NOT NULL, operation VARCHAR(120) NOT NULL, input_hash VARCHAR(64) NOT NULL, model VARCHAR(120) NOT NULL, response_json JSONB NOT NULL, tokens_saved_estimate INT NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_ai_caches_operation_input_model ON ai_caches (operation, input_hash, model)');
        $this->addSql('CREATE INDEX idx_ai_caches_operation ON ai_caches (operation)');

        $this->addSql('CREATE TABLE project_api_budgets (id UUID NOT NULL, project_id UUID NOT NULL, daily_max_cost NUMERIC(10, 4) NOT NULL, monthly_max_cost NUMERIC(10, 4) NOT NULL, daily_max_ai_tokens INT NOT NULL, daily_max_serp_calls INT NOT NULL, alert_threshold SMALLINT NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_project_api_budgets_project ON project_api_budgets (project_id)');

        $this->addSql('CREATE TABLE api_usage_logs (id UUID NOT NULL, project_id UUID DEFAULT NULL, provider VARCHAR(80) NOT NULL, operation VARCHAR(120) NOT NULL, estimated_cost NUMERIC(10, 6) NOT NULL, tokens_input INT NOT NULL, tokens_output INT NOT NULL, cache_hit BOOLEAN NOT NULL, saved_cost_estimate NUMERIC(10, 6) NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_67E4AE27166D1F9C ON api_usage_logs (project_id)');
        $this->addSql('CREATE INDEX idx_api_usage_logs_project_created ON api_usage_logs (project_id, created_at)');
        $this->addSql('CREATE INDEX idx_api_usage_logs_provider_operation ON api_usage_logs (provider, operation)');

        $this->addSql("ALTER TABLE topic_researches ADD quality_mode VARCHAR(255) DEFAULT 'BALANCED_MODE' NOT NULL");
        $this->addSql('ALTER TABLE project_api_budgets ADD CONSTRAINT FK_EE3426ED166D1F9C FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE api_usage_logs ADD CONSTRAINT FK_67E4AE27166D1F9C FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project_api_budgets DROP CONSTRAINT FK_EE3426ED166D1F9C');
        $this->addSql('ALTER TABLE api_usage_logs DROP CONSTRAINT FK_67E4AE27166D1F9C');
        $this->addSql('ALTER TABLE topic_researches DROP quality_mode');
        $this->addSql('DROP TABLE serp_caches');
        $this->addSql('DROP TABLE ai_caches');
        $this->addSql('DROP TABLE project_api_budgets');
        $this->addSql('DROP TABLE api_usage_logs');
    }
}
