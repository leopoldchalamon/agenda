<?php

declare(strict_types=1);

namespace App\Tests\Source\Unit\Application;

use App\Source\Application\Command\CreateSource;
use App\Source\Application\Command\CreateSourceHandler;
use App\Source\Application\Command\UpdateSource;
use App\Source\Application\Command\UpdateSourceHandler;
use App\Source\Domain\Event\SourceUpdated;
use App\Source\Domain\Exception\SourceAlreadyExists;
use App\Source\Domain\Exception\SourceNotFound;
use App\Source\Domain\Model\FetchInterval;
use App\Source\Domain\Model\SourceId;
use App\Source\Domain\Model\SourceName;
use App\Source\Domain\Model\SourceType;
use App\Source\Domain\Model\SourceUrl;
use App\Tests\Source\Fake\InMemorySourceRepository;
use PHPUnit\Framework\TestCase;

final class UpdateSourceHandlerTest extends TestCase
{
    private InMemorySourceRepository $sources;
    private UpdateSourceHandler $handler;
    private SourceId $id;

    protected function setUp(): void
    {
        $this->sources = new InMemorySourceRepository();
        $this->handler = new UpdateSourceHandler($this->sources);
        $this->id = new CreateSourceHandler($this->sources)(new CreateSource(
            new SourceName('Mairie'),
            SourceType::Ical,
            new SourceUrl('https://example.org/agenda.ics'),
        ));
        $this->sources->get($this->id)->releaseEvents();
    }

    public function testUpdatesSource(): void
    {
        ($this->handler)(new UpdateSource(
            $this->id,
            new SourceName('Town hall'),
            SourceType::Website,
            new SourceUrl('https://example.org/events'),
            new FetchInterval(720),
        ));

        $source = $this->sources->get($this->id);
        self::assertSame('Town hall', $source->name()->value);
        self::assertSame(SourceType::Website, $source->type());
        self::assertSame('https://example.org/events', $source->url()->value);
        self::assertSame(720, $source->fetchInterval()->minutes);
        self::assertEquals([new SourceUpdated($this->id)], $source->releaseEvents());
    }

    public function testKeepsOwnUrlAndType(): void
    {
        ($this->handler)(new UpdateSource(
            $this->id,
            new SourceName('Town hall'),
            SourceType::Ical,
            new SourceUrl('https://example.org/agenda.ics'),
            FetchInterval::default(),
        ));

        self::assertSame('Town hall', $this->sources->get($this->id)->name()->value);
    }

    public function testThrowsWhenUrlAndTypeBelongToAnotherSource(): void
    {
        new CreateSourceHandler($this->sources)(new CreateSource(
            new SourceName('Feed'),
            SourceType::Rss,
            new SourceUrl('https://example.org/feed'),
        ));

        $this->expectException(SourceAlreadyExists::class);

        ($this->handler)(new UpdateSource(
            $this->id,
            new SourceName('Mairie'),
            SourceType::Rss,
            new SourceUrl('https://example.org/feed'),
            FetchInterval::default(),
        ));
    }

    public function testThrowsWhenSourceDoesNotExist(): void
    {
        $this->expectException(SourceNotFound::class);

        ($this->handler)(new UpdateSource(
            $this->sources->nextIdentity(),
            new SourceName('Mairie'),
            SourceType::Ical,
            new SourceUrl('https://example.org/agenda.ics'),
            FetchInterval::default(),
        ));
    }
}
