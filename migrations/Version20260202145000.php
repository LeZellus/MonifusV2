<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260202145000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add missing updated_at column to comment table';
    }

    public function up(Schema $schema): void
    {
        // Check if column already exists
        $schemaManager = $this->connection->createSchemaManager();
        $columns = $schemaManager->listTableColumns('comment');

        if (!isset($columns['updated_at'])) {
            $this->addSql(<<<'SQL'
                ALTER TABLE comment ADD updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '(DC2Type:datetime_immutable)'
            SQL);

            // Update existing rows to copy created_at value
            $this->addSql(<<<'SQL'
                UPDATE comment SET updated_at = created_at
            SQL);
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE comment DROP COLUMN updated_at
        SQL);
    }
}
