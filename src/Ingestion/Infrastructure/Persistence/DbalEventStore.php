<?php

declare(strict_types=1);

namespace App\Ingestion\Infrastructure\Persistence;

use App\Ingestion\Domain\Port\EventStore;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\Uid\Uuid;

final class DbalEventStore implements EventStore
{
    private const string UPSERT = <<<'SQL'
        INSERT INTO event (id, source_id, external_id, title, starts_at, ends_at, location, url, description)
        VALUES (:id, :sourceId, :externalId, :title, :startsAt, :endsAt, :location, :url, :description)
        ON CONFLICT (source_id, external_id) DO UPDATE SET
            title = EXCLUDED.title,
            starts_at = EXCLUDED.starts_at,
            ends_at = EXCLUDED.ends_at,
            location = EXCLUDED.location,
            url = EXCLUDED.url,
            description = EXCLUDED.description
        SQL;

    public function __construct(private readonly Connection $connection)
    {
    }

    public function upsert(string $sourceId, array $events): void
    {
        $this->connection->transactional(function () use ($sourceId, $events): void {
            foreach ($events as $event) {
                $this->connection->executeStatement(self::UPSERT, [
                    'id' => Uuid::v7()->toRfc4122(),
                    'sourceId' => $sourceId,
                    'externalId' => $event->externalId,
                    'title' => $event->title,
                    'startsAt' => $event->period->startsAt,
                    'endsAt' => $event->period->endsAt,
                    'location' => $event->location,
                    'url' => $event->url,
                    'description' => $event->description,
                ], [
                    'startsAt' => Types::DATETIMETZ_IMMUTABLE,
                    'endsAt' => Types::DATETIMETZ_IMMUTABLE,
                ]);
            }
        });
    }
}
