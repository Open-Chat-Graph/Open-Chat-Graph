<?php

/**
 * ホームの「読み物（ブログ）」棚。最新記事を最大4本、最大の入口（トップ）から回遊させる。
 * BlogService->list() は frontmatter のみの軽量読み（本文レンダリングなし）。ja 用。
 * viewComponent 経由なので自動エスケープは無く、表示値は h() でエスケープする。
 */

$_posts = array_slice(app(\App\Services\Blog\BlogService::class)->list(), 0, 4);
if (!$_posts) return;
?>
<article class="home-blog-shelf">
    <header class="hbs-head">
        <h2 class="hbs-title"><span>読み物</span><span aria-hidden="true">📖</span></h2>
        <a class="hbs-more unset" href="<?php echo url('blog') ?>">ブログをもっと見る →</a>
    </header>
    <ul class="hbs-list">
        <?php foreach ($_posts as $p) : ?>
            <li>
                <a class="hbs-card unset" href="<?php echo url('blog/' . $p['slug']) ?>">
                    <div class="hbs-card-t"><?php echo h($p['title']) ?></div>
                    <div class="hbs-card-m">
                        <?php if ($p['category']) : ?><span class="hbs-cat"><?php echo h($p['category']) ?></span><?php endif ?>
                        <span class="hbs-date"><?php echo h($p['date']) ?></span>
                    </div>
                </a>
            </li>
        <?php endforeach ?>
    </ul>
</article>
<style>
    .home-blog-shelf { padding: 0 1rem; font-family: var(--font-family); }
    .home-blog-shelf .hbs-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: .7rem; }
    .home-blog-shelf .hbs-title { all: unset; display: flex; align-items: center; gap: 5px; font-size: 16px; font-weight: bold; color: #111; }
    .home-blog-shelf .hbs-title span:last-child { font-size: 13px; }
    .home-blog-shelf .hbs-more { font-size: 12.5px; font-weight: bold; color: #06c755; text-decoration: none; white-space: nowrap; }
    .home-blog-shelf .hbs-list { all: unset; display: grid; gap: 8px; }
    .home-blog-shelf .hbs-list li { all: unset; }
    .home-blog-shelf .hbs-card { display: block; padding: .8rem .95rem; border: 1px solid #ededf0; border-radius: 12px; text-decoration: none; transition: border-color .15s, box-shadow .15s; }
    .home-blog-shelf .hbs-card:hover { border-color: #06c755; box-shadow: 0 3px 12px rgba(6, 199, 85, .09); }
    .home-blog-shelf .hbs-card-t { font-size: 14.5px; font-weight: bold; color: #111; line-height: 1.5; }
    .home-blog-shelf .hbs-card-m { margin-top: 5px; display: flex; align-items: center; gap: 7px; }
    .home-blog-shelf .hbs-cat { border: 1px solid #06c755; color: #05a648; border-radius: 999px; padding: 1px 9px; font-weight: bold; font-size: 11px; }
    .home-blog-shelf .hbs-date { font-size: 11.5px; color: #9aa0a6; }
    @media (min-width: 600px) { .home-blog-shelf .hbs-list { grid-template-columns: 1fr 1fr; } }
</style>
