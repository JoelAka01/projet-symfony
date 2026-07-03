<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260701110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add audit-first keyword suggestions.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE keyword_suggestions (id UUID NOT NULL, project_id UUID NOT NULL, term VARCHAR(500) NOT NULL, normalized_term VARCHAR(500) NOT NULL, source VARCHAR(255) NOT NULL, intent VARCHAR(80) DEFAULT NULL, cluster_name VARCHAR(180) DEFAULT NULL, opportunity_score SMALLINT NOT NULL, business_score SMALLINT NOT NULL, difficulty_estimate SMALLINT NOT NULL, search_volume_estimate INT DEFAULT NULL, is_selected BOOLEAN NOT NULL, raw_data JSONB NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_keyword_suggestions_project_normalized ON keyword_suggestions (project_id, normalized_term)');
        $this->addSql('CREATE INDEX IDX_8448DA0E166D1F9C ON keyword_suggestions (project_id)');
        $this->addSql('CREATE INDEX idx_keyword_suggestions_project_score ON keyword_suggestions (project_id, opportunity_score)');
        $this->addSql('CREATE INDEX idx_keyword_suggestions_source ON keyword_suggestions (source)');
        $this->addSql('ALTER TABLE keyword_suggestions ADD CONSTRAINT FK_8448DA0E166D1F9C FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE keyword_suggestions DROP CONSTRAINT FK_8448DA0E166D1F9C');
        $this->addSql('DROP TABLE keyword_suggestions');
    }
}
