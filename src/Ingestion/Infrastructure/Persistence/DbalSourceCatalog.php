<?php

declare(strict_types=1);

namespace App\Ingestion\Infrastructure\Persistence;

use App\Ingestion\Domain\Exception\SourceNotIngestible;
use App\Ingestion\Domain\Model\SourceToIngest;
use App\Ingestion\Domain\Port\SourceCatalog;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;

final class DbalSourceCatalog implements SourceCatalog
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function dueForIngestion(\DateTimeImmutable $now): array
    {
        /** @var list<string> $ids */
        $ids = $this->connection->fetchFirstColumn(
            <<<'SQL'
                SELECT s.id FROM source s
                LEFT JOIN source_ingestion i ON i.source_id = s.id
                WHERE s.enabled
                  AND (i.last_ingested_at IS NULL OR i.last_ingested_at + s.fetch_interval_minutes * INTERVAL '1 minute' <= :now)
                ORDER BY s.id
                SQL,
            ['now' => $now],
            ['now' => Types::DATETIMETZ_IMMUTABLE],
        );

        return $ids;
    }

    public function get(string $sourceId): SourceToIngest
    {
        /** @var array{id: string, type: string, url: string}|false $row */
        $row = $this->connection->fetchAssociative(
            'SELECT id, type, url FROM source WHERE id = :id AND enabled',
            ['id' => $sourceId],
        );

        if (false === $row) {
            throw SourceNotIngestible::withId($sourceId);
        }

        return new SourceToIngest($row['id'], $row['type'], $row['url']);
    }

    public function markIngested(string $sourceId, \DateTimeImmutable $at): void
    {
        $this->connection->executeStatement(
            <<<'SQL'
                INSERT INTO source_ingestion (source_id, last_ingested_at) VALUES (:id, :at)
                ON CONFLICT (source_id) DO UPDATE SET last_ingested_at = EXCLUDED.last_ingested_at
                SQL,
            ['id' => $sourceId, 'at' => $at],
            ['at' => Types::DATETIMETZ_IMMUTABLE],
        );
    }
}
