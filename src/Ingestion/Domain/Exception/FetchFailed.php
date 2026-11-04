<?php

declare(strict_types=1);

namespace App\Ingestion\Domain\Exception;

final class FetchFailed extends \RuntimeException
{
    public static function because(string $url, string $reason, ?\Throwable $previous = null): self
    {
        return new self(\sprintf('Fetching "%s" failed: %s', $url, $reason), 0, $previous);
    }
}
