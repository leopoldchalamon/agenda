<?php

declare(strict_types=1);

namespace App\Tests\Ingestion\Fake;

use App\Ingestion\Domain\Model\FetchedEvent;
use App\Ingestion\Domain\Port\EventStore;

final class InMemoryEventStore implements EventStore
{
    /** @var array<string, array<string, FetchedEvent>> */
    public array $events = [];

    public function upsert(string $sourceId, array $events): void
    {
        foreach ($events as $event) {
            $this->events[$sourceId][$event->externalId] = $event;
        }
    }
}
