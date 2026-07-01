<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260701100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add internal linking engine storage.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE site_pages (id UUID NOT NULL, project_id UUID NOT NULL, url VARCHAR(1000) NOT NULL, title VARCHAR(500) NOT NULL, page_type VARCHAR(255) NOT NULL, target_keyword VARCHAR(500) DEFAULT NULL, business_priority SMALLINT NOT NULL, anchor_suggestions JSONB NOT NULL, is_active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_site_pages_project_url ON site_pages (project_id, url)');
        $this->addSql('CREATE INDEX IDX_67203CF166D1F9C ON site_pages (project_id)');
        $this->addSql('CREATE INDEX idx_site_pages_project_active ON site_pages (project_id, is_active)');
        $this->addSql('CREATE INDEX idx_site_pages_type_priority ON site_pages (page_type, business_priority)');

        $this->addSql('CREATE TABLE internal_link_suggestions (id UUID NOT NULL, source_article_id UUID NOT NULL, target_page_id UUID NOT NULL, anchor VARCHAR(180) NOT NULL, position INTEGER DEFAULT NULL, status VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_internal_link_suggestions_article ON internal_link_suggestions (source_article_id)');
        $this->addSql('CREATE INDEX idx_internal_link_suggestions_target ON internal_link_suggestions (target_page_id)');
        $this->addSql('CREATE INDEX idx_internal_link_suggestions_status ON internal_link_suggestions (status)');
        $this->addSql('CREATE INDEX IDX_D872D4198F801BC5 ON internal_link_suggestions (source_article_id)');
        $this->addSql('CREATE INDEX IDX_D872D419C2E5CD85 ON internal_link_suggestions (target_page_id)');

        $this->addSql('ALTER TABLE site_pages ADD CONSTRAINT FK_67203CF166D1F9C FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE internal_link_suggestions ADD CONSTRAINT FK_D872D4198F801BC5 FOREIGN KEY (source_article_id) REFERENCES articles (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE internal_link_suggestions ADD CONSTRAINT FK_D872D419C2E5CD85 FOREIGN KEY (target_page_id) REFERENCES site_pages (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE internal_link_suggestions DROP CONSTRAINT FK_D872D4198F801BC5');
        $this->addSql('ALTER TABLE internal_link_suggestions DROP CONSTRAINT FK_D872D419C2E5CD85');
        $this->addSql('ALTER TABLE site_pages DROP CONSTRAINT FK_67203CF166D1F9C');

        $this->addSql('DROP TABLE internal_link_suggestions');
        $this->addSql('DROP TABLE site_pages');
    }
}
