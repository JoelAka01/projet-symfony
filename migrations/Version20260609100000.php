<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260609100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add account profile, email verification, and password reset token fields.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE users ADD first_name VARCHAR(100) DEFAULT '' NOT NULL");
        $this->addSql("ALTER TABLE users ADD last_name VARCHAR(100) DEFAULT '' NOT NULL");
        $this->addSql('ALTER TABLE users ADD is_verified BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE users ADD email_verification_token_hash VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD email_verification_token_expires_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD password_reset_token_hash VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD password_reset_token_expires_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL');
        $this->addSql('UPDATE users SET is_verified = true');
        $this->addSql('ALTER TABLE users ALTER first_name DROP DEFAULT');
        $this->addSql('ALTER TABLE users ALTER last_name DROP DEFAULT');
        $this->addSql('ALTER TABLE users ALTER is_verified DROP DEFAULT');
        $this->addSql('CREATE INDEX idx_users_email_verification_token_hash ON users (email_verification_token_hash)');
        $this->addSql('CREATE INDEX idx_users_password_reset_token_hash ON users (password_reset_token_hash)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_users_email_verification_token_hash');
        $this->addSql('DROP INDEX IF EXISTS idx_users_password_reset_token_hash');
        $this->addSql('ALTER TABLE users DROP email_verification_token_hash');
        $this->addSql('ALTER TABLE users DROP email_verification_token_expires_at');
        $this->addSql('ALTER TABLE users DROP password_reset_token_hash');
        $this->addSql('ALTER TABLE users DROP password_reset_token_expires_at');
        $this->addSql('ALTER TABLE users DROP is_verified');
        $this->addSql('ALTER TABLE users DROP first_name');
        $this->addSql('ALTER TABLE users DROP last_name');
    }
}
