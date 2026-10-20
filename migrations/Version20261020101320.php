<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261020101320 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create source table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE source (id UUID NOT NULL, name VARCHAR(255) NOT NULL, type VARCHAR(32) NOT NULL, url VARCHAR(2048) NOT NULL, fetch_interval_minutes INT NOT NULL, enabled BOOLEAN NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX source_url_type_unique ON source (url, type)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE source');
    }
}
