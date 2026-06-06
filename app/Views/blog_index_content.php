<!DOCTYPE html>
<html lang="<?php echo t('ja') ?>">
<?php viewComponent('head', compact('_css', '_meta', '_schema')) ?>

<body>
    <?php viewComponent('site_header') ?>
    <main class="no-pad blog-main">
        <!-- $articles は BlogSummaryDto[]。プロパティ文字列は View 層で自動エスケープ済み -->
        <div class="blog">
            <nav class="blog-crumb">
                <a href="<?php echo url('') ?>">トップ</a><span class="sep">›</span><span>ブログ</span>
            </nav>
            <h1 class="blog-title">ブログ</h1>
            <p class="blog-lead">LINEオープンチャットの<b>運営のコツ</b>・<b>検索やランキングの仕組み</b>・<b>トレンド</b>を、オプチャグラフ独自のデータをもとに解説します。</p>

            <?php if (empty($articles)): ?>
                <p style="color:var(--c-text-steel);">記事は準備中です。</p>
            <?php else: ?>
                <ul class="blog-cards">
                    <?php foreach ($articles as $a): ?>
                        <li>
                            <a class="blog-card" href="<?php echo url('blog/' . $a->slug) ?>">
                                <div class="t"><?php echo $a->title ?></div>
                                <div class="m">
                                    <?php if ($a->category): ?><span class="cat"><?php echo $a->category ?></span><?php endif ?>
                                    <?php // 一覧では鮮度が伝わる更新日を表示（公開日・更新日の両方は記事ページで表示） ?>
                                    <span class="date"><?php echo $a->updated ?></span>
                                </div>
                                <?php if ($a->description): ?><p class="d"><?php echo $a->description ?></p><?php endif ?>
                                <div class="go">続きを読む</div>
                            </a>
                        </li>
                    <?php endforeach ?>
                </ul>
            <?php endif ?>
        </div>
    </main>
    <?php \App\Views\Ads\GoogleAdsense::loadAdsTag() ?>
    <?php viewComponent('footer_inner') ?>
    <?php echo $_breadcrumbsShema ?>
</body>

</html>
