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

// 訪問者の大半は「部屋を探している人」なので、運営者向け記事（増やし方等）は
// 管理人が見ている可能性が高い状態（小規模・ピークから縮小）だけに絞り、
// よくある状態（安定・増加・減少）には探す人の疑問に刺さる記事を当てる。
if ($member >= 4500) {
    // 5,000 人上限（拡張で 1 万人）に近い・超えている部屋
    $_link = ['openchat-ninzu-jogen', 'オープンチャットの人数は何人まで？上限と「拡張」の仕組み'];
} elseif (in_array($pattern, ['surge_up', 'strong_growth', 'recovering'], true)) {
    // 急成長・復活 → 「なぜ伸びてる？」
    $_link = ['growing-openchat-features', '伸びるオープンチャットに共通する特徴とは？'];
} elseif (in_array($pattern, ['surge_down', 'declining', 'stagnant'], true)) {
    // 急減・減少・更新停止 → 「何があった？」(検索落ち・圏外の実データ記事)
    $_link = ['openchat-kensaku-ranking-ochi', '検索に出てこない・ランキングから消える部屋で何が起きている？'];
} elseif ($pattern === 'shrinking_from_peak') {
    // かつて大規模→縮小 = 管理人が見ている可能性が最も高い文脈にだけ運営者向け記事
    $_link = ['openchat-member-fuyasu', 'オープンチャットのメンバーを増やすには？'];
} elseif ($pattern === 'tiny') {
    // 小規模（最多層）→ もっと探したい人向け
    $_link = ['openchat-sagashikata', '自分に合うオープンチャットの探し方のコツ'];
} elseif ($pattern === 'new') {
    // 開設直後 → 「自分も作ってみたい」
    $_link = ['openchat-hajimekata', 'オープンチャットの始め方・作り方（最初のメンバー集めまで）'];
} elseif (in_array($pattern, ['growing', 'gradual_up'], true)) {
    // 着実増加 → 「ランキングはどう決まる？」
    $_link = ['openchat-ranking-shikumi', 'オープンチャットのランキングの仕組みと公式に載る条件とは？'];
} elseif (in_array($pattern, ['stable', 'gradual_down'], true)) {
    // 安定・緩やか減（最多層）→ 参加前の不安に答える記事
    $_link = ['openchat-kiken-anzen', 'オープンチャットは危険？参加前に知っておくリスクと自衛策'];
} else {
    return;
}
?>
<a class="oc-narrative__article unset" href="<?php echo url('blog/' . $_link[0]) ?>"><span aria-hidden="true">📖</span> <?php echo h($_link[1]) ?> →</a>
<style>
    .oc-narrative__article { display: inline-block; margin-top: 8px; font-size: 13px; font-weight: bold; color: var(--c-brand); text-decoration: none; font-family: var(--font-family); }
    .oc-narrative__article:hover { text-decoration: underline; }
</style>
