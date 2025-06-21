<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250621033405 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE classe (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, img_url VARCHAR(500) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE comment (id INT AUTO_INCREMENT NOT NULL, lot_unit_id INT DEFAULT NULL, content LONGTEXT NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_9474526C553D362E (lot_unit_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE dofus_character (id INT AUTO_INCREMENT NOT NULL, trading_profile_id INT NOT NULL, server_id INT NOT NULL, classe_id INT NOT NULL, name VARCHAR(100) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_8D8BA67245A9E0D (trading_profile_id), INDEX IDX_8D8BA6721844E6B7 (server_id), INDEX IDX_8D8BA6728F5EA509 (classe_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE item (id INT AUTO_INCREMENT NOT NULL, ankama_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, item_type VARCHAR(255) DEFAULT NULL, level INT DEFAULT NULL, img_url VARCHAR(500) DEFAULT NULL, xp_pet DOUBLE PRECISION DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE item_custom_field (id INT AUTO_INCREMENT NOT NULL, item_id INT NOT NULL, field_name VARCHAR(100) NOT NULL, field_value LONGTEXT DEFAULT NULL, field_type VARCHAR(255) NOT NULL, INDEX IDX_57A240F0126F525E (item_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE lot_group (id INT AUTO_INCREMENT NOT NULL, dofus_character_id INT NOT NULL, item_id INT NOT NULL, lot_size INT NOT NULL, buy_price_per_lot BIGINT NOT NULL, sell_price_per_lot BIGINT DEFAULT NULL, status VARCHAR(255) NOT NULL, sale_unit INT NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_1B8829CBB3033844 (dofus_character_id), INDEX IDX_1B8829CB126F525E (item_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE lot_unit (id INT AUTO_INCREMENT NOT NULL, lot_group_id INT NOT NULL, sold_at DATETIME DEFAULT NULL, actual_sell_price INT DEFAULT NULL, quantity_sold INT NOT NULL, notes LONGTEXT DEFAULT NULL, INDEX IDX_489792135C9E2873 (lot_group_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE market_watch (id INT AUTO_INCREMENT NOT NULL, item_id INT NOT NULL, dofus_character_id INT NOT NULL, price_per_unit BIGINT DEFAULT NULL, price_per10 BIGINT DEFAULT NULL, price_per100 BIGINT DEFAULT NULL, notes LONGTEXT DEFAULT NULL, observed_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_9B25F553126F525E (item_id), INDEX IDX_9B25F553B3033844 (dofus_character_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE server (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, community VARCHAR(50) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE trading_profile (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, name VARCHAR(100) NOT NULL, description LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_E44B7B64A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, pseudonyme_website VARCHAR(100) DEFAULT NULL, pseudonyme_dofus VARCHAR(100) DEFAULT NULL, is_verified TINYINT(1) DEFAULT NULL, profile_picture VARCHAR(500) DEFAULT NULL, cover_picture VARCHAR(500) DEFAULT NULL, discord_id VARCHAR(255) DEFAULT NULL, discord_username VARCHAR(100) DEFAULT NULL, discord_avatar VARCHAR(255) DEFAULT NULL, is_tutorial TINYINT(1) DEFAULT NULL, contact VARCHAR(255) DEFAULT NULL, description LONGTEXT DEFAULT NULL, youtube_url VARCHAR(100) DEFAULT NULL, twitter_url VARCHAR(100) DEFAULT NULL, ankama_url VARCHAR(100) DEFAULT NULL, twitch_url VARCHAR(100) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', available_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', delivered_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE comment ADD CONSTRAINT FK_9474526C553D362E FOREIGN KEY (lot_unit_id) REFERENCES lot_unit (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE dofus_character ADD CONSTRAINT FK_8D8BA67245A9E0D FOREIGN KEY (trading_profile_id) REFERENCES trading_profile (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE dofus_character ADD CONSTRAINT FK_8D8BA6721844E6B7 FOREIGN KEY (server_id) REFERENCES server (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE dofus_character ADD CONSTRAINT FK_8D8BA6728F5EA509 FOREIGN KEY (classe_id) REFERENCES classe (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE item_custom_field ADD CONSTRAINT FK_57A240F0126F525E FOREIGN KEY (item_id) REFERENCES item (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE lot_group ADD CONSTRAINT FK_1B8829CBB3033844 FOREIGN KEY (dofus_character_id) REFERENCES dofus_character (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE lot_group ADD CONSTRAINT FK_1B8829CB126F525E FOREIGN KEY (item_id) REFERENCES item (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE lot_unit ADD CONSTRAINT FK_489792135C9E2873 FOREIGN KEY (lot_group_id) REFERENCES lot_group (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE market_watch ADD CONSTRAINT FK_9B25F553126F525E FOREIGN KEY (item_id) REFERENCES item (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE market_watch ADD CONSTRAINT FK_9B25F553B3033844 FOREIGN KEY (dofus_character_id) REFERENCES dofus_character (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE trading_profile ADD CONSTRAINT FK_E44B7B64A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE comment DROP FOREIGN KEY FK_9474526C553D362E
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE dofus_character DROP FOREIGN KEY FK_8D8BA67245A9E0D
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE dofus_character DROP FOREIGN KEY FK_8D8BA6721844E6B7
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE dofus_character DROP FOREIGN KEY FK_8D8BA6728F5EA509
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE item_custom_field DROP FOREIGN KEY FK_57A240F0126F525E
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE lot_group DROP FOREIGN KEY FK_1B8829CBB3033844
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE lot_group DROP FOREIGN KEY FK_1B8829CB126F525E
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE lot_unit DROP FOREIGN KEY FK_489792135C9E2873
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE market_watch DROP FOREIGN KEY FK_9B25F553126F525E
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE market_watch DROP FOREIGN KEY FK_9B25F553B3033844
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE trading_profile DROP FOREIGN KEY FK_E44B7B64A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE classe
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE comment
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE dofus_character
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE item
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE item_custom_field
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE lot_group
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE lot_unit
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE market_watch
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE server
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE trading_profile
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE user
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE messenger_messages
        SQL);
    }
}
