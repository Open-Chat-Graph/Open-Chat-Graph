<?php

declare(strict_types=1);

namespace App\Services\StaticData\Dto;

// .dat にシリアライズされる。プロパティを削除しても、移行期の旧キャッシュが
// 持つ余剰プロパティを動的プロパティとして黙って受け流すための属性
// （E_DEPRECATED がエラーハンドラで例外化されるのを防ぐ）。
#[\AllowDynamicProperties]
class StaticRecommendPageDto
{
    public string $hourlyUpdatedAt;
    /** @var array $tagRecordCounts ['タグ名' => int] */
    public array $tagRecordCounts;
}
