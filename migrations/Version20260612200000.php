<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260612200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add subscription plans, simulated payments, and persisted analysis quota reservations.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE subscriptions (id UUID NOT NULL, user_id UUID NOT NULL, plan VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL, monthly_price_cents INTEGER NOT NULL, monthly_credit_limit INTEGER NOT NULL, weekly_analysis_limit INTEGER NOT NULL, starts_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, ends_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_A3C664D3A76ED395 ON subscriptions (user_id)');
        $this->addSql('CREATE INDEX idx_subscriptions_user_status ON subscriptions (user_id, status)');
        $this->addSql('CREATE INDEX idx_subscriptions_ends_at ON subscriptions (ends_at)');

        $this->addSql('CREATE TABLE payments (id UUID NOT NULL, user_id UUID DEFAULT NULL, subscription_id UUID DEFAULT NULL, plan VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL, amount_cents INTEGER NOT NULL, currency VARCHAR(3) NOT NULL, card_last_four VARCHAR(4) NOT NULL, simulated BOOLEAN NOT NULL, admin_note TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, paid_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_65D29B32A76ED395 ON payments (user_id)');
        $this->addSql('CREATE INDEX IDX_65D29B329A1887DC ON payments (subscription_id)');
        $this->addSql('CREATE INDEX idx_payments_status_created ON payments (status, created_at)');
        $this->addSql('CREATE INDEX idx_payments_user_created ON payments (user_id, created_at)');

        $this->addSql('CREATE TABLE analysis_quota_usages (id UUID NOT NULL, user_id UUID NOT NULL, project_id UUID NOT NULL, audit_id UUID NOT NULL, subscription_id UUID DEFAULT NULL, plan_code VARCHAR(20) NOT NULL, ip_hash VARCHAR(64) DEFAULT NULL, credits_charged INTEGER NOT NULL, status VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, finalized_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_39DD1B15A76ED395 ON analysis_quota_usages (user_id)');
        $this->addSql('CREATE INDEX IDX_39DD1B15166D1F9C ON analysis_quota_usages (project_id)');
        $this->addSql('CREATE INDEX IDX_39DD1B15BD29F3B6 ON analysis_quota_usages (audit_id)');
        $this->addSql('CREATE INDEX IDX_39DD1B159A1887DC ON analysis_quota_usages (subscription_id)');
        $this->addSql('CREATE INDEX idx_analysis_quota_user_created ON analysis_quota_usages (user_id, created_at)');
        $this->addSql('CREATE INDEX idx_analysis_quota_ip_created ON analysis_quota_usages (ip_hash, created_at)');
        $this->addSql('CREATE INDEX idx_analysis_quota_audit_status ON analysis_quota_usages (audit_id, status)');

        $this->addSql('ALTER TABLE subscriptions ADD CONSTRAINT FK_A3C664D3A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE payments ADD CONSTRAINT FK_65D29B32A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE payments ADD CONSTRAINT FK_65D29B329A1887DC FOREIGN KEY (subscription_id) REFERENCES subscriptions (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE analysis_quota_usages ADD CONSTRAINT FK_39DD1B15A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE analysis_quota_usages ADD CONSTRAINT FK_39DD1B15166D1F9C FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE analysis_quota_usages ADD CONSTRAINT FK_39DD1B15BD29F3B6 FOREIGN KEY (audit_id) REFERENCES audits (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE analysis_quota_usages ADD CONSTRAINT FK_39DD1B159A1887DC FOREIGN KEY (subscription_id) REFERENCES subscriptions (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql(<<<'SQL'
            INSERT INTO analysis_quota_usages (
                id,
                user_id,
                project_id,
                audit_id,
                subscription_id,
                plan_code,
                ip_hash,
                credits_charged,
                status,
                created_at,
                finalized_at
            )
            SELECT
                audits.id,
                audits.requested_by_id,
                audits.project_id,
                audits.id,
                NULL,
                'FREE',
                NULL,
                CASE WHEN audits.status = 'FAILED' THEN 0 ELSE 40000 END,
                CASE
                    WHEN audits.status IN ('QUEUED', 'RUNNING') THEN 'RESERVED'
                    WHEN audits.status = 'COMPLETED' THEN 'CONSUMED'
                    ELSE 'RELEASED'
                END,
                audits.created_at,
                CASE WHEN audits.status IN ('QUEUED', 'RUNNING') THEN NULL ELSE COALESCE(audits.crawl_finished_at, audits.created_at) END
            FROM audits
            WHERE audits.requested_by_id IS NOT NULL
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE analysis_quota_usages');
        $this->addSql('DROP TABLE payments');
        $this->addSql('DROP TABLE subscriptions');
    }
}
