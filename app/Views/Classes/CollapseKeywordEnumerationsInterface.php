<?php

declare(strict_types=1);

namespace App\Views\Classes;

interface CollapseKeywordEnumerationsInterface
{
    public static function collapse(
        string $text,
        int $minItems = 12,
        int $keepFirst = 1,
        int $allowHashtags = 1,
        string $extraText = '',
        bool $returnRemovedOnly = false,
        int $embeddedMinItems = 3
    ): string;
}