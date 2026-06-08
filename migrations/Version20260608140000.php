<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260608140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create initial SEO/GEO/AI SaaS domain schema with UUID entities, inheritance, and join tables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE users (id UUID NOT NULL, email VARCHAR(255) NOT NULL, password_hash VARCHAR(255) NOT NULL, role VARCHAR(255) NOT NULL, is_2fa_enabled BOOLEAN NOT NULL, last_login_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_users_email ON users (email)');

        $this->addSql('CREATE TABLE organizations (id UUID NOT NULL, name VARCHAR(180) NOT NULL, billing_email VARCHAR(255) DEFAULT NULL, white_label_enabled BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');

        $this->addSql('CREATE TABLE organization_users (id UUID NOT NULL, organization_id UUID NOT NULL, user_id UUID NOT NULL, role VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_organization_users_membership ON organization_users (organization_id, user_id)');
        $this->addSql('CREATE INDEX IDX_93C1A67032C8A3DE ON organization_users (organization_id)');
        $this->addSql('CREATE INDEX IDX_93C1A670A76ED395 ON organization_users (user_id)');

        $this->addSql('CREATE TABLE projects (id UUID NOT NULL, organization_id UUID NOT NULL, owner_id UUID DEFAULT NULL, name VARCHAR(180) NOT NULL, status VARCHAR(255) NOT NULL, default_language VARCHAR(10) DEFAULT NULL, target_country VARCHAR(10) DEFAULT NULL, seo_score SMALLINT DEFAULT NULL, geo_score SMALLINT DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_5C93B3A432C8A3DE ON projects (organization_id)');
        $this->addSql('CREATE INDEX IDX_5C93B3A47E3C61F9 ON projects (owner_id)');
        $this->addSql('CREATE INDEX idx_projects_status ON projects (status)');

        $this->addSql('CREATE TABLE project_members (project_id UUID NOT NULL, user_id UUID NOT NULL, PRIMARY KEY(project_id, user_id))');
        $this->addSql('CREATE INDEX IDX_252DF8F3166D1F9C ON project_members (project_id)');
        $this->addSql('CREATE INDEX IDX_252DF8F3A76ED395 ON project_members (user_id)');

        $this->addSql('CREATE TABLE domains (id UUID NOT NULL, project_id UUID NOT NULL, root_domain VARCHAR(255) NOT NULL, verified_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, verification_method VARCHAR(50) DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_domains_project_root ON domains (project_id, root_domain)');
        $this->addSql('CREATE INDEX IDX_A7A91E0B166D1F9C ON domains (project_id)');

        $this->addSql('CREATE TABLE cms_connections (id UUID NOT NULL, project_id UUID NOT NULL, provider VARCHAR(255) NOT NULL, base_url VARCHAR(500) NOT NULL, encrypted_access_token TEXT DEFAULT NULL, encrypted_refresh_token TEXT DEFAULT NULL, encrypted_api_key TEXT DEFAULT NULL, token_expires_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, is_active BOOLEAN NOT NULL, last_tested_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_653477E3166D1F9C ON cms_connections (project_id)');
        $this->addSql('CREATE INDEX idx_cms_connections_project_provider ON cms_connections (project_id, provider)');

        $this->addSql('CREATE TABLE keyword_clusters (id UUID NOT NULL, project_id UUID NOT NULL, name VARCHAR(255) NOT NULL, intent VARCHAR(80) DEFAULT NULL, main_topic VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_54B878DF166D1F9C ON keyword_clusters (project_id)');
        $this->addSql('CREATE INDEX idx_keyword_clusters_project ON keyword_clusters (project_id)');

        $this->addSql('CREATE TABLE keywords (id UUID NOT NULL, project_id UUID NOT NULL, keyword_cluster_id UUID DEFAULT NULL, term VARCHAR(500) NOT NULL, search_volume INTEGER DEFAULT NULL, difficulty SMALLINT DEFAULT NULL, cpc NUMERIC(10, 2) DEFAULT NULL, intent VARCHAR(80) DEFAULT NULL, is_fanout_keyword BOOLEAN NOT NULL, source VARCHAR(80) DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_29A8C09166D1F9C ON keywords (project_id)');
        $this->addSql('CREATE INDEX IDX_29A8C09C68DF3B8 ON keywords (keyword_cluster_id)');
        $this->addSql('CREATE INDEX idx_keywords_project_term ON keywords (project_id, term)');
        $this->addSql('CREATE INDEX idx_keywords_cluster ON keywords (keyword_cluster_id)');

        $this->addSql('CREATE TABLE content_items (id UUID NOT NULL, project_id UUID NOT NULL, title VARCHAR(500) NOT NULL, published_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, type VARCHAR(20) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_9F6E521E166D1F9C ON content_items (project_id)');

        $this->addSql('CREATE TABLE articles (id UUID NOT NULL, keyword_cluster_id UUID DEFAULT NULL, primary_keyword_id UUID DEFAULT NULL, slug VARCHAR(500) DEFAULT NULL, status VARCHAR(255) NOT NULL, word_count INTEGER DEFAULT NULL, seo_score SMALLINT DEFAULT NULL, geo_score SMALLINT DEFAULT NULL, content_markdown TEXT DEFAULT NULL, content_html TEXT DEFAULT NULL, faq_json JSONB DEFAULT NULL, internal_links_json JSONB DEFAULT NULL, external_sources_json JSONB DEFAULT NULL, cannibalization_score SMALLINT DEFAULT NULL, generated_by_provider VARCHAR(80) DEFAULT NULL, generated_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, scheduled_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_BFDD3168C68DF3B8 ON articles (keyword_cluster_id)');
        $this->addSql('CREATE INDEX IDX_BFDD3168F5D46435 ON articles (primary_keyword_id)');
        $this->addSql('CREATE INDEX idx_articles_status ON articles (status)');

        $this->addSql('CREATE TABLE article_keywords (article_id UUID NOT NULL, keyword_id UUID NOT NULL, PRIMARY KEY(article_id, keyword_id))');
        $this->addSql('CREATE INDEX IDX_5A2B626D7294869C ON article_keywords (article_id)');
        $this->addSql('CREATE INDEX IDX_5A2B626D46478CFB ON article_keywords (keyword_id)');

        $this->addSql('CREATE TABLE reports (id UUID NOT NULL, status VARCHAR(255) NOT NULL, period_start DATE DEFAULT NULL, period_end DATE DEFAULT NULL, storage_url VARCHAR(1000) DEFAULT NULL, csv_export_url VARCHAR(1000) DEFAULT NULL, sent_to_email VARCHAR(255) DEFAULT NULL, generated_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, sent_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, error_message TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_reports_project_status ON reports (status)');

        $this->addSql('CREATE TABLE cms_publications (id UUID NOT NULL, article_id UUID NOT NULL, cms_connection_id UUID NOT NULL, external_post_id VARCHAR(255) DEFAULT NULL, external_url VARCHAR(1000) DEFAULT NULL, status VARCHAR(255) NOT NULL, scheduled_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, published_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, error_message TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_26D06A337294869C ON cms_publications (article_id)');
        $this->addSql('CREATE INDEX IDX_26D06A33D70002B5 ON cms_publications (cms_connection_id)');
        $this->addSql('CREATE INDEX idx_cms_publications_status ON cms_publications (status)');

        $this->addSql('CREATE TABLE audits (id UUID NOT NULL, project_id UUID NOT NULL, domain_id UUID NOT NULL, status VARCHAR(255) NOT NULL, crawl_started_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, crawl_finished_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, pages_crawled INTEGER DEFAULT NULL, seo_score SMALLINT DEFAULT NULL, core_web_vitals_score SMALLINT DEFAULT NULL, metadata JSONB DEFAULT NULL, error_message TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_2A8938F166D1F9C ON audits (project_id)');
        $this->addSql('CREATE INDEX IDX_2A8938F115F0EE5 ON audits (domain_id)');
        $this->addSql('CREATE INDEX idx_audits_project_status ON audits (project_id, status)');

        $this->addSql('CREATE TABLE audit_pages (id UUID NOT NULL, audit_id UUID NOT NULL, url VARCHAR(1000) NOT NULL, canonical_url VARCHAR(1000) DEFAULT NULL, status_code SMALLINT DEFAULT NULL, title VARCHAR(500) DEFAULT NULL, meta_description TEXT DEFAULT NULL, h1 TEXT DEFAULT NULL, word_count INTEGER DEFAULT NULL, load_time_ms INTEGER DEFAULT NULL, is_indexable BOOLEAN NOT NULL, is_orphan BOOLEAN NOT NULL, content_hash VARCHAR(64) DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_56FBCA28BD29F3B6 ON audit_pages (audit_id)');
        $this->addSql('CREATE INDEX idx_audit_pages_audit_status ON audit_pages (audit_id, status_code)');
        $this->addSql('CREATE INDEX idx_audit_pages_content_hash ON audit_pages (content_hash)');

        $this->addSql('CREATE TABLE audit_issues (id UUID NOT NULL, audit_id UUID NOT NULL, audit_page_id UUID DEFAULT NULL, issue_type VARCHAR(80) NOT NULL, severity VARCHAR(20) NOT NULL, message TEXT DEFAULT NULL, recommendation TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_4C21F67DBD29F3B6 ON audit_issues (audit_id)');
        $this->addSql('CREATE INDEX IDX_4C21F67D1E1337D3 ON audit_issues (audit_page_id)');
        $this->addSql('CREATE INDEX idx_audit_issues_audit_severity ON audit_issues (audit_id, severity)');

        $this->addSql('CREATE TABLE keyword_rankings (id UUID NOT NULL, keyword_id UUID NOT NULL, project_id UUID NOT NULL, rank_position INTEGER DEFAULT NULL, previous_rank_position INTEGER DEFAULT NULL, search_engine VARCHAR(50) DEFAULT NULL, device VARCHAR(30) DEFAULT NULL, country VARCHAR(10) DEFAULT NULL, checked_at DATE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_A94E31546478CFB ON keyword_rankings (keyword_id)');
        $this->addSql('CREATE INDEX IDX_A94E315166D1F9C ON keyword_rankings (project_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_keyword_rankings_daily ON keyword_rankings (keyword_id, search_engine, device, country, checked_at)');
        $this->addSql('CREATE INDEX idx_keyword_rankings_project_checked ON keyword_rankings (project_id, checked_at)');

        $this->addSql('CREATE TABLE article_images (id UUID NOT NULL, article_id UUID NOT NULL, storage_url VARCHAR(1000) NOT NULL, prompt TEXT DEFAULT NULL, alt_text VARCHAR(500) DEFAULT NULL, provider VARCHAR(80) DEFAULT NULL, width INTEGER DEFAULT NULL, height INTEGER DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_26F3E1D7294869C ON article_images (article_id)');

        $this->addSql('CREATE TABLE geo_prompts (id UUID NOT NULL, project_id UUID NOT NULL, keyword_id UUID DEFAULT NULL, prompt_text TEXT NOT NULL, topic VARCHAR(255) DEFAULT NULL, is_active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_F7C91D65166D1F9C ON geo_prompts (project_id)');
        $this->addSql('CREATE INDEX IDX_F7C91D6546478CFB ON geo_prompts (keyword_id)');
        $this->addSql('CREATE INDEX idx_geo_prompts_project_active ON geo_prompts (project_id, is_active)');

        $this->addSql('CREATE TABLE geo_results (id UUID NOT NULL, geo_prompt_id UUID NOT NULL, provider VARCHAR(255) NOT NULL, response_text TEXT DEFAULT NULL, mentioned_brand BOOLEAN NOT NULL, cited_project_url BOOLEAN NOT NULL, cited_urls_json JSONB DEFAULT NULL, competitors_json JSONB DEFAULT NULL, sentiment_score NUMERIC(5, 2) DEFAULT NULL, visibility_score SMALLINT DEFAULT NULL, checked_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_5EA0D30224F4B6C5 ON geo_results (geo_prompt_id)');
        $this->addSql('CREATE INDEX idx_geo_results_prompt_checked ON geo_results (geo_prompt_id, checked_at)');

        $this->addSql('CREATE TABLE geo_daily_snapshots (id UUID NOT NULL, project_id UUID NOT NULL, snapshot_date DATE NOT NULL, geo_score SMALLINT DEFAULT NULL, prompts_checked INTEGER DEFAULT NULL, mentions_count INTEGER DEFAULT NULL, citations_count INTEGER DEFAULT NULL, competitors_json JSONB DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_736EE4BC166D1F9C ON geo_daily_snapshots (project_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_geo_daily_snapshot_project_date ON geo_daily_snapshots (project_id, snapshot_date)');

        $this->addSql('CREATE TABLE backlink_sites (id UUID NOT NULL, project_id UUID NOT NULL, domain_id UUID NOT NULL, niche VARCHAR(255) DEFAULT NULL, domain_authority SMALLINT DEFAULT NULL, traffic_estimate INTEGER DEFAULT NULL, accepts_exchanges BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_80B51AC8166D1F9C ON backlink_sites (project_id)');
        $this->addSql('CREATE INDEX IDX_80B51AC8115F0EE5 ON backlink_sites (domain_id)');

        $this->addSql('CREATE TABLE backlinks (id UUID NOT NULL, source_project_id UUID NOT NULL, target_project_id UUID NOT NULL, source_url VARCHAR(1000) DEFAULT NULL, target_url VARCHAR(1000) DEFAULT NULL, anchor_text VARCHAR(500) DEFAULT NULL, context_text TEXT DEFAULT NULL, quality_score SMALLINT DEFAULT NULL, status VARCHAR(255) NOT NULL, first_detected_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, last_checked_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, removed_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_E10562B780FB8D5A ON backlinks (source_project_id)');
        $this->addSql('CREATE INDEX IDX_E10562B715890354 ON backlinks (target_project_id)');
        $this->addSql('CREATE INDEX idx_backlinks_status ON backlinks (status)');
        $this->addSql('CREATE INDEX idx_backlinks_projects ON backlinks (source_project_id, target_project_id)');

        $this->addSql('CREATE TABLE backlink_exchanges (id UUID NOT NULL, requester_project_id UUID NOT NULL, publisher_project_id UUID NOT NULL, backlink_id UUID DEFAULT NULL, status VARCHAR(255) NOT NULL, match_score SMALLINT DEFAULT NULL, requested_anchor_text VARCHAR(500) DEFAULT NULL, requested_target_url VARCHAR(1000) DEFAULT NULL, proposed_source_url VARCHAR(1000) DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, accepted_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, completed_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_C1B269D9C59520CD ON backlink_exchanges (requester_project_id)');
        $this->addSql('CREATE INDEX IDX_C1B269D990C3F6D3 ON backlink_exchanges (publisher_project_id)');
        $this->addSql('CREATE INDEX IDX_C1B269D9D8452742 ON backlink_exchanges (backlink_id)');
        $this->addSql('CREATE INDEX idx_backlink_exchanges_status ON backlink_exchanges (status)');

        $this->addSql('CREATE TABLE analytics_daily_snapshots (id UUID NOT NULL, project_id UUID NOT NULL, snapshot_date DATE NOT NULL, seo_score SMALLINT DEFAULT NULL, geo_score SMALLINT DEFAULT NULL, organic_traffic INTEGER DEFAULT NULL, backlinks_count INTEGER DEFAULT NULL, published_articles_count INTEGER DEFAULT NULL, estimated_roi NUMERIC(12, 2) DEFAULT NULL, metrics_json JSONB DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_D8B7B822166D1F9C ON analytics_daily_snapshots (project_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_analytics_daily_snapshot_project_date ON analytics_daily_snapshots (project_id, snapshot_date)');

        $this->addSql('CREATE TABLE api_keys (id UUID NOT NULL, organization_id UUID NOT NULL, name VARCHAR(180) NOT NULL, key_hash VARCHAR(255) DEFAULT NULL, scopes_json JSONB DEFAULT NULL, last_used_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, expires_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, revoked_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_5C7D94A032C8A3DE ON api_keys (organization_id)');

        $this->addSql('CREATE TABLE audit_logs (id UUID NOT NULL, organization_id UUID DEFAULT NULL, user_id UUID DEFAULT NULL, project_id UUID DEFAULT NULL, action VARCHAR(120) DEFAULT NULL, entity_type VARCHAR(120) DEFAULT NULL, entity_id UUID DEFAULT NULL, ip_address INET DEFAULT NULL, user_agent TEXT DEFAULT NULL, metadata JSONB DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_F2D3A5CA32C8A3DE ON audit_logs (organization_id)');
        $this->addSql('CREATE INDEX IDX_F2D3A5CAA76ED395 ON audit_logs (user_id)');
        $this->addSql('CREATE INDEX IDX_F2D3A5CA166D1F9C ON audit_logs (project_id)');
        $this->addSql('CREATE INDEX idx_audit_logs_organization_created ON audit_logs (organization_id, created_at)');
        $this->addSql('CREATE INDEX idx_audit_logs_project_created ON audit_logs (project_id, created_at)');

        $this->addSql('CREATE TABLE rate_limit_events (id UUID NOT NULL, organization_id UUID DEFAULT NULL, user_id UUID DEFAULT NULL, route VARCHAR(255) DEFAULT NULL, ip_address INET DEFAULT NULL, attempts INTEGER NOT NULL, window_started_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_17B4AD9032C8A3DE ON rate_limit_events (organization_id)');
        $this->addSql('CREATE INDEX IDX_17B4AD90A76ED395 ON rate_limit_events (user_id)');
        $this->addSql('CREATE INDEX idx_rate_limit_events_route_window ON rate_limit_events (route, window_started_at)');

        $this->addSql('ALTER TABLE organization_users ADD CONSTRAINT FK_93C1A67032C8A3DE FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE organization_users ADD CONSTRAINT FK_93C1A670A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE projects ADD CONSTRAINT FK_5C93B3A432C8A3DE FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE projects ADD CONSTRAINT FK_5C93B3A47E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE project_members ADD CONSTRAINT FK_252DF8F3166D1F9C FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE project_members ADD CONSTRAINT FK_252DF8F3A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE domains ADD CONSTRAINT FK_A7A91E0B166D1F9C FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE cms_connections ADD CONSTRAINT FK_653477E3166D1F9C FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE keyword_clusters ADD CONSTRAINT FK_54B878DF166D1F9C FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE keywords ADD CONSTRAINT FK_29A8C09166D1F9C FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE keywords ADD CONSTRAINT FK_29A8C09C68DF3B8 FOREIGN KEY (keyword_cluster_id) REFERENCES keyword_clusters (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE content_items ADD CONSTRAINT FK_9F6E521E166D1F9C FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE articles ADD CONSTRAINT FK_BFDD3168BF396750 FOREIGN KEY (id) REFERENCES content_items (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE articles ADD CONSTRAINT FK_BFDD3168C68DF3B8 FOREIGN KEY (keyword_cluster_id) REFERENCES keyword_clusters (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE articles ADD CONSTRAINT FK_BFDD3168F5D46435 FOREIGN KEY (primary_keyword_id) REFERENCES keywords (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE article_keywords ADD CONSTRAINT FK_5A2B626D7294869C FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE article_keywords ADD CONSTRAINT FK_5A2B626D46478CFB FOREIGN KEY (keyword_id) REFERENCES keywords (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE reports ADD CONSTRAINT FK_F11FA745BF396750 FOREIGN KEY (id) REFERENCES content_items (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE cms_publications ADD CONSTRAINT FK_26D06A337294869C FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE cms_publications ADD CONSTRAINT FK_26D06A33D70002B5 FOREIGN KEY (cms_connection_id) REFERENCES cms_connections (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE audits ADD CONSTRAINT FK_2A8938F166D1F9C FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE audits ADD CONSTRAINT FK_2A8938F115F0EE5 FOREIGN KEY (domain_id) REFERENCES domains (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE audit_pages ADD CONSTRAINT FK_56FBCA28BD29F3B6 FOREIGN KEY (audit_id) REFERENCES audits (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE audit_issues ADD CONSTRAINT FK_4C21F67DBD29F3B6 FOREIGN KEY (audit_id) REFERENCES audits (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE audit_issues ADD CONSTRAINT FK_4C21F67D1E1337D3 FOREIGN KEY (audit_page_id) REFERENCES audit_pages (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE keyword_rankings ADD CONSTRAINT FK_A94E31546478CFB FOREIGN KEY (keyword_id) REFERENCES keywords (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE keyword_rankings ADD CONSTRAINT FK_A94E315166D1F9C FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE article_images ADD CONSTRAINT FK_26F3E1D7294869C FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE geo_prompts ADD CONSTRAINT FK_F7C91D65166D1F9C FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE geo_prompts ADD CONSTRAINT FK_F7C91D6546478CFB FOREIGN KEY (keyword_id) REFERENCES keywords (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE geo_results ADD CONSTRAINT FK_5EA0D30224F4B6C5 FOREIGN KEY (geo_prompt_id) REFERENCES geo_prompts (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE geo_daily_snapshots ADD CONSTRAINT FK_736EE4BC166D1F9C FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE backlink_sites ADD CONSTRAINT FK_80B51AC8166D1F9C FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE backlink_sites ADD CONSTRAINT FK_80B51AC8115F0EE5 FOREIGN KEY (domain_id) REFERENCES domains (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE backlinks ADD CONSTRAINT FK_E10562B780FB8D5A FOREIGN KEY (source_project_id) REFERENCES projects (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE backlinks ADD CONSTRAINT FK_E10562B715890354 FOREIGN KEY (target_project_id) REFERENCES projects (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE backlink_exchanges ADD CONSTRAINT FK_C1B269D9C59520CD FOREIGN KEY (requester_project_id) REFERENCES projects (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE backlink_exchanges ADD CONSTRAINT FK_C1B269D990C3F6D3 FOREIGN KEY (publisher_project_id) REFERENCES projects (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE backlink_exchanges ADD CONSTRAINT FK_C1B269D9D8452742 FOREIGN KEY (backlink_id) REFERENCES backlinks (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE analytics_daily_snapshots ADD CONSTRAINT FK_D8B7B822166D1F9C FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE api_keys ADD CONSTRAINT FK_5C7D94A032C8A3DE FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE audit_logs ADD CONSTRAINT FK_F2D3A5CA32C8A3DE FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE audit_logs ADD CONSTRAINT FK_F2D3A5CAA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE audit_logs ADD CONSTRAINT FK_F2D3A5CA166D1F9C FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE rate_limit_events ADD CONSTRAINT FK_17B4AD9032C8A3DE FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE rate_limit_events ADD CONSTRAINT FK_17B4AD90A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS rate_limit_events');
        $this->addSql('DROP TABLE IF EXISTS audit_logs');
        $this->addSql('DROP TABLE IF EXISTS api_keys');
        $this->addSql('DROP TABLE IF EXISTS analytics_daily_snapshots');
        $this->addSql('DROP TABLE IF EXISTS backlink_exchanges');
        $this->addSql('DROP TABLE IF EXISTS backlinks');
        $this->addSql('DROP TABLE IF EXISTS backlink_sites');
        $this->addSql('DROP TABLE IF EXISTS geo_daily_snapshots');
        $this->addSql('DROP TABLE IF EXISTS geo_results');
        $this->addSql('DROP TABLE IF EXISTS geo_prompts');
        $this->addSql('DROP TABLE IF EXISTS article_images');
        $this->addSql('DROP TABLE IF EXISTS keyword_rankings');
        $this->addSql('DROP TABLE IF EXISTS audit_issues');
        $this->addSql('DROP TABLE IF EXISTS audit_pages');
        $this->addSql('DROP TABLE IF EXISTS audits');
        $this->addSql('DROP TABLE IF EXISTS cms_publications');
        $this->addSql('DROP TABLE IF EXISTS reports');
        $this->addSql('DROP TABLE IF EXISTS article_keywords');
        $this->addSql('DROP TABLE IF EXISTS articles');
        $this->addSql('DROP TABLE IF EXISTS content_items');
        $this->addSql('DROP TABLE IF EXISTS keywords');
        $this->addSql('DROP TABLE IF EXISTS keyword_clusters');
        $this->addSql('DROP TABLE IF EXISTS cms_connections');
        $this->addSql('DROP TABLE IF EXISTS domains');
        $this->addSql('DROP TABLE IF EXISTS project_members');
        $this->addSql('DROP TABLE IF EXISTS projects');
        $this->addSql('DROP TABLE IF EXISTS organization_users');
        $this->addSql('DROP TABLE IF EXISTS organizations');
        $this->addSql('DROP TABLE IF EXISTS users');
    }
}
