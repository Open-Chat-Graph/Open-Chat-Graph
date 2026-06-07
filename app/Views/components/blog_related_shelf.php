<?php

/**
 * 「読み物（ブログ）」関連記事ミニ棚。主要ページ（/oc・/recommend）からブログへ回遊させる。
 * 呼び出し側がページ文脈に合う記事 slug を渡す（ja 専用・該当記事が無ければ非表示）。
 * BlogService->list() は frontmatter のみの軽量読み（リクエスト内キャッシュあり・本文なし）。
 * viewComponent 経由なので自動エスケープは無く、表示値は h() でエスケープする。
 *
 * @var string[] $slugs 表示する記事 slug（この並び順で表示）
 */

if (\Shared\MimimalCmsConfig::$urlRoot !== '') return;

$_bySlug = [];
foreach (app(\App\Services\Blog\BlogService::class)->list() as $_p) {
    $_bySlug[$_p->slug] = $_p;
}
$_posts = array_values(array_filter(array_map(static fn($s) => $_bySlug[$s] ?? null, $slugs)));
if (!$_posts) return;
?>
<aside class="blog-related-shelf">
    <header class="brs-head">
        <h2 class="brs-title">読み物<span aria-hidden="true">📖</span></h2>
        <a class="brs-more unset" href="<?php echo url('blog') ?>">ブログをもっと見る →</a>
    </header>
    <ul class="brs-list">
        <?php foreach ($_posts as $p) : ?>
            <li>
                <a class="brs-card unset" href="<?php echo url('blog/' . $p->slug) ?>">
                    <?php // 一覧では「｜」前の主題だけ見せて1行に収める（全文タイトルは記事側で） ?>
                    <span class="brs-card-t"><?php echo h(explode('｜', $p->title)[0]) ?></span>
                    <?php if ($p->category) : ?><span class="brs-cat"><?php echo h($p->category) ?></span><?php endif ?>
                </a>
            </li>
        <?php endforeach ?>
    </ul>
</aside>
<style>
    .blog-related-shelf { margin-top: var(--sp-section-gap); padding: 0 1rem; font-family: var(--font-family); }
    .blog-related-shelf .brs-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
    .blog-related-shelf .brs-title { all: unset; display: flex; align-items: center; gap: 5px; font-size: 16px; font-weight: bold; color: var(--c-text-1); }
    /* タップ領域は padding で広げ、負マージンで見た目の位置は変えない */
    .blog-related-shelf .brs-more { font-size: 13px; font-weight: bold; color: var(--c-brand); text-decoration: none; white-space: nowrap; padding: .6rem 0 .6rem .6rem; margin: -.6rem 0; }
    .blog-related-shelf .brs-list { all: unset; display: grid; gap: 7px; }
    .blog-related-shelf .brs-list li { all: unset; }
    .blog-related-shelf .brs-card { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: .65rem .9rem; border: 1px solid var(--c-border-muted); border-radius: 12px; text-decoration: none; transition: border-color .15s, box-shadow .15s; }
    .blog-related-shelf .brs-card:hover { border-color: var(--c-brand); box-shadow: 0 3px 12px var(--c-brand-shadow-soft); }
    .blog-related-shelf .brs-card-t { font-size: 13.5px; font-weight: bold; color: var(--c-text-1); line-height: 1.5; }
    .blog-related-shelf .brs-cat { flex-shrink: 0; border: 1px solid var(--c-brand); color: var(--c-btn-blog-text); border-radius: 999px; padding: 1px 9px; font-weight: bold; font-size: 10.5px; white-space: nowrap; }
</style>
