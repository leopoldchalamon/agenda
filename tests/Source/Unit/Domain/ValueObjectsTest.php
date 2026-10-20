<?php

declare(strict_types=1);

namespace App\Tests\Source\Unit\Domain;

use App\Source\Domain\Exception\InvalidFetchInterval;
use App\Source\Domain\Exception\InvalidSourceId;
use App\Source\Domain\Exception\InvalidSourceName;
use App\Source\Domain\Exception\InvalidSourceUrl;
use App\Source\Domain\Model\FetchInterval;
use App\Source\Domain\Model\SourceId;
use App\Source\Domain\Model\SourceName;
use App\Source\Domain\Model\SourceUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ValueObjectsTest extends TestCase
{
    public function testSourceIdAcceptsUuid(): void
    {
        self::assertSame('0199f1c2-7a3b-7c4d-8e5f-0a1b2c3d4e5f', new SourceId('0199f1c2-7a3b-7c4d-8e5f-0a1b2c3d4e5f')->value);
    }

    public function testSourceIdThrowsWhenNotUuid(): void
    {
        $this->expectException(InvalidSourceId::class);

        new SourceId('not-a-uuid');
    }

    public function testSourceIdThrowsWhenNotUuidV7(): void
    {
        $this->expectException(InvalidSourceId::class);

        new SourceId('0199f1c2-7a3b-4c4d-8e5f-0a1b2c3d4e5f');
    }

    public function testSourceNameIsTrimmed(): void
    {
        self::assertSame('Mairie', new SourceName('  Mairie ')->value);
    }

    public function testSourceNameThrowsWhenEmpty(): void
    {
        $this->expectException(InvalidSourceName::class);

        new SourceName('   ');
    }

    public function testSourceNameThrowsWhenTooLong(): void
    {
        $this->expectException(InvalidSourceName::class);

        new SourceName(str_repeat('a', 256));
    }

    public function testSourceUrlAcceptsHttps(): void
    {
        self::assertSame('https://example.org/agenda.ics', new SourceUrl('https://example.org/agenda.ics')->value);
    }

    #[DataProvider('invalidUrls')]
    public function testSourceUrlThrowsWhenInvalid(string $url): void
    {
        $this->expectException(InvalidSourceUrl::class);

        new SourceUrl($url);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidUrls(): iterable
    {
        yield 'not an url' => ['agenda'];
        yield 'ftp scheme' => ['ftp://example.org/agenda.ics'];
        yield 'file scheme' => ['file:///etc/passwd'];
    }

    public function testFetchIntervalDefaultsTo24Hours(): void
    {
        self::assertSame(1440, FetchInterval::default()->minutes);
    }

    public function testFetchIntervalAccepts12Hours(): void
    {
        self::assertSame(720, new FetchInterval(720)->minutes);
    }

    public function testFetchIntervalThrowsWhenBelow12Hours(): void
    {
        $this->expectException(InvalidFetchInterval::class);

        new FetchInterval(719);
    }
}
