<?php

declare(strict_types=1);

namespace App\Source\Application\Command;

use App\Source\Domain\Port\SourceRepository;

final class DisableSourceHandler
{
    public function __construct(private readonly SourceRepository $sources)
    {
    }

    public function __invoke(DisableSource $command): void
    {
        $source = $this->sources->get($command->id);
        $source->disable();
        $this->sources->save($source);
    }
}
