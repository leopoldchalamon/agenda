<?php

declare(strict_types=1);

namespace App\Tests\Source\Unit\Application;

use App\Source\Application\Command\CreateSource;
use App\Source\Application\Command\CreateSourceHandler;
use App\Source\Domain\Event\SourceCreated;
use App\Source\Domain\Exception\SourceAlreadyExists;
use App\Source\Domain\Model\FetchInterval;
use App\Source\Domain\Model\SourceName;
use App\Source\Domain\Model\SourceType;
use App\Source\Domain\Model\SourceUrl;
use App\Tests\Source\Fake\InMemorySourceRepository;
use PHPUnit\Framework\TestCase;

final class CreateSourceHandlerTest extends TestCase
{
    private InMemorySourceRepository $sources;
    private CreateSourceHandler $handler;

    protected function setUp(): void
    {
        $this->sources = new InMemorySourceRepository();
        $this->handler = new CreateSourceHandler($this->sources);
    }

    public function testCreatesEnabledSourceWithDefaultInterval(): void
    {
        $id = ($this->handler)(new CreateSource(
            new SourceName('Mairie'),
            SourceType::Ical,
            new SourceUrl('https://example.org/agenda.ics'),
        ));

        $source = $this->sources->get($id);
        self::assertSame('Mairie', $source->name()->value);
        self::assertSame(SourceType::Ical, $source->type());
        self::assertSame('https://example.org/agenda.ics', $source->url()->value);
        self::assertSame(1440, $source->fetchInterval()->minutes);
        self::assertTrue($source->isEnabled());
        self::assertEquals([new SourceCreated($id)], $source->releaseEvents());
    }

    public function testCreatesSourceWithCustomInterval(): void
    {
        $id = ($this->handler)(new CreateSource(
            new SourceName('Mairie'),
            SourceType::Rss,
            new SourceUrl('https://example.org/feed'),
            new FetchInterval(720),
        ));

        self::assertSame(720, $this->sources->get($id)->fetchInterval()->minutes);
    }

    public function testAllowsSameUrlWithAnotherType(): void
    {
        $url = new SourceUrl('https://example.org/agenda');
        ($this->handler)(new CreateSource(new SourceName('Agenda page'), SourceType::Website, $url));

        $id = ($this->handler)(new CreateSource(new SourceName('Agenda feed'), SourceType::Rss, $url));

        self::assertSame(SourceType::Rss, $this->sources->get($id)->type());
    }

    public function testThrowsWhenUrlAndTypeAlreadyExist(): void
    {
        $url = new SourceUrl('https://example.org/agenda.ics');
        ($this->handler)(new CreateSource(new SourceName('Mairie'), SourceType::Ical, $url));

        $this->expectException(SourceAlreadyExists::class);

        ($this->handler)(new CreateSource(new SourceName('Duplicate'), SourceType::Ical, $url));
    }
}
