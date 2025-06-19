<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250619020144 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE comment (id INT AUTO_INCREMENT NOT NULL, lot_unit_id INT DEFAULT NULL, content LONGTEXT NOT NULL, INDEX IDX_9474526C553D362E (lot_unit_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE item_custom_field (id INT AUTO_INCREMENT NOT NULL, item_id INT NOT NULL, field_name VARCHAR(100) NOT NULL, field_value LONGTEXT DEFAULT NULL, field_type VARCHAR(50) NOT NULL, INDEX IDX_57A240F0126F525E (item_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE lot_unit (id INT AUTO_INCREMENT NOT NULL, lot_group_id INT NOT NULL, sold_at DATETIME DEFAULT NULL, actual_sell_price INT DEFAULT NULL, notes LONGTEXT DEFAULT NULL, INDEX IDX_489792135C9E2873 (lot_group_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE market_watch (id INT AUTO_INCREMENT NOT NULL, dofus_character_id INT NOT NULL, item_id INT NOT NULL, lot_size INT NOT NULL, observed_price INT NOT NULL, price_type VARCHAR(50) NOT NULL, notes LONGTEXT DEFAULT NULL, INDEX IDX_9B25F553B3033844 (dofus_character_id), INDEX IDX_9B25F553126F525E (item_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE comment ADD CONSTRAINT FK_9474526C553D362E FOREIGN KEY (lot_unit_id) REFERENCES lot_unit (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE item_custom_field ADD CONSTRAINT FK_57A240F0126F525E FOREIGN KEY (item_id) REFERENCES item (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE lot_unit ADD CONSTRAINT FK_489792135C9E2873 FOREIGN KEY (lot_group_id) REFERENCES lot_group (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE market_watch ADD CONSTRAINT FK_9B25F553B3033844 FOREIGN KEY (dofus_character_id) REFERENCES dofus_character (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE market_watch ADD CONSTRAINT FK_9B25F553126F525E FOREIGN KEY (item_id) REFERENCES item (id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE comment DROP FOREIGN KEY FK_9474526C553D362E
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE item_custom_field DROP FOREIGN KEY FK_57A240F0126F525E
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE lot_unit DROP FOREIGN KEY FK_489792135C9E2873
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE market_watch DROP FOREIGN KEY FK_9B25F553B3033844
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE market_watch DROP FOREIGN KEY FK_9B25F553126F525E
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE comment
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE item_custom_field
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE lot_unit
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE market_watch
        SQL);
    }
}
