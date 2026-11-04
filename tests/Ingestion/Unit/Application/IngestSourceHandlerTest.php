<?php

declare(strict_types=1);

namespace App\Tests\Ingestion\Unit\Application;

use App\Ingestion\Application\Command\IngestSource;
use App\Ingestion\Application\Command\IngestSourceHandler;
use App\Ingestion\Domain\Exception\FetchFailed;
use App\Ingestion\Domain\Exception\SourceNotIngestible;
use App\Ingestion\Domain\Exception\UnsupportedSourceType;
use App\Ingestion\Domain\Model\EventPeriod;
use App\Ingestion\Domain\Model\FetchedEvent;
use App\Ingestion\Domain\Model\SourceToIngest;
use App\Ingestion\Domain\Port\SourceFetcher;
use App\Tests\Ingestion\Fake\InMemoryEventStore;
use App\Tests\Ingestion\Fake\InMemorySourceCatalog;
use App\Tests\Ingestion\Fake\StubFetcher;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class IngestSourceHandlerTest extends TestCase
{
    private const string SOURCE_ID = '0199f1c2-7a3b-7c4d-8e5f-0a1b2c3d4e5f';

    private InMemorySourceCatalog $sources;
    private InMemoryEventStore $events;
    private MockClock $clock;

    protected function setUp(): void
    {
        $this->sources = new InMemorySourceCatalog();
        $this->sources->add(new SourceToIngest(self::SOURCE_ID, 'ical', 'https://example.org/agenda.ics'));
        $this->events = new InMemoryEventStore();
        $this->clock = new MockClock('2026-10-21 08:00:00');
    }

    public function testStoresFetchedEventsAndMarksSourceIngested(): void
    {
        $event = new FetchedEvent('uid-1', 'Concert', new EventPeriod(new \DateTimeImmutable('2026-11-01 20:00')));

        $this->handler(new StubFetcher('rss'), new StubFetcher('ical', [$event]))(new IngestSource(self::SOURCE_ID));

        self::assertSame(['uid-1' => $event], $this->events->events[self::SOURCE_ID]);
        self::assertEquals($this->clock->now(), $this->sources->ingestedAt[self::SOURCE_ID]);
    }

    public function testThrowsWhenSourceIsNotIngestible(): void
    {
        $this->expectException(SourceNotIngestible::class);

        $this->handler(new StubFetcher('ical'))(new IngestSource('0199f1c2-7a3b-7c4d-8e5f-000000000000'));
    }

    public function testThrowsWhenNoFetcherSupportsSourceType(): void
    {
        $this->expectException(UnsupportedSourceType::class);

        $this->handler(new StubFetcher('rss'))(new IngestSource(self::SOURCE_ID));
    }

    public function testDoesNotMarkSourceIngestedWhenFetchFails(): void
    {
        $handler = $this->handler(new StubFetcher('ical', failure: FetchFailed::because('https://example.org/agenda.ics', 'timeout')));

        try {
            $handler(new IngestSource(self::SOURCE_ID));
            self::fail('FetchFailed was not thrown.');
        } catch (FetchFailed) {
        }

        self::assertSame([], $this->events->events);
        self::assertSame([], $this->sources->ingestedAt);
    }

    private function handler(SourceFetcher ...$fetchers): IngestSourceHandler
    {
        return new IngestSourceHandler($this->sources, $this->events, $fetchers, $this->clock);
    }
}
