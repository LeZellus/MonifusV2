<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250703215211 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE lot_unit CHANGE actual_sell_price actual_sell_price BIGINT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE market_watch ADD price_per1000 BIGINT DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE market_watch DROP price_per1000
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE lot_unit CHANGE actual_sell_price actual_sell_price INT DEFAULT NULL
        SQL);
    }
}
