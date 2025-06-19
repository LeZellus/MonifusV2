<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250619021558 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE item CHANGE item_type item_type VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE item_custom_field CHANGE field_type field_type VARCHAR(255) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE lot_group CHANGE buy_price_per_lot buy_price_per_lot BIGINT NOT NULL, CHANGE sell_price_per_lot sell_price_per_lot BIGINT NOT NULL, CHANGE status status VARCHAR(255) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE market_watch CHANGE observed_price observed_price BIGINT NOT NULL, CHANGE price_type price_type VARCHAR(255) NOT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE lot_group CHANGE buy_price_per_lot buy_price_per_lot INT NOT NULL, CHANGE sell_price_per_lot sell_price_per_lot INT NOT NULL, CHANGE status status VARCHAR(50) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE market_watch CHANGE observed_price observed_price INT NOT NULL, CHANGE price_type price_type VARCHAR(50) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE item CHANGE item_type item_type VARCHAR(50) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE item_custom_field CHANGE field_type field_type VARCHAR(50) NOT NULL
        SQL);
    }
}
