<?php

declare(strict_types=1);

namespace App\Tests\Ingestion\Integration\Persistence;

use App\Ingestion\Domain\Model\EventPeriod;
use App\Ingestion\Domain\Model\FetchedEvent;
use App\Ingestion\Infrastructure\Persistence\DbalEventStore;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DbalEventStoreTest extends KernelTestCase
{
    private const string SOURCE_ID = '0199f1c2-7a3b-7c4d-8e5f-0a1b2c3d4e5f';

    private Connection $connection;
    private DbalEventStore $events;

    protected function setUp(): void
    {
        $this->connection = self::getContainer()->get(Connection::class);
        $this->events = new DbalEventStore($this->connection);
        $this->connection->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->connection->rollBack();
        parent::tearDown();
    }

    public function testStoresAndReloadsEvent(): void
    {
        $period = new EventPeriod(new \DateTimeImmutable('2026-11-01 19:00:00 UTC'), new \DateTimeImmutable('2026-11-01 21:00:00 UTC'));

        $this->events->upsert(self::SOURCE_ID, [new FetchedEvent('uid-1', 'Concert', $period, 'Salle', 'https://example.org/concert', 'Jazz')]);

        $row = $this->connection->fetchAssociative('SELECT * FROM event WHERE source_id = ?', [self::SOURCE_ID]);
        self::assertIsArray($row);
        self::assertSame('uid-1', $row['external_id']);
        self::assertSame('Concert', $row['title']);
        self::assertIsString($row['starts_at']);
        self::assertIsString($row['ends_at']);
        self::assertEquals($period->startsAt, new \DateTimeImmutable($row['starts_at']));
        self::assertEquals($period->endsAt, new \DateTimeImmutable($row['ends_at']));
        self::assertSame('Salle', $row['location']);
        self::assertSame('https://example.org/concert', $row['url']);
        self::assertSame('Jazz', $row['description']);
    }

    public function testIngestingTwiceUpdatesInsteadOfDuplicating(): void
    {
        $period = new EventPeriod(new \DateTimeImmutable('2026-11-01 19:00:00 UTC'));

        $this->events->upsert(self::SOURCE_ID, [new FetchedEvent('uid-1', 'Concert', $period)]);
        $id = $this->connection->fetchOne('SELECT id FROM event WHERE source_id = ?', [self::SOURCE_ID]);
        $this->events->upsert(self::SOURCE_ID, [new FetchedEvent('uid-1', 'Concert (complet)', $period)]);

        $rows = $this->connection->fetchAllAssociative('SELECT id, title FROM event WHERE source_id = ?', [self::SOURCE_ID]);
        self::assertSame([['id' => $id, 'title' => 'Concert (complet)']], $rows);
    }

    public function testKeepsSameExternalIdFromDifferentSources(): void
    {
        $event = new FetchedEvent('uid-1', 'Concert', new EventPeriod(new \DateTimeImmutable('2026-11-01 19:00:00 UTC')));

        $this->events->upsert(self::SOURCE_ID, [$event]);
        $this->events->upsert('0199f1c2-7a3b-7c4d-8e5f-000000000000', [$event]);

        self::assertSame(2, $this->connection->fetchOne("SELECT COUNT(*) FROM event WHERE external_id = 'uid-1'"));
    }
}
