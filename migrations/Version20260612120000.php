<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260612120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add real CMS provider settings and CMS-ready article SEO fields.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cms_connections ADD settings JSONB DEFAULT NULL');
        $this->addSql('ALTER TABLE cms_connections ADD last_error TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE articles ADD seo_title VARCHAR(70) DEFAULT NULL');
        $this->addSql('ALTER TABLE articles ADD seo_description VARCHAR(320) DEFAULT NULL');
        $this->addSql('ALTER TABLE articles ADD excerpt TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE articles ADD generation_metadata JSONB DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_cms_publication_article_connection ON cms_publications (article_id, cms_connection_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS uniq_cms_publication_article_connection');
        $this->addSql('ALTER TABLE cms_connections DROP settings');
        $this->addSql('ALTER TABLE cms_connections DROP last_error');
        $this->addSql('ALTER TABLE articles DROP seo_title');
        $this->addSql('ALTER TABLE articles DROP seo_description');
        $this->addSql('ALTER TABLE articles DROP excerpt');
        $this->addSql('ALTER TABLE articles DROP generation_metadata');
    }
}
