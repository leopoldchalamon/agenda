<?php

declare(strict_types=1);

namespace App\Source\Domain\Model;

enum SourceType: string
{
    case Ical = 'ical';
    case JsonApi = 'json_api';
    case Rss = 'rss';
    case Website = 'website';
}
