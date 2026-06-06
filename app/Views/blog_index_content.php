<!DOCTYPE html>
<html lang="<?php echo t('ja') ?>">
<?php viewComponent('head', compact('_css', '_meta', '_schema')) ?>

<body>
    <?php viewComponent('site_header') ?>
    <main class="blog-main" style="overflow: hidden;">
        <!-- $articles は View 層で自動エスケープ済み -->
        <div class="blog">
            <nav class="blog-crumb">
                <a href="<?php echo url('') ?>">トップ</a><span class="sep">›</span><span>ブログ</span>
            </nav>
            <h1 class="blog-title">ブログ</h1>
            <p class="blog-lead">LINEオープンチャットの<b>運営のコツ</b>・<b>検索やランキングの仕組み</b>・<b>トレンド</b>を、オプチャグラフ独自のデータをもとに解説します。</p>

            <?php if (empty($articles)): ?>
                <p style="color:#8a9097;">記事は準備中です。</p>
            <?php else: ?>
                <ul class="blog-cards">
                    <?php foreach ($articles as $a): ?>
                        <li>
                            <a class="blog-card" href="<?php echo url('blog/' . $a['slug']) ?>">
                                <div class="t"><?php echo $a['title'] ?></div>
                                <div class="m">
                                    <?php if ($a['category']): ?><span class="cat"><?php echo $a['category'] ?></span><?php endif ?>
                                    <span class="date"><?php echo $a['date'] ?></span>
                                </div>
                                <?php if ($a['description']): ?><p class="d"><?php echo $a['description'] ?></p><?php endif ?>
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
