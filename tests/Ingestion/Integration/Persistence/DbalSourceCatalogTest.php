<?php

declare(strict_types=1);

namespace App\Tests\Ingestion\Integration\Persistence;

use App\Ingestion\Domain\Exception\SourceNotIngestible;
use App\Ingestion\Infrastructure\Persistence\DbalSourceCatalog;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DbalSourceCatalogTest extends KernelTestCase
{
    private const string NEVER_INGESTED = '0199f1c2-7a3b-7c4d-8e5f-000000000001';
    private const string INTERVAL_ELAPSED = '0199f1c2-7a3b-7c4d-8e5f-000000000002';
    private const string RECENTLY_INGESTED = '0199f1c2-7a3b-7c4d-8e5f-000000000003';
    private const string DISABLED = '0199f1c2-7a3b-7c4d-8e5f-000000000004';

    private Connection $connection;
    private DbalSourceCatalog $sources;

    protected function setUp(): void
    {
        $this->connection = self::getContainer()->get(Connection::class);
        $this->sources = new DbalSourceCatalog($this->connection);
        $this->connection->beginTransaction();

        $this->insertSource(self::NEVER_INGESTED, enabled: true);
        $this->insertSource(self::INTERVAL_ELAPSED, enabled: true);
        $this->insertSource(self::RECENTLY_INGESTED, enabled: true);
        $this->insertSource(self::DISABLED, enabled: false);
    }

    protected function tearDown(): void
    {
        $this->connection->rollBack();
        parent::tearDown();
    }

    public function testListsEnabledSourcesWhoseIntervalHasElapsed(): void
    {
        $now = new \DateTimeImmutable('2026-10-21 12:00:00 UTC');
        $this->sources->markIngested(self::INTERVAL_ELAPSED, $now->modify('-720 minutes'));
        $this->sources->markIngested(self::RECENTLY_INGESTED, $now->modify('-719 minutes'));

        self::assertSame([self::NEVER_INGESTED, self::INTERVAL_ELAPSED], $this->sources->dueForIngestion($now));
    }

    public function testMarkingIngestedTwiceKeepsLatestDate(): void
    {
        $now = new \DateTimeImmutable('2026-10-21 12:00:00 UTC');
        $this->sources->markIngested(self::NEVER_INGESTED, $now->modify('-1 day'));
        $this->sources->markIngested(self::NEVER_INGESTED, $now);

        self::assertNotContains(self::NEVER_INGESTED, $this->sources->dueForIngestion($now));
    }

    public function testGetsEnabledSource(): void
    {
        $source = $this->sources->get(self::NEVER_INGESTED);

        self::assertSame(self::NEVER_INGESTED, $source->sourceId);
        self::assertSame('ical', $source->type);
        self::assertSame('https://example.org/'.self::NEVER_INGESTED, $source->url);
    }

    public function testThrowsWhenSourceIsDisabled(): void
    {
        $this->expectException(SourceNotIngestible::class);

        $this->sources->get(self::DISABLED);
    }

    private function insertSource(string $id, bool $enabled): void
    {
        $this->connection->insert('source', [
            'id' => $id,
            'name' => 'Source '.$id,
            'type' => 'ical',
            'url' => 'https://example.org/'.$id,
            'fetch_interval_minutes' => 720,
            'enabled' => $enabled,
        ], ['enabled' => 'boolean']);
    }
}
