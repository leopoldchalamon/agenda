<?php

declare(strict_types=1);

namespace App\Source\Application\Command;

use App\Source\Domain\Exception\SourceAlreadyExists;
use App\Source\Domain\Model\Source;
use App\Source\Domain\Model\SourceId;
use App\Source\Domain\Port\SourceRepository;

final class CreateSourceHandler
{
    public function __construct(private readonly SourceRepository $sources)
    {
    }

    public function __invoke(CreateSource $command): SourceId
    {
        if ($this->sources->existsWithUrlAndType($command->url, $command->type)) {
            throw SourceAlreadyExists::withUrlAndType($command->url, $command->type);
        }

        $source = Source::create($this->sources->nextIdentity(), $command->name, $command->type, $command->url, $command->fetchInterval);
        $this->sources->save($source);

        return $source->id();
    }
}
