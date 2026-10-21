<?php

declare(strict_types=1);

namespace App\Source\Domain\Exception;

use App\Source\Domain\Model\SourceId;

final class SourceNotFound extends \DomainException
{
    public static function withId(SourceId $id): self
    {
        return new self(\sprintf('Source %s does not exist.', $id->value));
    }
}
