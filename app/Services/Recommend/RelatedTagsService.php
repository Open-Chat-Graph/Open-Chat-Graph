<?php

declare(strict_types=1);

namespace App\Services\Recommend;

use App\Models\RecommendRepositories\RecommendRankingRepository;

/**
 * タグ間の意味的な近さ（関連タグ）を事前計算する。
 *
 * 根拠: タグ付けエンジンは 1部屋 = 1タグ（recommend）を優先順位で確定させるが、
 * 2・3番手にマッチしたタグも oc_tag / oc_tag2 に残っている。
 * 「優先順位次第でどちらにも転び得た部屋」を多く共有するタグ同士は意味的に近い
 * （例: ガチャガチャ ↔ シール / トレカ）。同一カテゴリよりも強いシグナル。
 *
 * 結果は静的データ生成（StaticDataGenerator::updateStaticData → @relatedTags）で
 * 毎時キャッシュされ、表示側（ThemeDiscoveryService）は読むだけにする。
 */
class RelatedTagsService
{
    /** 1タグあたり保存する関連タグ数（表示側の棚上限より少し多めに持つ） */
    private const RELATED_LIMIT = 16;

    public function __construct(
        private RecommendRankingRepository $recommendRankingRepository,
    ) {}

    /**
     * 全タグの関連タグマップを構築する。
     * スコアは共起数を各タグの総共起量で正規化した cosine 近似値。
     * 母数の大きい汎用タグが共起件数だけで上位を独占する偏りを抑える。
     *
     * @return array<string, array<string, int>> [タグ => [関連タグ => スコア（降順）]]
     */
    public function build(): array
    {
        $score = [];
        foreach ($this->recommendRankingRepository->getRelatedTagPairs() as $row) {
            $a = (string)$row['tag'];
            $b = (string)$row['related'];
            $cnt = (int)$row['cnt'];
            if ($a === '' || $b === '') continue;
            // 対称化: A の2番手が B でも、B の2番手が A でも「近い」事実は同じ
            $score[$a][$b] = ($score[$a][$b] ?? 0) + $cnt;
            $score[$b][$a] = ($score[$b][$a] ?? 0) + $cnt;
        }

        $marginals = [];
        foreach ($score as $tag => $related) {
            $marginals[$tag] = array_sum($related);
        }

        foreach ($score as $tag => $related) {
            $normalized = [];
            foreach ($related as $other => $count) {
                $denominator = sqrt(($marginals[$tag] ?? 0) * ($marginals[$other] ?? 0));
                if ($denominator <= 0) continue;
                // 静的キャッシュの既存契約(string=>int)を維持しつつ精度を確保する。
                $normalized[$other] = (int)round(($count / $denominator) * 1_000_000);
            }
            arsort($normalized, SORT_NUMERIC);
            $score[$tag] = array_slice($normalized, 0, self::RELATED_LIMIT, true);
        }

        return $score;
    }
}
