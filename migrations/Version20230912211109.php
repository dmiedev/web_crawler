<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230912211109 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE node ALTER title DROP NOT NULL');
        $this->addSql('ALTER TABLE node ALTER crawl_time DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE node ALTER title SET NOT NULL');
        $this->addSql('ALTER TABLE node ALTER crawl_time SET NOT NULL');
    }
}
