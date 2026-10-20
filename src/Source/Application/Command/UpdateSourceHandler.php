<?php

declare(strict_types=1);

namespace App\Source\Application\Command;

use App\Source\Domain\Exception\SourceAlreadyExists;
use App\Source\Domain\Port\SourceRepository;

final class UpdateSourceHandler
{
    public function __construct(private readonly SourceRepository $sources)
    {
    }

    public function __invoke(UpdateSource $command): void
    {
        $source = $this->sources->get($command->id);

        if ($this->sources->existsWithUrlAndType($command->url, $command->type, $command->id)) {
            throw SourceAlreadyExists::withUrlAndType($command->url, $command->type);
        }

        $source->update($command->name, $command->type, $command->url, $command->fetchInterval);
        $this->sources->save($source);
    }
}
