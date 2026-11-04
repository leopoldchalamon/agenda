<?php

declare(strict_types=1);

namespace App\Tests\Ingestion\Unit\Domain;

use App\Ingestion\Domain\Exception\InvalidFetchedContent;
use App\Ingestion\Domain\Model\EventPeriod;
use App\Ingestion\Domain\Model\FetchedEvent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FetchedEventTest extends TestCase
{
    public function testNormalizesFields(): void
    {
        $event = new FetchedEvent(' uid-1 ', ' Concert ', $this->period(), ' ', 'https://example.org/concert', ' Jazz ');

        self::assertSame('uid-1', $event->externalId);
        self::assertSame('Concert', $event->title);
        self::assertNull($event->location);
        self::assertSame('https://example.org/concert', $event->url);
        self::assertSame('Jazz', $event->description);
    }

    public function testTruncatesLongTitle(): void
    {
        $event = new FetchedEvent('uid-1', str_repeat('é', 300), $this->period());

        self::assertSame(255, mb_strlen($event->title));
    }

    #[DataProvider('unusableUrls')]
    public function testDropsUnusableUrl(string $url): void
    {
        self::assertNull(new FetchedEvent('uid-1', 'Concert', $this->period(), url: $url)->url);
    }

    /** @return iterable<string, array{string}> */
    public static function unusableUrls(): iterable
    {
        yield 'not an url' => ['concert'];
        yield 'javascript scheme' => ['javascript:alert(1)'];
        yield 'too long' => ['https://example.org/'.str_repeat('a', 2029)];
    }

    public function testThrowsWhenExternalIdIsEmpty(): void
    {
        $this->expectException(InvalidFetchedContent::class);

        new FetchedEvent(' ', 'Concert', $this->period());
    }

    public function testThrowsWhenTitleIsEmpty(): void
    {
        $this->expectException(InvalidFetchedContent::class);

        new FetchedEvent('uid-1', ' ', $this->period());
    }

    public function testThrowsWhenPeriodEndsBeforeStart(): void
    {
        $this->expectException(InvalidFetchedContent::class);

        new EventPeriod(new \DateTimeImmutable('2026-10-21 20:00'), new \DateTimeImmutable('2026-10-21 19:00'));
    }

    private function period(): EventPeriod
    {
        return new EventPeriod(new \DateTimeImmutable('2026-10-21 20:00'));
    }
}
