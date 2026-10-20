<?php

declare(strict_types=1);

namespace App\Source\Domain\Exception;

use App\Source\Domain\Model\SourceType;
use App\Source\Domain\Model\SourceUrl;

final class SourceAlreadyExists extends \DomainException
{
    public static function withUrlAndType(SourceUrl $url, SourceType $type): self
    {
        return new self(\sprintf('A %s source already exists for %s.', $type->value, $url->value));
    }
}
