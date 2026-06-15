<?php

declare(strict_types=1);

namespace App\Services\Narrative;

/**
 * 部屋の状態（OcNarrativeService の pattern）に合うブログ記事リンクを 1 つ解決する。
 *
 * 方針:「そのページを見ている人がいま抱く疑問」に答える記事だけを返し、該当なしは null。
 * SEO 着地用の記事（人数上限・増やし方・退会等）は文脈が合わないため返さない
 * （それらは SEO・トップの読み物棚・ブログ一覧が担当）。
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
    public function resolve(string $pattern): ?array
    {
        return match (true) {
            // 急成長(急上昇中) → この勢いで急上昇ランキング(=アプリTOP露出)を狙う記事
            $pattern === 'surge_up'
            => ['slug' => 'openchat-kyujosho-ranking', 'label' => 'この勢いで急上昇ランキングに載るには？'],

            // 強い成長・復活 →「なんで伸びてる？」(続けて伸びる部屋の特徴)
            in_array($pattern, ['strong_growth', 'recovering'], true)
            => ['slug' => 'growing-openchat-features', 'label' => '伸びるオープンチャットに共通する特徴とは？'],

            // 急減・減少・更新停止・ピークから縮小 →「何があった？」（検索落ち・圏外の実データ）
            in_array($pattern, ['surge_down', 'declining', 'stagnant', 'shrinking_from_peak'], true)
            => ['slug' => 'openchat-kensaku-ranking-ochi', 'label' => '検索に出てこない・ランキングから消える部屋で何が起きている？'],

            // 小規模（最多層）→ もっと探したい人向け
            $pattern === 'tiny'
            => ['slug' => 'openchat-sagashikata', 'label' => '自分に合うオープンチャットの探し方のコツ'],

            // 開設直後（閲覧者は作った本人が多い）→ 最初のメンバー集めまで
            $pattern === 'new'
            => ['slug' => 'openchat-hajimekata', 'label' => 'オープンチャットの始め方・作り方（最初のメンバー集めまで）'],

            // 着実増加 → ページが順位グラフを見せている文脈「ランキングはどう決まる？」
            in_array($pattern, ['growing', 'gradual_up'], true)
            => ['slug' => 'openchat-ranking-shikumi', 'label' => 'オープンチャットのランキングの仕組みと公式に載る条件とは？'],

            // 安定・緩やか減 → 参加前の不安に答える
            in_array($pattern, ['stable', 'gradual_down'], true)
            => ['slug' => 'openchat-kiken-anzen', 'label' => 'オープンチャットは危険？参加前に知っておくリスクと自衛策'],

            default => null,
        };
    }
}
