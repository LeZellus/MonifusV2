<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260115160248 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ON DELETE CASCADE to foreign key constraints';
    }

    public function up(Schema $schema): void
    {
        // Add ON DELETE CASCADE to comment.lot_unit_id
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526C553D362E');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526C553D362E FOREIGN KEY (lot_unit_id) REFERENCES lot_unit (id) ON DELETE CASCADE');

        // Add ON DELETE CASCADE to lot_unit.lot_group_id
        $this->addSql('ALTER TABLE lot_unit DROP FOREIGN KEY FK_489792135C9E2873');
        $this->addSql('ALTER TABLE lot_unit ADD CONSTRAINT FK_489792135C9E2873 FOREIGN KEY (lot_group_id) REFERENCES lot_group (id) ON DELETE CASCADE');

        // Add ON DELETE CASCADE to lot_group.dofus_character_id
        $this->addSql('ALTER TABLE lot_group DROP FOREIGN KEY FK_1B8829CBB3033844');
        $this->addSql('ALTER TABLE lot_group ADD CONSTRAINT FK_1B8829CBB3033844 FOREIGN KEY (dofus_character_id) REFERENCES dofus_character (id) ON DELETE CASCADE');

        // Add ON DELETE CASCADE to dofus_character.trading_profile_id
        $this->addSql('ALTER TABLE dofus_character DROP FOREIGN KEY FK_8D8BA67245A9E0D');
        $this->addSql('ALTER TABLE dofus_character ADD CONSTRAINT FK_8D8BA67245A9E0D FOREIGN KEY (trading_profile_id) REFERENCES trading_profile (id) ON DELETE CASCADE');

        // Add ON DELETE CASCADE to trading_profile.user_id
        $this->addSql('ALTER TABLE trading_profile DROP FOREIGN KEY FK_E44B7B64A76ED395');
        $this->addSql('ALTER TABLE trading_profile ADD CONSTRAINT FK_E44B7B64A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // Remove ON DELETE CASCADE from foreign keys
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526C553D362E');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526C553D362E FOREIGN KEY (lot_unit_id) REFERENCES lot_unit (id)');

        $this->addSql('ALTER TABLE lot_unit DROP FOREIGN KEY FK_489792135C9E2873');
        $this->addSql('ALTER TABLE lot_unit ADD CONSTRAINT FK_489792135C9E2873 FOREIGN KEY (lot_group_id) REFERENCES lot_group (id)');

        $this->addSql('ALTER TABLE lot_group DROP FOREIGN KEY FK_1B8829CBB3033844');
        $this->addSql('ALTER TABLE lot_group ADD CONSTRAINT FK_1B8829CBB3033844 FOREIGN KEY (dofus_character_id) REFERENCES dofus_character (id)');

        $this->addSql('ALTER TABLE dofus_character DROP FOREIGN KEY FK_8D8BA67245A9E0D');
        $this->addSql('ALTER TABLE dofus_character ADD CONSTRAINT FK_8D8BA67245A9E0D FOREIGN KEY (trading_profile_id) REFERENCES trading_profile (id)');

        $this->addSql('ALTER TABLE trading_profile DROP FOREIGN KEY FK_E44B7B64A76ED395');
        $this->addSql('ALTER TABLE trading_profile ADD CONSTRAINT FK_E44B7B64A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
    }
}
