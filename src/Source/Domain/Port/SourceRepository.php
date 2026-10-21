<?php

declare(strict_types=1);

namespace App\Source\Domain\Port;

use App\Source\Domain\Exception\SourceNotFound;
use App\Source\Domain\Model\Source;
use App\Source\Domain\Model\SourceId;
use App\Source\Domain\Model\SourceType;
use App\Source\Domain\Model\SourceUrl;

interface SourceRepository
{
    public function nextIdentity(): SourceId;

    public function save(Source $source): void;

    /**
     * @throws SourceNotFound
     */
    public function get(SourceId $id): Source;

    public function existsWithUrlAndType(SourceUrl $url, SourceType $type, ?SourceId $excluding = null): bool;
}
