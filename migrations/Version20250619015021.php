<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250619015021 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE dofus_character (id INT AUTO_INCREMENT NOT NULL, trading_profile_id INT NOT NULL, server_id INT NOT NULL, classe_id INT NOT NULL, name VARCHAR(100) NOT NULL, INDEX IDX_8D8BA67245A9E0D (trading_profile_id), INDEX IDX_8D8BA6721844E6B7 (server_id), INDEX IDX_8D8BA6728F5EA509 (classe_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE lot_group (id INT AUTO_INCREMENT NOT NULL, dofus_character_id INT NOT NULL, item_id INT NOT NULL, lot_size INT NOT NULL, buy_price_per_lot INT NOT NULL, sell_price_per_lot INT NOT NULL, status VARCHAR(50) NOT NULL, INDEX IDX_1B8829CBB3033844 (dofus_character_id), INDEX IDX_1B8829CB126F525E (item_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE trading_profile (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, name VARCHAR(100) NOT NULL, description LONGTEXT DEFAULT NULL, INDEX IDX_E44B7B64A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
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
            ALTER TABLE lot_group ADD CONSTRAINT FK_1B8829CBB3033844 FOREIGN KEY (dofus_character_id) REFERENCES dofus_character (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE lot_group ADD CONSTRAINT FK_1B8829CB126F525E FOREIGN KEY (item_id) REFERENCES item (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE trading_profile ADD CONSTRAINT FK_E44B7B64A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
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
            ALTER TABLE lot_group DROP FOREIGN KEY FK_1B8829CBB3033844
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE lot_group DROP FOREIGN KEY FK_1B8829CB126F525E
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE trading_profile DROP FOREIGN KEY FK_E44B7B64A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE dofus_character
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE lot_group
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE trading_profile
        SQL);
    }
}
