<?php

/**
 * 「分析」直下のブログ記事インライン導線（ja 専用）。
 * 部屋の状態に合致する記事があるときだけ 1 本表示する（全室一律に出すと目障りなため）。
 * ページ最下部の棚は実測でほぼ読まれない（80%地点到達32%）ため、
 * GA4 実証済みのホットゾーン（グラフ/分析）に「いま生まれた疑問」への答えとして置く。
 *
 * 優先度: 人数上限接近 > 急成長 > 減少傾向。該当なしなら何も出さない。
 *
 * @var string $pattern OcNarrativeService の状態分類 (surge_up / strong_growth / declining 等)
 * @var int $member 現在のメンバー数
 */

if (\Shared\MimimalCmsConfig::$urlRoot !== '') return;

// 「この部屋のページを見ている人が、いま抱いている疑問」に答える記事だけを出す。
// SEO 着地用の記事（人数上限・増やし方・退会等）は文脈が合わないためここからは出さない
// （それらはSEO・トップの読み物棚・ブログ一覧が受け持つ）。
if (in_array($pattern, ['surge_up', 'strong_growth', 'recovering'], true)) {
    // 急成長・復活 → 「なんで伸びてる？」
    $_link = ['growing-openchat-features', '伸びるオープンチャットに共通する特徴とは？'];
} elseif (in_array($pattern, ['surge_down', 'declining', 'stagnant', 'shrinking_from_peak'], true)) {
    // 急減・減少・更新停止・ピークから縮小 → 「何があった？」(検索落ち・圏外の実データ記事)
    $_link = ['openchat-kensaku-ranking-ochi', '検索に出てこない・ランキングから消える部屋で何が起きている？'];
} elseif ($pattern === 'tiny') {
    // 小規模（最多層）→ もっと探したい人向け
    $_link = ['openchat-sagashikata', '自分に合うオープンチャットの探し方のコツ'];
} elseif ($pattern === 'new') {
    // 開設直後の閲覧者は作った本人が多い（SEO流入はまだ無い）→ 最初のメンバー集めまで
    $_link = ['openchat-hajimekata', 'オープンチャットの始め方・作り方（最初のメンバー集めまで）'];
} elseif (in_array($pattern, ['growing', 'gradual_up'], true)) {
    // 着実増加 → ページが順位グラフを見せている文脈そのもの「ランキングはどう決まる？」
    $_link = ['openchat-ranking-shikumi', 'オープンチャットのランキングの仕組みと公式に載る条件とは？'];
} elseif (in_array($pattern, ['stable', 'gradual_down'], true)) {
    // 安定・緩やか減 → 参加前の不安に答える記事
    $_link = ['openchat-kiken-anzen', 'オープンチャットは危険？参加前に知っておくリスクと自衛策'];
} else {
    return;
}
?>
<a class="oc-narrative__article unset" href="<?php echo url('blog/' . $_link[0]) ?>"><span aria-hidden="true">📖</span> <?php echo h($_link[1]) ?> →</a>
<style>
    /* 「押せそう」は下線で出す。リンク青はダークの無彩色基調から浮くため、文字は本文格・下線だけ薄める */
    .oc-narrative__article { display: inline-block; margin-top: 8px; font-size: 13px; font-weight: bold; color: var(--c-text-1); text-decoration: underline; text-decoration-thickness: 1px; text-underline-offset: 3px; text-decoration-color: var(--c-text-3); font-family: var(--font-family); }
    .oc-narrative__article:hover { color: var(--c-brand); text-decoration-color: var(--c-brand); }
</style>
