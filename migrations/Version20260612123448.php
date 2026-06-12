<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260612123448 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER INDEX idx_d8b7b822166d1f9c RENAME TO IDX_781EE0B166D1F9C');
        $this->addSql('ALTER INDEX idx_5c7d94a032c8a3de RENAME TO IDX_9579321F32C8A3DE');
        $this->addSql('ALTER INDEX idx_26f3e1d7294869c RENAME TO IDX_8AD829EA7294869C');
        $this->addSql('ALTER INDEX idx_bfdd3168c68df3b8 RENAME TO IDX_BFDD3168851F705B');
        $this->addSql('ALTER INDEX idx_bfdd3168f5d46435 RENAME TO IDX_BFDD31682FF9A934');
        $this->addSql('ALTER INDEX idx_5a2b626d7294869c RENAME TO IDX_FFB741357294869C');
        $this->addSql('ALTER INDEX idx_5a2b626d46478cfb RENAME TO IDX_FFB74135115D4552');
        $this->addSql('ALTER INDEX idx_4c21f67dbd29f3b6 RENAME TO IDX_F9A35358BD29F359');
        $this->addSql('ALTER INDEX idx_4c21f67d1e1337d3 RENAME TO IDX_F9A353581D64B694');
        $this->addSql('ALTER TABLE audit_logs ALTER ip_address TYPE VARCHAR(255)');
        $this->addSql('ALTER INDEX idx_f2d3a5ca32c8a3de RENAME TO IDX_D62F285832C8A3DE');
        $this->addSql('ALTER INDEX idx_f2d3a5caa76ed395 RENAME TO IDX_D62F2858A76ED395');
        $this->addSql('ALTER INDEX idx_f2d3a5ca166d1f9c RENAME TO IDX_D62F2858166D1F9C');
        $this->addSql('ALTER INDEX idx_56fbca28bd29f3b6 RENAME TO IDX_273F8182BD29F359');
        $this->addSql('ALTER INDEX idx_2a8938f166d1f9c RENAME TO IDX_32451E6C166D1F9C');
        $this->addSql('ALTER INDEX idx_2a8938f115f0ee5 RENAME TO IDX_32451E6C115F0EE5');
        $this->addSql('ALTER INDEX idx_c1b269d9c59520cd RENAME TO IDX_FCCEBE2B8B0955DD');
        $this->addSql('ALTER INDEX idx_c1b269d990c3f6d3 RENAME TO IDX_FCCEBE2BB22FA08');
        $this->addSql('ALTER INDEX idx_c1b269d9d8452742 RENAME TO IDX_FCCEBE2B85BD2DD8');
        $this->addSql('ALTER INDEX idx_80b51ac8166d1f9c RENAME TO IDX_B36500D7166D1F9C');
        $this->addSql('ALTER INDEX idx_80b51ac8115f0ee5 RENAME TO IDX_B36500D7115F0EE5');
        $this->addSql('ALTER INDEX idx_e10562b780fb8d5a RENAME TO IDX_4710E24D6AD96EB8');
        $this->addSql('ALTER INDEX idx_e10562b715890354 RENAME TO IDX_4710E24D2481C70D');
        $this->addSql('ALTER INDEX idx_653477e3166d1f9c RENAME TO IDX_22174BC1166D1F9C');
        $this->addSql('ALTER INDEX idx_26d06a337294869c RENAME TO IDX_B35BCD8E7294869C');
        $this->addSql('ALTER INDEX idx_26d06a33d70002b5 RENAME TO IDX_B35BCD8EBD8184D4');
        $this->addSql('ALTER INDEX idx_9f6e521e166d1f9c RENAME TO IDX_ADDBD9F166D1F9C');
        $this->addSql('ALTER INDEX idx_a7a91e0b166d1f9c RENAME TO IDX_8C7BBF9D166D1F9C');
        $this->addSql('ALTER INDEX idx_736ee4bc166d1f9c RENAME TO IDX_1019E6EA166D1F9C');
        $this->addSql('ALTER INDEX idx_f7c91d65166d1f9c RENAME TO IDX_847815FB166D1F9C');
        $this->addSql('ALTER INDEX idx_f7c91d6546478cfb RENAME TO IDX_847815FB115D4552');
        $this->addSql('ALTER INDEX idx_5ea0d30224f4b6c5 RENAME TO IDX_28B5691614FB8031');
        $this->addSql('DROP INDEX idx_54b878df166d1f9c');
        $this->addSql('ALTER INDEX idx_a94e31546478cfb RENAME TO IDX_C3668B7115D4552');
        $this->addSql('ALTER INDEX idx_a94e315166d1f9c RENAME TO IDX_C3668B7166D1F9C');
        $this->addSql('DROP INDEX idx_29a8c09c68df3b8');
        $this->addSql('ALTER INDEX idx_29a8c09166d1f9c RENAME TO IDX_AA5FB55E166D1F9C');
        $this->addSql('ALTER INDEX idx_93c1a67032c8a3de RENAME TO IDX_9A04432E32C8A3DE');
        $this->addSql('ALTER INDEX idx_93c1a670a76ed395 RENAME TO IDX_9A04432EA76ED395');
        $this->addSql('DELETE FROM projects WHERE id IN (SELECT id FROM (SELECT id, ROW_NUMBER() OVER (PARTITION BY owner_id, name ORDER BY created_at DESC) as row_num FROM projects) t WHERE t.row_num > 1)');
        $this->addSql('CREATE UNIQUE INDEX uniq_projects_owner_name ON projects (owner_id, name)');
        $this->addSql('ALTER INDEX idx_252df8f3166d1f9c RENAME TO IDX_D3BEDE9A166D1F9C');
        $this->addSql('ALTER INDEX idx_252df8f3a76ed395 RENAME TO IDX_D3BEDE9AA76ED395');
        $this->addSql('ALTER TABLE rate_limit_events ALTER ip_address TYPE VARCHAR(255)');
        $this->addSql('ALTER INDEX idx_17b4ad9032c8a3de RENAME TO IDX_8930CE0632C8A3DE');
        $this->addSql('ALTER INDEX idx_17b4ad90a76ed395 RENAME TO IDX_8930CE06A76ED395');
        $this->addSql('DROP INDEX idx_users_password_reset_token_hash');
        $this->addSql('DROP INDEX idx_users_email_verification_token_hash');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER INDEX idx_781ee0b166d1f9c RENAME TO idx_d8b7b822166d1f9c');
        $this->addSql('ALTER INDEX idx_9579321f32c8a3de RENAME TO idx_5c7d94a032c8a3de');
        $this->addSql('ALTER INDEX idx_8ad829ea7294869c RENAME TO idx_26f3e1d7294869c');
        $this->addSql('ALTER INDEX idx_ffb741357294869c RENAME TO idx_5a2b626d7294869c');
        $this->addSql('ALTER INDEX idx_ffb74135115d4552 RENAME TO idx_5a2b626d46478cfb');
        $this->addSql('ALTER INDEX idx_bfdd31682ff9a934 RENAME TO idx_bfdd3168f5d46435');
        $this->addSql('ALTER INDEX idx_bfdd3168851f705b RENAME TO idx_bfdd3168c68df3b8');
        $this->addSql('ALTER INDEX idx_f9a35358bd29f359 RENAME TO idx_4c21f67dbd29f3b6');
        $this->addSql('ALTER INDEX idx_f9a353581d64b694 RENAME TO idx_4c21f67d1e1337d3');
        $this->addSql('ALTER TABLE audit_logs ALTER ip_address TYPE VARCHAR');
        $this->addSql('ALTER INDEX idx_d62f2858166d1f9c RENAME TO idx_f2d3a5ca166d1f9c');
        $this->addSql('ALTER INDEX idx_d62f2858a76ed395 RENAME TO idx_f2d3a5caa76ed395');
        $this->addSql('ALTER INDEX idx_d62f285832c8a3de RENAME TO idx_f2d3a5ca32c8a3de');
        $this->addSql('ALTER INDEX idx_273f8182bd29f359 RENAME TO idx_56fbca28bd29f3b6');
        $this->addSql('ALTER INDEX idx_32451e6c166d1f9c RENAME TO idx_2a8938f166d1f9c');
        $this->addSql('ALTER INDEX idx_32451e6c115f0ee5 RENAME TO idx_2a8938f115f0ee5');
        $this->addSql('ALTER INDEX idx_fccebe2b85bd2dd8 RENAME TO idx_c1b269d9d8452742');
        $this->addSql('ALTER INDEX idx_fccebe2b8b0955dd RENAME TO idx_c1b269d9c59520cd');
        $this->addSql('ALTER INDEX idx_fccebe2bb22fa08 RENAME TO idx_c1b269d990c3f6d3');
        $this->addSql('ALTER INDEX idx_b36500d7166d1f9c RENAME TO idx_80b51ac8166d1f9c');
        $this->addSql('ALTER INDEX idx_b36500d7115f0ee5 RENAME TO idx_80b51ac8115f0ee5');
        $this->addSql('ALTER INDEX idx_4710e24d6ad96eb8 RENAME TO idx_e10562b780fb8d5a');
        $this->addSql('ALTER INDEX idx_4710e24d2481c70d RENAME TO idx_e10562b715890354');
        $this->addSql('ALTER INDEX idx_22174bc1166d1f9c RENAME TO idx_653477e3166d1f9c');
        $this->addSql('ALTER INDEX idx_b35bcd8ebd8184d4 RENAME TO idx_26d06a33d70002b5');
        $this->addSql('ALTER INDEX idx_b35bcd8e7294869c RENAME TO idx_26d06a337294869c');
        $this->addSql('ALTER INDEX idx_addbd9f166d1f9c RENAME TO idx_9f6e521e166d1f9c');
        $this->addSql('ALTER INDEX idx_8c7bbf9d166d1f9c RENAME TO idx_a7a91e0b166d1f9c');
        $this->addSql('ALTER INDEX idx_1019e6ea166d1f9c RENAME TO idx_736ee4bc166d1f9c');
        $this->addSql('ALTER INDEX idx_847815fb166d1f9c RENAME TO idx_f7c91d65166d1f9c');
        $this->addSql('ALTER INDEX idx_847815fb115d4552 RENAME TO idx_f7c91d6546478cfb');
        $this->addSql('ALTER INDEX idx_28b5691614fb8031 RENAME TO idx_5ea0d30224f4b6c5');
        $this->addSql('CREATE INDEX idx_54b878df166d1f9c ON keyword_clusters (project_id)');
        $this->addSql('ALTER INDEX idx_c3668b7166d1f9c RENAME TO idx_a94e315166d1f9c');
        $this->addSql('ALTER INDEX idx_c3668b7115d4552 RENAME TO idx_a94e31546478cfb');
        $this->addSql('CREATE INDEX idx_29a8c09c68df3b8 ON keywords (keyword_cluster_id)');
        $this->addSql('ALTER INDEX idx_aa5fb55e166d1f9c RENAME TO idx_29a8c09166d1f9c');
        $this->addSql('ALTER INDEX idx_9a04432e32c8a3de RENAME TO idx_93c1a67032c8a3de');
        $this->addSql('ALTER INDEX idx_9a04432ea76ed395 RENAME TO idx_93c1a670a76ed395');
        $this->addSql('ALTER INDEX idx_d3bede9aa76ed395 RENAME TO idx_252df8f3a76ed395');
        $this->addSql('ALTER INDEX idx_d3bede9a166d1f9c RENAME TO idx_252df8f3166d1f9c');
        $this->addSql('DROP INDEX uniq_projects_owner_name');
        $this->addSql('ALTER TABLE rate_limit_events ALTER ip_address TYPE VARCHAR');
        $this->addSql('ALTER INDEX idx_8930ce0632c8a3de RENAME TO idx_17b4ad9032c8a3de');
        $this->addSql('ALTER INDEX idx_8930ce06a76ed395 RENAME TO idx_17b4ad90a76ed395');
        $this->addSql('CREATE INDEX idx_users_password_reset_token_hash ON users (password_reset_token_hash)');
        $this->addSql('CREATE INDEX idx_users_email_verification_token_hash ON users (email_verification_token_hash)');
    }
}
