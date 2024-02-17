<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240216174230 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE web_page ALTER url TYPE TEXT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE web_page ALTER url TYPE VARCHAR(255)');
    }
}
