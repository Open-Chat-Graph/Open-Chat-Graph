<?php

declare(strict_types=1);

namespace App\Services\Recommend\Dto;

use App\Config\AppConfig;
use App\Services\Recommend\Enum\RecommendListType;

/**
 * おすすめ/カテゴリ/公式ランキング1件分のデータ。
 *
 * $list はランキング全体(最大 LIST_LIMIT_RECOMMEND_POOL 件)を単一基準で並べたもの。
 * 表示は先頭 LIST_LIMIT_RECOMMEND 件($mergedElements)、/oc 関連ルームの人数絞り込みは
 * $list 全体(findByMemberRange)を使う。
 *
 * AllowDynamicProperties: デプロイ直後の旧 .dat(hour/day/week/member 形式)を unserialize しても
 * 落ちないための防御。次回の毎時バッチ再生成で新形式に置き換わる。
 */
#[\AllowDynamicProperties]
class RecommendListDto
{
    public int $maxMemberCount;
    public array $mergedElements;
    public ?array $shuffledMergedElements = null;
    public array $sortAndUniqueTags = [];

    /**
     * テーマの勢い(RecommendGrowthRepository::themeMomentum の結果)。毎時バッチの .dat 生成時に
     * 事前計算して同梱。null = 未計算 → ページ側がライブ計算にフォールバック。[] = 計算済みデータ不足。
     */
    public ?array $themeMomentum = null;

    /** このタグの関連タグ(タグ => 共起スコア)。毎時バッチが .dat に同梱。null = 未同梱(旧 .dat)。 */
    public ?array $relatedTags = null;

    /** @var array{ id:int,name:string,img_url:string,member:int,table_name:string,emblem:int }[] $list */
    function __construct(
        public RecommendListType $type,
        public string $listName,
        public array $list = [],
        public string $hourlyUpdatedAt = ''
    ) {
        $this->mergedElements = array_slice($list, 0, AppConfig::LIST_LIMIT_RECOMMEND);
        $members = array_column($this->mergedElements, 'member');
        $this->maxMemberCount = $members ? max($members) : 0;
    }

    function getList(bool $shuffle = true, ?int $limit = 0, int $excludeId = 0): array
    {
        $limit = $limit === 0 ? AppConfig::$listLimitTopRanking : $limit;

        $elements = $shuffle ? $this->buildShuffledList() : $this->mergedElements;
        if ($excludeId) $elements = array_filter($elements, fn($el) => $el['id'] !== $excludeId);

        return $limit ? array_slice($elements, 0, $limit) : $elements;
    }

    /**
     * URIベースの決定的シャッフル。同じURIなら常に同じ順序。
     */
    private function seededShuffle(array &$array): void
    {
        $seed = base62Hash(getHostAndUri());

        $count = count($array);
        for ($i = $count - 1; $i > 0; $i--) {
            $hash = md5($seed . $i);
            $j = hexdec(substr($hash, 0, 8)) % ($i + 1);
            [$array[$i], $array[$j]] = [$array[$j], $array[$i]];
        }
    }

    /** @return array{ id:int,name:string,img_url:string,member:int,table_name:string,emblem:int }[] */
    private function buildShuffledList(): array
    {
        if (is_array($this->shuffledMergedElements) && $this->shuffledMergedElements) {
            return $this->shuffledMergedElements;
        }
        $list = $this->mergedElements;
        $this->seededShuffle($list);
        $this->shuffledMergedElements = $list;
        return $list;
    }

    /**
     * 「メンバー数が近い」ルームを人数レンジで絞り込んで返す(/oc の関連ルーム用)。
     * 母集団は表示30件ではなくランキング全体($list, 最大 LIST_LIMIT_RECOMMEND_POOL 件)。
     * 並び順: |人数差| ASC, member DESC。
     *
     * @return array{ id:int,name:string,img_url:string,member:int,table_name:string,emblem:int }[]
     */
    function findByMemberRange(int $excludeId, int $currentMember, int $minMember, int $maxMember, int $limit): array
    {
        $seen = [];
        $result = [];
        // ?? []: デプロイ直後の旧形式 .dat($list を持たない)を unserialize した場合の防御。
        foreach (($this->list ?? []) as $row) {
            $id = (int)$row['id'];
            if ($id === $excludeId || isset($seen[$id])) continue;
            $seen[$id] = true;

            $m = (int)$row['member'];
            if ($m < $minMember || $m > $maxMember) continue;
            $result[] = $row;
        }

        usort($result, fn(array $a, array $b) =>
            (abs((int)$a['member'] - $currentMember) <=> abs((int)$b['member'] - $currentMember))
                ?: ((int)$b['member'] <=> (int)$a['member']));

        return array_slice($result, 0, $limit);
    }

    /** @return array{ id:int,name:string,img_url:string,member:int,table_name:string,emblem:int }[] */
    function getPreviewList(int $len): array
    {
        return array_slice($this->mergedElements, 0, $len);
    }

    function getCount(): int
    {
        return count($this->mergedElements);
    }
}
