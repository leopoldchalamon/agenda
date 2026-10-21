<?php

declare(strict_types=1);

namespace App\Tests\Source\Unit\Application;

use App\Source\Application\Command\CreateSource;
use App\Source\Application\Command\CreateSourceHandler;
use App\Source\Application\Command\DisableSource;
use App\Source\Application\Command\DisableSourceHandler;
use App\Source\Application\Command\EnableSource;
use App\Source\Application\Command\EnableSourceHandler;
use App\Source\Domain\Event\SourceDisabled;
use App\Source\Domain\Event\SourceEnabled;
use App\Source\Domain\Exception\SourceAlreadyDisabled;
use App\Source\Domain\Exception\SourceAlreadyEnabled;
use App\Source\Domain\Exception\SourceNotFound;
use App\Source\Domain\Model\SourceId;
use App\Source\Domain\Model\SourceName;
use App\Source\Domain\Model\SourceType;
use App\Source\Domain\Model\SourceUrl;
use App\Tests\Source\Fake\InMemorySourceRepository;
use PHPUnit\Framework\TestCase;

final class ToggleSourceHandlersTest extends TestCase
{
    private InMemorySourceRepository $sources;
    private EnableSourceHandler $enable;
    private DisableSourceHandler $disable;
    private SourceId $id;

    protected function setUp(): void
    {
        $this->sources = new InMemorySourceRepository();
        $this->enable = new EnableSourceHandler($this->sources);
        $this->disable = new DisableSourceHandler($this->sources);
        $this->id = new CreateSourceHandler($this->sources)(new CreateSource(
            new SourceName('Mairie'),
            SourceType::Ical,
            new SourceUrl('https://example.org/agenda.ics'),
        ));
        $this->sources->get($this->id)->releaseEvents();
    }

    public function testDisablesEnabledSource(): void
    {
        ($this->disable)(new DisableSource($this->id));

        $source = $this->sources->get($this->id);
        self::assertFalse($source->isEnabled());
        self::assertEquals([new SourceDisabled($this->id)], $source->releaseEvents());
    }

    public function testEnablesDisabledSource(): void
    {
        ($this->disable)(new DisableSource($this->id));
        $this->sources->get($this->id)->releaseEvents();

        ($this->enable)(new EnableSource($this->id));

        $source = $this->sources->get($this->id);
        self::assertTrue($source->isEnabled());
        self::assertEquals([new SourceEnabled($this->id)], $source->releaseEvents());
    }

    public function testThrowsWhenSourceIsAlreadyEnabled(): void
    {
        $this->expectException(SourceAlreadyEnabled::class);

        ($this->enable)(new EnableSource($this->id));
    }

    public function testThrowsWhenSourceIsAlreadyDisabled(): void
    {
        ($this->disable)(new DisableSource($this->id));

        $this->expectException(SourceAlreadyDisabled::class);

        ($this->disable)(new DisableSource($this->id));
    }

    public function testThrowsWhenDisablingUnknownSource(): void
    {
        $this->expectException(SourceNotFound::class);

        ($this->disable)(new DisableSource($this->sources->nextIdentity()));
    }

    public function testThrowsWhenEnablingUnknownSource(): void
    {
        $this->expectException(SourceNotFound::class);

        ($this->enable)(new EnableSource($this->sources->nextIdentity()));
    }
}
