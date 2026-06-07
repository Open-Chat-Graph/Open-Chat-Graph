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

// 5,000 人上限（拡張で 1 万人）に近い・超えている部屋 → 上限の仕組み記事
if ($member >= 4500) {
    $_link = ['openchat-ninzu-jogen', 'オープンチャットの人数は何人まで？上限と「拡張」の仕組み'];
} elseif (in_array($pattern, ['surge_up', 'strong_growth'], true)) {
    $_link = ['growing-openchat-features', '伸びるオープンチャットに共通する特徴とは？'];
} elseif (in_array($pattern, ['declining', 'shrinking_from_peak', 'surge_down'], true)) {
    $_link = ['openchat-member-fuyasu', 'オープンチャットのメンバーを増やすには？'];
} else {
    return;
}
?>
<a class="oc-narrative__article unset" href="<?php echo url('blog/' . $_link[0]) ?>"><span aria-hidden="true">📖</span> <?php echo h($_link[1]) ?> →</a>
<style>
    .oc-narrative__article { display: inline-block; margin-top: 8px; font-size: 13px; font-weight: bold; color: var(--c-brand); text-decoration: none; font-family: var(--font-family); }
    .oc-narrative__article:hover { text-decoration: underline; }
</style>
