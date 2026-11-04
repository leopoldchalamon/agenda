<?php

declare(strict_types=1);

namespace App\Ingestion\Domain\Port;

use App\Ingestion\Domain\Model\FetchedEvent;

interface EventStore
{
    /**
     * Inserts new events and updates those already stored for the same source and external id.
     *
     * @param list<FetchedEvent> $events
     */
    public function upsert(string $sourceId, array $events): void;
}
