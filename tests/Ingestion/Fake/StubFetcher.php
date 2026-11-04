<?php

declare(strict_types=1);

namespace App\Tests\Ingestion\Fake;

use App\Ingestion\Domain\Model\FetchedEvent;
use App\Ingestion\Domain\Port\SourceFetcher;

final class StubFetcher implements SourceFetcher
{
    /**
     * @param list<FetchedEvent> $events
     */
    public function __construct(
        private readonly string $type,
        private readonly array $events = [],
        private readonly ?\Throwable $failure = null,
    ) {
    }

    public function supports(string $sourceType): bool
    {
        return $sourceType === $this->type;
    }

    public function fetch(string $url): array
    {
        if (null !== $this->failure) {
            throw $this->failure;
        }

        return $this->events;
    }
}
