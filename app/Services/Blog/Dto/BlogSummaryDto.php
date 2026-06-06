<?php

declare(strict_types=1);

namespace App\Services\Blog\Dto;

/**
 * 記事一覧・関連記事の表示用データ（frontmatter のみ・本文レンダリングなし）。
 * View へ直接渡してプロパティ参照する（非 _ プロパティは View 層で自動エスケープされる）。
 */
class BlogSummaryDto
{
    function __construct(
        public string $slug,
        public string $title,
        public string $description,
        public string $date,
        public string $updated,
        public string $category,
    ) {}
}
