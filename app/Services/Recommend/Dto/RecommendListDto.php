<?php

declare(strict_types=1);

namespace App\Services\Recommend\Dto;

use App\Config\AppConfig;
use App\Services\Recommend\Enum\RecommendListType;

class RecommendListDto
{
    const TAG_LIMIT = 30;

    public int $maxMemberCount;
    public array $mergedElements;
    public ?array $shuffledMergedElements = null;
    public array $sortAndUniqueTags = [];

    /** @var array{ id:int,name:string,img_url:string,member:int,table_name:string,emblem:int } $list */
    function __construct(
        public RecommendListType $type,
        public string $listName,
        public array $hour,
        public array $day,
        public array $week,
        public array $member,
        public string $hourlyUpdatedAt
    ) {
        $this->mergedElements = array_merge($hour, $day, $week, $member);
        if (count($this->mergedElements) > AppConfig::LIST_LIMIT_RECOMMEND) {
            $this->mergedElements = array_slice($this->mergedElements, 0, AppConfig::LIST_LIMIT_RECOMMEND);
        }

        $elements = array_column($this->mergedElements, 'member');
        $this->maxMemberCount = $elements ? max($elements) : 0;
    }

    function getList(bool $shuffle = true, ?int $limit = 0, int $excludeId = 0): array
    {
        $limit = $limit === 0 ? AppConfig::$listLimitTopRanking : $limit;

        $elements = $shuffle ? $this->buildShuffledList() : $this->mergedElements;
        if ($excludeId) $elements = array_filter($elements, fn($el) => $el['id'] !== $excludeId);

        $result = $limit ? array_slice($elements, 0, $limit) : $elements;
        return $result;
    }

    /**
     * URIベースの決定的シャッフル
     * 同じURIなら常に同じ順序、異なるURIなら異なる順序を生成
     *
     * @param array &$array シャッフル対象の配列
     */
    private function seededShuffle(array &$array): void
    {
        $uri = getHostAndUri();
        $seed = base62Hash($uri);

        // Fisher-Yatesアルゴリズムで決定的にシャッフル
        $count = count($array);
        for ($i = $count - 1; $i > 0; $i--) {
            // シードとインデックスから決定的な乱数を生成
            $hash = md5($seed . $i);
            $randomValue = hexdec(substr($hash, 0, 8));
            $j = $randomValue % ($i + 1);

            // スワップ
            [$array[$i], $array[$j]] = [$array[$j], $array[$i]];
        }
    }

    /** @return array{ id:int,name:string,img_url:string,member:int,table_name:string,emblem:int }[] */
    private function buildShuffledList(): array
    {
        if (is_array($this->shuffledMergedElements) && $this->shuffledMergedElements)
            return $this->shuffledMergedElements;

        $hour = $this->hour;
        $this->seededShuffle($hour);
        $length = $this->getSliceLength(count($hour));
        if (!$length) {
            $this->shuffledMergedElements = $hour;
            return $this->shuffledMergedElements;
        }

        $day = array_slice($this->day, 0, $length);
        $this->seededShuffle($day);
        $length = $this->getSliceLength(count($hour) + count($day));
        if (!$length) {
            $this->shuffledMergedElements = array_merge($hour, $day);
            return $this->shuffledMergedElements;
        };

        $week = array_slice($this->week, 0, $length);
        $this->seededShuffle($week);
        $length = $this->getSliceLength(count($hour) + count($day) + count($week));
        if (!$length) {
            $result = array_merge($day, $week);
            $this->seededShuffle($result);
            $this->shuffledMergedElements = array_merge($hour, $result);
            return $this->shuffledMergedElements;
        };

        $member = array_slice($this->member, 0, $length);
        $this->seededShuffle($member);

        $result = array_merge($day, $week);
        $this->seededShuffle($result);

        $this->shuffledMergedElements = array_merge($hour, $result, $member);
        return $this->shuffledMergedElements;
    }

    private function getSliceLength(int $count)
    {
        $count = AppConfig::LIST_LIMIT_RECOMMEND - $count;
        return max($count, 0);
    }

    /**
     * 「メンバー数が近い」ルームを人数レンジで絞り込んで返す（/oc の関連ルーム用）。
     *
     * 母集団は伸び部屋(hour) + 人数降順の裾(member, LIST_LIMIT_RECOMMEND_POOL件)の全候補。
     * 表示用 mergedElements(30件キャップ)とは独立に探すため取りこぼしが少ない。
     * 並び順は旧 SimilarSizeRoomRepository の SQL と同一: |人数差| ASC, member DESC。
     *
     * @return array{ id:int,name:string,img_url:string,member:int,table_name:string,emblem:int }[]
     */
    function findByMemberRange(int $excludeId, int $currentMember, int $minMember, int $maxMember, int $limit): array
    {
        $seen = [];
        $result = [];
        foreach (array_merge($this->hour, $this->day, $this->week, $this->member) as $row) {
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
