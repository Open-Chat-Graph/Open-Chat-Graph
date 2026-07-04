<?php

/**
 * ホームの「読み物（ブログ）」棚。よく読まれている記事を最大2本、最大の入口（トップ）から回遊させる。
 * BlogService->list() は frontmatter のみの軽量読み（BlogSummaryDto[] を返す・本文なし）。ja 用。
 * viewComponent 経由なので自動エスケープは無く、表示値は h() でエスケープする。
 */

// トップ棚は GA4 の実測PV上位をハードコードで指名する（2026-07-04 時点・直近28日:
// growing 161PV / kensaku-ranking-ochi 108PV / kyujosho 90PV / … / ninzu-jogen 33PV）。
// ブログ全体のPVがまだ小さく上位の偏りも安定しているため自動集計はせず、
// 順位が入れ替わったらここを手で更新する。指名記事が消えた場合は更新日順で埋める。
$_pinnedSlugs = ['growing-openchat-features', 'openchat-kensaku-ranking-ochi'];
$_limit = 2;

$_all = app(\App\Services\Blog\BlogService::class)->list();
$_bySlug = array_combine(array_map(fn($p) => $p->slug, $_all), $_all);
$_posts = array_values(array_filter(array_map(fn($slug) => $_bySlug[$slug] ?? null, $_pinnedSlugs)));
foreach ($_all as $_p) {
    if (count($_posts) >= $_limit) break;
    if (!in_array($_p, $_posts, true)) $_posts[] = $_p;
}
$_posts = array_slice($_posts, 0, $_limit);
if (!$_posts) return;
?>
<article class="home-blog-shelf">
    <header class="hbs-head">
        <?php // 見出しは他セクション（room_list.css .openchat-list-title=グラデ文字）とスタイルを共通化 ?>
        <h2 class="hbs-title"><span class="openchat-list-title">読み物</span><span aria-hidden="true">📖</span></h2>
        <a class="hbs-more unset" href="<?php echo url('blog') ?>">ブログをもっと見る →</a>
    </header>
    <ul class="hbs-list">
        <?php foreach ($_posts as $p) : ?>
            <li>
                <a class="hbs-card unset" href="<?php echo url('blog/' . $p->slug) ?>">
                    <div class="hbs-card-t"><?php echo h($p->title) ?></div>
                    <div class="hbs-card-m">
                        <?php if ($p->category) : ?><span class="hbs-cat"><?php echo h($p->category) ?></span><?php endif ?>
                        <?php // 鮮度が伝わる更新日を表示（公開日は記事ページで表示） ?>
                        <span class="hbs-date"><?php echo h($p->updated) ?></span>
                    </div>
                </a>
            </li>
        <?php endforeach ?>
    </ul>
</article>
<style>
    /* 上下 .5rem は隣接セクション（.top-ranking の padding: .5rem 0）と同じ余白リズム */
    .home-blog-shelf { padding: .5rem 1rem; font-family: var(--font-family); }
    .home-blog-shelf .hbs-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
    .home-blog-shelf .hbs-title { all: unset; display: flex; align-items: center; gap: 5px; }
    .home-blog-shelf .hbs-title span:last-child { font-size: 15px; }
    /* タップ領域は padding で広げ、負マージンで見た目の位置は変えない */
    .home-blog-shelf .hbs-more { font-size: 13.5px; font-weight: bold; color: var(--c-brand); text-decoration: none; white-space: nowrap; padding: .6rem 0 .6rem .6rem; margin: -.6rem 0; }
    .home-blog-shelf .hbs-list { all: unset; display: grid; gap: 8px; }
    .home-blog-shelf .hbs-list li { all: unset; }
    .home-blog-shelf .hbs-card { display: block; padding: .8rem .95rem; border: 1px solid var(--c-border-muted); border-radius: 12px; text-decoration: none; transition: border-color .15s, box-shadow .15s; }
    .home-blog-shelf .hbs-card:hover { border-color: var(--c-brand); box-shadow: 0 3px 12px var(--c-brand-shadow-soft); }
    .home-blog-shelf .hbs-card-t { font-size: 14.5px; font-weight: bold; color: var(--c-text-1); line-height: 1.5; }
    .home-blog-shelf .hbs-card-m { margin-top: 5px; display: flex; align-items: center; gap: 7px; }
    .home-blog-shelf .hbs-cat { border: 1px solid var(--c-brand); color: var(--c-btn-blog-text); border-radius: 999px; padding: 1px 9px; font-weight: bold; font-size: 11px; }
    .home-blog-shelf .hbs-date { font-size: 11.5px; color: var(--c-rg-muted); }
    @media (min-width: 600px) { .home-blog-shelf .hbs-list { grid-template-columns: 1fr 1fr; } }
</style>
