<?php

declare(strict_types=1);

namespace App\Services\Blog\Dto;

/**
 * 1記事の全データ（frontmatter ＋ レンダリング済み本文）。
 *
 * $html / $faqHtml は commonmark 済みの信頼 HTML。View では _ 付き変数として
 * 生出力する（DTO 自体を View に渡すと自動エスケープでこの 2 つも escape されるため、
 * Controller 側で _html / _faqHtml に取り出してから渡す）。
 */
class BlogArticleDto
{
    /**
     * @param FaqItemDto[] $faq
     */
    function __construct(
        public string $slug,
        public string $title,
        public string $description,
        public string $date,
        public string $updated,
        public string $category,
        public int $wordCount,
        public int $readingMinutes,
        public array $faq,
        public string $html,
        public string $faqHtml,
    ) {}
}
