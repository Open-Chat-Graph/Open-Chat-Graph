<?php

declare(strict_types=1);

namespace App\Services\Narrative;

/**
 * 部屋の状態（OcNarrativeService の pattern）に合うブログ記事リンクを 1 つ解決する。
 *
 * 方針:「そのページを見ている人がいま抱く疑問」に答える記事だけを返し、該当なしは null。
 * 記事は 2026-10 に 4 本（急上昇/伸びる・検索落ち・人数上限・人数を増やす）へ統合済みで、
 * どの状態からも必ずこの 4 本のどれかへ送る。
 *
 * 表示の可否（ja のみ等）や URL 解決・エスケープは呼び出し側／テンプレートの責務。
 * 本クラスは「pattern → 記事(slug,label)」という純粋なマッピングだけを持つ（単体テスト可能）。
 */
final class OcBlogContextLinkResolver
{
    /**
     * @param string $pattern OcNarrativeService の状態分類
     * @return array{slug: string, label: string}|null 該当記事。無ければ null
     */
    public function resolve(string $pattern, ?array $rising = null): ?array
    {
        // rising(急上昇/ランキング)の実掲載状態を pattern より優先する（narrative に相乗り済み）。
        // surge_up 等の「人数の伸び」指標と違い、実際の急上昇ランキング掲載に基づく導線。
        if ($rising !== null) {
            // 「すべて」急上昇の上位(=top5=アプリTOP露出)に週内到達 → 露出を活かす
            if (!empty($rising['top5_all_week'])) {
                return ['slug' => 'growing-openchat-features', 'label' => '急上昇ランキングの上位に入っています。さらに伸ばすには？'];
            }
            // ランキングには載る(=基準内)が急上昇は未掲載 → 急上昇を狙う本命。
            // ランキング非掲載(=基準外の可能性)には出さない＝この条件を満たさない。
            if (!empty($rising['on_ranking_week']) && empty($rising['on_rising_week'])) {
                return ['slug' => 'growing-openchat-features', 'label' => '急上昇ランキングに載る条件は？アプリTOP露出を狙う'];
            }
        }

        return match (true) {
            // 急成長・復活 →「なんで伸びてる？」(rising 状態が非該当/未取得のときのフォールバック)
            in_array($pattern, ['surge_up', 'strong_growth', 'recovering'], true)
            => ['slug' => 'growing-openchat-features', 'label' => '伸びるオープンチャットに共通する特徴とは？'],

            // 急減・減少・更新停止・ピークから縮小 →「何があった？」（検索落ち・圏外の実データ）
            in_array($pattern, ['surge_down', 'declining', 'stagnant', 'shrinking_from_peak'], true)
            => ['slug' => 'openchat-kensaku-ranking-ochi', 'label' => '検索に出てこない・ランキングから消える部屋で何が起きている？'],

            // 小規模（最多層）→「この人数は普通？」に実データで答える（中央値18人・9割が100人未満）
            $pattern === 'tiny'
            => ['slug' => 'openchat-ninzu-jogen', 'label' => 'オープンチャットは何人が普通？人数の上限と実データ'],

            // 開設直後（閲覧者は作った本人が多い）→ 最初のメンバー集め（作った直後の24時間ルールも）
            $pattern === 'new'
            => ['slug' => 'openchat-member-fuyasu', 'label' => '最初のメンバーの集め方（作った直後に検索に出ない理由も）'],

            // 着実増加 → ページが順位グラフを見せている文脈「ランキングはどう決まる？」（仕組みは growing に統合）
            in_array($pattern, ['growing', 'gradual_up'], true)
            => ['slug' => 'growing-openchat-features', 'label' => 'ランキングの仕組みと急上昇に載る条件（何位まで載る？）'],

            // 安定・緩やか減 → 伸びが止まった部屋の次の一手
            in_array($pattern, ['stable', 'gradual_down'], true)
            => ['slug' => 'openchat-member-fuyasu', 'label' => '人数が止まったときの次の一手（人集めのコツ）'],

            default => null,
        };
    }
}
