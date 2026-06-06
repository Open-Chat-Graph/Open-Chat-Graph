<?php

declare(strict_types=1);

namespace App\Services\Blog\Dto;

/**
 * 本文の「よくある質問」セクションから抽出した Q&A 1件（FAQPage 構造化データ用）。
 */
class FaqItemDto
{
    function __construct(
        public string $q,
        public string $a,
    ) {}
}
