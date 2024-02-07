<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240206171643 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE execution_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE node_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE web_page_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE execution (id INT NOT NULL, web_page_id INT NOT NULL, status VARCHAR(255) NOT NULL, start_time TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, end_time TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, crawled_count INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_2A0D73AE4E9CD7A ON execution (web_page_id)');
        $this->addSql('COMMENT ON COLUMN execution.start_time IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN execution.end_time IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE node (id INT NOT NULL, owner_id INT NOT NULL, title VARCHAR(255) DEFAULT NULL, url VARCHAR(255) NOT NULL, crawl_time TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_857FE8457E3C61F9 ON node (owner_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_857FE845F47645AE7E3C61F9 ON node (url, owner_id)');
        $this->addSql('COMMENT ON COLUMN node.crawl_time IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE node_node (node_source INT NOT NULL, node_target INT NOT NULL, PRIMARY KEY(node_source, node_target))');
        $this->addSql('CREATE INDEX IDX_42DB65D3EB986AD6 ON node_node (node_source)');
        $this->addSql('CREATE INDEX IDX_42DB65D3F27D3A59 ON node_node (node_target)');
        $this->addSql('CREATE TABLE web_page (id INT NOT NULL, label VARCHAR(255) NOT NULL, url VARCHAR(255) NOT NULL, regexp VARCHAR(255) NOT NULL, active BOOLEAN NOT NULL, tags TEXT NOT NULL, periodicity TIME(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN web_page.tags IS \'(DC2Type:simple_array)\'');
        $this->addSql('CREATE TABLE messenger_messages (id BIGSERIAL NOT NULL, body TEXT NOT NULL, headers TEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, available_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0 ON messenger_messages (queue_name)');
        $this->addSql('CREATE INDEX IDX_75EA56E0E3BD61CE ON messenger_messages (available_at)');
        $this->addSql('CREATE INDEX IDX_75EA56E016BA31DB ON messenger_messages (delivered_at)');
        $this->addSql('COMMENT ON COLUMN messenger_messages.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN messenger_messages.available_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN messenger_messages.delivered_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE OR REPLACE FUNCTION notify_messenger_messages() RETURNS TRIGGER AS $$
            BEGIN
                PERFORM pg_notify(\'messenger_messages\', NEW.queue_name::text);
                RETURN NEW;
            END;
        $$ LANGUAGE plpgsql;');
        $this->addSql('DROP TRIGGER IF EXISTS notify_trigger ON messenger_messages;');
        $this->addSql('CREATE TRIGGER notify_trigger AFTER INSERT OR UPDATE ON messenger_messages FOR EACH ROW EXECUTE PROCEDURE notify_messenger_messages();');
        $this->addSql('ALTER TABLE execution ADD CONSTRAINT FK_2A0D73AE4E9CD7A FOREIGN KEY (web_page_id) REFERENCES web_page (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE node ADD CONSTRAINT FK_857FE8457E3C61F9 FOREIGN KEY (owner_id) REFERENCES web_page (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE node_node ADD CONSTRAINT FK_42DB65D3EB986AD6 FOREIGN KEY (node_source) REFERENCES node (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE node_node ADD CONSTRAINT FK_42DB65D3F27D3A59 FOREIGN KEY (node_target) REFERENCES node (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP SEQUENCE execution_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE node_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE web_page_id_seq CASCADE');
        $this->addSql('ALTER TABLE execution DROP CONSTRAINT FK_2A0D73AE4E9CD7A');
        $this->addSql('ALTER TABLE node DROP CONSTRAINT FK_857FE8457E3C61F9');
        $this->addSql('ALTER TABLE node_node DROP CONSTRAINT FK_42DB65D3EB986AD6');
        $this->addSql('ALTER TABLE node_node DROP CONSTRAINT FK_42DB65D3F27D3A59');
        $this->addSql('DROP TABLE execution');
        $this->addSql('DROP TABLE node');
        $this->addSql('DROP TABLE node_node');
        $this->addSql('DROP TABLE web_page');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
