<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260630141000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add V2 article generation pipeline storage and Redis message routing support.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE topic_researches (id UUID NOT NULL, project_id UUID NOT NULL, requested_by_id UUID DEFAULT NULL, primary_keyword VARCHAR(200) NOT NULL, status VARCHAR(255) NOT NULL, country VARCHAR(10) DEFAULT NULL, language VARCHAR(10) DEFAULT NULL, sector VARCHAR(180) DEFAULT NULL, audience TEXT DEFAULT NULL, business_objective TEXT DEFAULT NULL, current_step VARCHAR(40) DEFAULT NULL, failed_step VARCHAR(40) DEFAULT NULL, error_message TEXT DEFAULT NULL, completed_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_12CE80B2166D1F9C ON topic_researches (project_id)');
        $this->addSql('CREATE INDEX IDX_12CE80B267B3B43D ON topic_researches (requested_by_id)');
        $this->addSql('CREATE INDEX idx_topic_researches_status ON topic_researches (status)');
        $this->addSql('CREATE INDEX idx_topic_researches_project_created ON topic_researches (project_id, created_at)');

        $this->addSql('CREATE TABLE serp_analyses (id UUID NOT NULL, topic_research_id UUID NOT NULL, competitors JSONB NOT NULL, serp_features JSONB NOT NULL, content_gaps JSONB NOT NULL, questions JSONB NOT NULL, average_word_count INTEGER NOT NULL, total_questions INTEGER NOT NULL, raw_serp_response JSONB NOT NULL, analyzed_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_7149A9B3F733EE4A ON serp_analyses (topic_research_id)');

        $this->addSql('CREATE TABLE intelligence_analyses (id UUID NOT NULL, topic_research_id UUID NOT NULL, primary_intent VARCHAR(80) NOT NULL, intent_breakdown JSONB NOT NULL, entities JSONB NOT NULL, semantic_concepts JSONB NOT NULL, analyzed_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_66886DD6F733EE4A ON intelligence_analyses (topic_research_id)');

        $this->addSql('CREATE TABLE content_briefs (id UUID NOT NULL, topic_research_id UUID NOT NULL, target_audience TEXT DEFAULT NULL, objective TEXT DEFAULT NULL, intent VARCHAR(80) DEFAULT NULL, tone_recommendation VARCHAR(120) DEFAULT NULL, target_word_count INTEGER DEFAULT NULL, key_entities JSONB NOT NULL, key_questions JSONB NOT NULL, competitor_insights JSONB NOT NULL, cta TEXT DEFAULT NULL, sources JSONB NOT NULL, seo_targets JSONB NOT NULL, outline JSONB NOT NULL, faq_suggestions JSONB NOT NULL, table_suggestions JSONB NOT NULL, estimated_word_count INTEGER DEFAULT NULL, generated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_371F0402F733EE4A ON content_briefs (topic_research_id)');

        $this->addSql('CREATE TABLE pipeline_run_logs (id UUID NOT NULL, topic_research_id UUID NOT NULL, step VARCHAR(40) NOT NULL, attempt INTEGER NOT NULL, prompt_sent TEXT NOT NULL, raw_response TEXT DEFAULT NULL, parsed_response JSONB DEFAULT NULL, model VARCHAR(120) NOT NULL, provider VARCHAR(50) NOT NULL, input_tokens INTEGER NOT NULL, output_tokens INTEGER NOT NULL, total_credits INTEGER NOT NULL, duration_ms INTEGER NOT NULL, status VARCHAR(20) NOT NULL, error_message TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_BAF95377F733EE4A ON pipeline_run_logs (topic_research_id)');
        $this->addSql('CREATE INDEX idx_pipeline_run_logs_topic_step ON pipeline_run_logs (topic_research_id, step)');
        $this->addSql('CREATE INDEX idx_pipeline_run_logs_created ON pipeline_run_logs (created_at)');

        $this->addSql('ALTER TABLE articles ADD topic_research_id UUID DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_BFDD3168F733EE4A ON articles (topic_research_id)');

        $this->addSql('ALTER TABLE topic_researches ADD CONSTRAINT FK_12CE80B2166D1F9C FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE topic_researches ADD CONSTRAINT FK_12CE80B267B3B43D FOREIGN KEY (requested_by_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE serp_analyses ADD CONSTRAINT FK_7149A9B3F733EE4A FOREIGN KEY (topic_research_id) REFERENCES topic_researches (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE intelligence_analyses ADD CONSTRAINT FK_66886DD6F733EE4A FOREIGN KEY (topic_research_id) REFERENCES topic_researches (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE content_briefs ADD CONSTRAINT FK_371F0402F733EE4A FOREIGN KEY (topic_research_id) REFERENCES topic_researches (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE pipeline_run_logs ADD CONSTRAINT FK_BAF95377F733EE4A FOREIGN KEY (topic_research_id) REFERENCES topic_researches (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE articles ADD CONSTRAINT FK_BFDD3168F733EE4A FOREIGN KEY (topic_research_id) REFERENCES topic_researches (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE articles DROP CONSTRAINT FK_BFDD3168F733EE4A');
        $this->addSql('ALTER TABLE pipeline_run_logs DROP CONSTRAINT FK_BAF95377F733EE4A');
        $this->addSql('ALTER TABLE content_briefs DROP CONSTRAINT FK_371F0402F733EE4A');
        $this->addSql('ALTER TABLE intelligence_analyses DROP CONSTRAINT FK_66886DD6F733EE4A');
        $this->addSql('ALTER TABLE serp_analyses DROP CONSTRAINT FK_7149A9B3F733EE4A');
        $this->addSql('ALTER TABLE topic_researches DROP CONSTRAINT FK_12CE80B267B3B43D');
        $this->addSql('ALTER TABLE topic_researches DROP CONSTRAINT FK_12CE80B2166D1F9C');

        $this->addSql('DROP TABLE pipeline_run_logs');
        $this->addSql('DROP TABLE content_briefs');
        $this->addSql('DROP TABLE intelligence_analyses');
        $this->addSql('DROP TABLE serp_analyses');
        $this->addSql('DROP TABLE topic_researches');

        $this->addSql('DROP INDEX IDX_BFDD3168F733EE4A');
        $this->addSql('ALTER TABLE articles DROP topic_research_id');
    }
}
