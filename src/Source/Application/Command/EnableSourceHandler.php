<?php

declare(strict_types=1);

namespace App\Source\Application\Command;

use App\Source\Domain\Port\SourceRepository;

final class EnableSourceHandler
{
    public function __construct(private readonly SourceRepository $sources)
    {
    }

    public function __invoke(EnableSource $command): void
    {
        $source = $this->sources->get($command->id);
        $source->enable();
        $this->sources->save($source);
    }
}
