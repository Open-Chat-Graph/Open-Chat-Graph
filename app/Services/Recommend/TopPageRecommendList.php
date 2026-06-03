<?php

declare(strict_types=1);

namespace App\Services\Recommend;

use App\Models\Repositories\Recommend\TrendingThemeRepositoryInterface;
use App\Services\Recommend\TagDefinition\Ja\RecommendTagFilters;

class TopPageRecommendList
{
    public function __construct(
        private TrendingThemeRepositoryInterface $trendingThemeRepository,
    ) {}

    function getList(int $limit)
    {
        // 取得(SQL)はリポジトリに委譲し、本サービスはテーマ別の集計・並びに専念する。
        // 旧実装は「伸びている部屋の件数」順だったが、小規模室を多数持つ大テーマが
        // 少数でも大きく伸びているテーマより上位になりがちだったため、増加量合計順に改善。
        $hour = $this->trendingThemeRepository->fetchRisingRoomsByHour();
        $hour2 = $this->trendingThemeRepository->fetchRisingRoomsByHourAnd24h();
        $hour24 = $this->trendingThemeRepository->fetchRisingRoomsByDay();

        $filter = RecommendTagFilters::getTopPageTagFilter();

        // breadth(伸び室の件数)で足切りしつつ、intensity(増加量合計)で並べる。
        $tags = $this->rankByMomentum($hour, 2, $filter);
        $tags1 = $this->rankByMomentum($hour2, 3, array_merge($filter, $tags));
        $hourTags = array_merge($tags, $tags1);

        $tags2 = $this->rankByMomentum($hour24, 4, array_merge($filter, $hourTags));

        return ['hour' => $hourTags, 'hour24' => array_slice($tags2, 0, $limit)];
    }

    /**
     * 「いま伸びている部屋」をテーマ別に集計し、急上昇テーマを返す。
     *
     * - breadth: 伸びている部屋が $minCount 室以上あるテーマだけを採用（単発室のノイズ除去）。
     * - intensity: 増加量(diff_member)の合計が大きい順に並べる。同点は件数の多い順。
     *
     * @param array<int,array{tag?:?string,diff_member?:int|null}> $rows
     * @param string[] $exclude 除外するタグ（トップページ用フィルタ＋既に上位段で採用済みのタグ）
     * @return string[] 増加量合計の降順のタグ名
     */
    private function rankByMomentum(array $rows, int $minCount, array $exclude): array
    {
        $excludeSet = array_flip($exclude);
        $count = [];
        $sum = [];
        foreach ($rows as $row) {
            $tag = (string)($row['tag'] ?? '');
            if ($tag === '' || isset($excludeSet[$tag])) continue;
            $count[$tag] = ($count[$tag] ?? 0) + 1;
            $sum[$tag] = ($sum[$tag] ?? 0) + max(0, (int)($row['diff_member'] ?? 0));
        }

        $tags = array_keys(array_filter($count, fn($c) => $c >= $minCount));
        usort($tags, fn($a, $b) => ($sum[$b] <=> $sum[$a]) ?: ($count[$b] <=> $count[$a]));

        return $tags;
    }
}
