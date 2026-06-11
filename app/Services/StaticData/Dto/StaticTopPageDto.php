<?php

declare(strict_types=1);

namespace App\Services\StaticData\Dto;

// .dat にシリアライズされる。プロパティを削除しても、移行期の旧キャッシュが
// 持つ余剰プロパティを動的プロパティとして黙って受け流すための属性
// （E_DEPRECATED がエラーハンドラで例外化されるのを防ぐ）。
#[\AllowDynamicProperties]
class StaticTopPageDto
{
    public array $hourlyList;
    public array $dailyList;
    public array $weeklyList;
    public array $popularList;

    /** @var array{ hour:string[],hour24:string[] } $recommendList */
    public array $recommendList;

    public \DateTime $hourlyUpdatedAt;
    public \DateTime $dailyUpdatedAt;
    public \DateTime $rankingUpdatedAt;
}
