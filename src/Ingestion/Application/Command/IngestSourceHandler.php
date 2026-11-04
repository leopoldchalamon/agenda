<?php

declare(strict_types=1);

namespace App\Ingestion\Application\Command;

use App\Ingestion\Domain\Exception\UnsupportedSourceType;
use App\Ingestion\Domain\Port\EventStore;
use App\Ingestion\Domain\Port\SourceCatalog;
use App\Ingestion\Domain\Port\SourceFetcher;
use Psr\Clock\ClockInterface;

final class IngestSourceHandler
{
    /**
     * @param iterable<SourceFetcher> $fetchers
     */
    public function __construct(
        private readonly SourceCatalog $sources,
        private readonly EventStore $events,
        private readonly iterable $fetchers,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(IngestSource $command): void
    {
        $source = $this->sources->get($command->sourceId);
        $events = $this->fetcherFor($source->type)->fetch($source->url);

        $this->events->upsert($source->sourceId, $events);
        $this->sources->markIngested($source->sourceId, $this->clock->now());
    }

    private function fetcherFor(string $type): SourceFetcher
    {
        foreach ($this->fetchers as $fetcher) {
            if ($fetcher->supports($type)) {
                return $fetcher;
            }
        }

        throw UnsupportedSourceType::named($type);
    }
}
