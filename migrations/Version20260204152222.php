<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add buyUnit field to lot_group table for purchase unit (separate from sale unit)
 */
final class Version20260204152222 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add buyUnit field to lot_group table';
    }

    public function up(Schema $schema): void
    {
        // Add buyUnit column with default value matching existing saleUnit for backward compatibility
        $this->addSql(<<<'SQL'
            ALTER TABLE lot_group ADD buy_unit VARCHAR(255) NOT NULL DEFAULT '1'
        SQL);

        // Copy existing saleUnit values to buyUnit for existing records
        $this->addSql(<<<'SQL'
            UPDATE lot_group SET buy_unit = sale_unit
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE lot_group DROP buy_unit
        SQL);
    }
}
