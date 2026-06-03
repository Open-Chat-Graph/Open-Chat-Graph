<?php

declare(strict_types=1);

namespace App\Services\Recommend\Dto;

/**
 * テーマ発見セクション（/recommend 着地客の回遊導線）の表示用データ。
 * 表示ロジックは ThemeDiscoveryService が確定し、View は本DTOを描画するだけにする。
 *
 * 各棚の要素は ['name' => 表示名, 'slug' => url用スラッグ]。
 */
class ThemeDiscoveryDto
{
    /**
     * @param list<array{name:string,slug:string}> $trending 🚀急上昇
     * @param list<array{name:string,slug:string}> $popular  🔥人気（合計人数順）
     * @param list<array{name:string,slug:string}> $nearby   🗂同カテゴリ
     * @param string $nearbyCategoryName 近いカテゴリの表示名（無ければ空）
     * @param list<array{0:string,1:string}> $searchIndex 検索用 [表示名, スラッグ]
     */
    public function __construct(
        public readonly array $trending,
        public readonly array $popular,
        public readonly array $nearby,
        public readonly string $nearbyCategoryName,
        public readonly array $searchIndex,
    ) {}

    public function isEmpty(): bool
    {
        return !$this->trending && !$this->popular && !$this->nearby;
    }
}
