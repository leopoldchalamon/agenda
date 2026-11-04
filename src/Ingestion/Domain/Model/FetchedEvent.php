<?php

declare(strict_types=1);

namespace App\Ingestion\Domain\Model;

use App\Ingestion\Domain\Exception\InvalidFetchedContent;

final readonly class FetchedEvent
{
    public const int MAX_LENGTH = 255;
    public const int MAX_URL_LENGTH = 2048;

    public string $externalId;
    public string $title;
    public ?string $location;
    public ?string $url;
    public ?string $description;

    public function __construct(
        string $externalId,
        string $title,
        public EventPeriod $period,
        ?string $location = null,
        ?string $url = null,
        ?string $description = null,
    ) {
        $externalId = trim($externalId);
        if ('' === $externalId || mb_strlen($externalId) > self::MAX_LENGTH) {
            throw InvalidFetchedContent::because('Event external id is empty or too long.');
        }

        $title = trim($title);
        if ('' === $title) {
            throw InvalidFetchedContent::because(\sprintf('Event "%s" has no title.', $externalId));
        }

        $this->externalId = $externalId;
        $this->title = mb_substr($title, 0, self::MAX_LENGTH);
        $this->location = self::optional($location, self::MAX_LENGTH);
        $this->url = self::httpUrl($url);
        $this->description = self::optional($description, null);
    }

    private static function optional(?string $value, ?int $maxLength): ?string
    {
        $value = trim($value ?? '');

        if ('' === $value) {
            return null;
        }

        return null === $maxLength ? $value : mb_substr($value, 0, $maxLength);
    }

    // An unusable link should not discard the whole event.
    private static function httpUrl(?string $url): ?string
    {
        $url = self::optional($url, null);

        if (null === $url || \strlen($url) > self::MAX_URL_LENGTH || false === filter_var($url, \FILTER_VALIDATE_URL)) {
            return null;
        }

        return \in_array(parse_url($url, \PHP_URL_SCHEME), ['http', 'https'], true) ? $url : null;
    }
}
