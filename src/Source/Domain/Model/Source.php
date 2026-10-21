<?php

declare(strict_types=1);

namespace App\Source\Domain\Model;

use App\Source\Domain\Event\SourceCreated;
use App\Source\Domain\Event\SourceDisabled;
use App\Source\Domain\Event\SourceEnabled;
use App\Source\Domain\Event\SourceUpdated;
use App\Source\Domain\Exception\SourceAlreadyDisabled;
use App\Source\Domain\Exception\SourceAlreadyEnabled;

final class Source
{
    /** @var list<object> */
    private array $events = [];

    private function __construct(
        private readonly SourceId $id,
        private SourceName $name,
        private SourceType $type,
        private SourceUrl $url,
        private FetchInterval $fetchInterval,
        private bool $enabled,
    ) {
    }

    public static function create(SourceId $id, SourceName $name, SourceType $type, SourceUrl $url, FetchInterval $fetchInterval): self
    {
        $source = new self($id, $name, $type, $url, $fetchInterval, true);
        $source->events[] = new SourceCreated($id);

        return $source;
    }

    /**
     * Rebuilds a stored source without recording events.
     */
    public static function reconstitute(SourceId $id, SourceName $name, SourceType $type, SourceUrl $url, FetchInterval $fetchInterval, bool $enabled): self
    {
        return new self($id, $name, $type, $url, $fetchInterval, $enabled);
    }

    public function update(SourceName $name, SourceType $type, SourceUrl $url, FetchInterval $fetchInterval): void
    {
        $this->name = $name;
        $this->type = $type;
        $this->url = $url;
        $this->fetchInterval = $fetchInterval;
        $this->events[] = new SourceUpdated($this->id);
    }

    public function enable(): void
    {
        if ($this->enabled) {
            throw SourceAlreadyEnabled::withId($this->id);
        }

        $this->enabled = true;
        $this->events[] = new SourceEnabled($this->id);
    }

    public function disable(): void
    {
        if (!$this->enabled) {
            throw SourceAlreadyDisabled::withId($this->id);
        }

        $this->enabled = false;
        $this->events[] = new SourceDisabled($this->id);
    }

    /** @return list<object> */
    public function releaseEvents(): array
    {
        $events = $this->events;
        $this->events = [];

        return $events;
    }

    public function id(): SourceId
    {
        return $this->id;
    }

    public function name(): SourceName
    {
        return $this->name;
    }

    public function type(): SourceType
    {
        return $this->type;
    }

    public function url(): SourceUrl
    {
        return $this->url;
    }

    public function fetchInterval(): FetchInterval
    {
        return $this->fetchInterval;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
