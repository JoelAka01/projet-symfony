<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260609153000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add real crawler audit limits, failure counts, and extracted page facts.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE audits ADD pages_failed INTEGER DEFAULT NULL');
        $this->addSql('ALTER TABLE audits ADD max_pages INTEGER DEFAULT NULL');
        $this->addSql('ALTER TABLE audits ADD max_depth INTEGER DEFAULT NULL');

        $this->addSql('ALTER TABLE audit_pages ADD normalized_url VARCHAR(1000) DEFAULT NULL');
        $this->addSql('ALTER TABLE audit_pages ADD content_type VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE audit_pages ADD robots_meta TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE audit_pages ADD internal_links_count INTEGER DEFAULT NULL');
        $this->addSql('ALTER TABLE audit_pages ADD external_links_count INTEGER DEFAULT NULL');
        $this->addSql('ALTER TABLE audit_pages ADD images_without_alt_count INTEGER DEFAULT NULL');
        $this->addSql('ALTER TABLE audit_pages ADD structured_data_present BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE audit_pages ADD error_message TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE audit_pages ALTER structured_data_present DROP DEFAULT');
        $this->addSql('CREATE INDEX idx_audit_pages_audit_normalized_url ON audit_pages (audit_id, normalized_url)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_audit_pages_audit_normalized_url');
        $this->addSql('ALTER TABLE audit_pages DROP error_message');
        $this->addSql('ALTER TABLE audit_pages DROP structured_data_present');
        $this->addSql('ALTER TABLE audit_pages DROP images_without_alt_count');
        $this->addSql('ALTER TABLE audit_pages DROP external_links_count');
        $this->addSql('ALTER TABLE audit_pages DROP internal_links_count');
        $this->addSql('ALTER TABLE audit_pages DROP robots_meta');
        $this->addSql('ALTER TABLE audit_pages DROP content_type');
        $this->addSql('ALTER TABLE audit_pages DROP normalized_url');

        $this->addSql('ALTER TABLE audits DROP max_depth');
        $this->addSql('ALTER TABLE audits DROP max_pages');
        $this->addSql('ALTER TABLE audits DROP pages_failed');
    }
}
