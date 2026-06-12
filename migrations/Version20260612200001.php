<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260612200001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align subscription and analysis quota foreign-key index names with Doctrine metadata.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER INDEX IDX_39DD1B15A76ED395 RENAME TO IDX_3DBF66D4A76ED395');
        $this->addSql('ALTER INDEX IDX_39DD1B15166D1F9C RENAME TO IDX_3DBF66D4166D1F9C');
        $this->addSql('ALTER INDEX IDX_39DD1B15BD29F3B6 RENAME TO IDX_3DBF66D4BD29F359');
        $this->addSql('ALTER INDEX IDX_39DD1B159A1887DC RENAME TO IDX_3DBF66D49A1887DC');
        $this->addSql('ALTER INDEX IDX_A3C664D3A76ED395 RENAME TO IDX_4778A01A76ED395');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER INDEX IDX_3DBF66D4A76ED395 RENAME TO IDX_39DD1B15A76ED395');
        $this->addSql('ALTER INDEX IDX_3DBF66D4166D1F9C RENAME TO IDX_39DD1B15166D1F9C');
        $this->addSql('ALTER INDEX IDX_3DBF66D4BD29F359 RENAME TO IDX_39DD1B15BD29F3B6');
        $this->addSql('ALTER INDEX IDX_3DBF66D49A1887DC RENAME TO IDX_39DD1B159A1887DC');
        $this->addSql('ALTER INDEX IDX_4778A01A76ED395 RENAME TO IDX_A3C664D3A76ED395');
    }
}
