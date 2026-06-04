<!DOCTYPE html>
<html lang="<?php echo t('ja') ?>">
<?php viewComponent('head', compact('_css', '_meta')) ?>

<body>
    <?php viewComponent('site_header') ?>
    <main style="overflow: hidden;">
        <article class="terms" style="max-width: 760px; margin: 0 auto; padding: 0 1rem;">
            <h1 style="letter-spacing: 0;">ブログ</h1>
            <p>LINEオープンチャットの<b>運営のコツ</b>・<b>検索やランキングの仕組み</b>・<b>トレンド</b>を、オプチャグラフ独自のデータをもとに解説します。</p>

            <?php if (empty($articles)): ?>
                <p style="color:#888;">記事は準備中です。</p>
            <?php else: ?>
                <!-- $articles は View 層で自動エスケープ済み（h() で二重エスケープしないこと） -->
                <ul style="list-style:none; padding:0; margin: 1.5rem 0 0;">
                    <?php foreach ($articles as $a): ?>
                        <li style="margin:0 0 1.25rem; padding:0 0 1.25rem; border-bottom:1px solid #eee;">
                            <a href="<?php echo url('blog/' . $a['slug']) ?>" style="font-size:18px; font-weight:bold; text-decoration:none; line-height:1.5;"><?php echo $a['title'] ?></a>
                            <p style="color:#999; font-size:12.5px; margin:5px 0;">
                                <?php echo $a['date'] ?><?php if ($a['category']): ?> ・ <?php echo $a['category'] ?><?php endif ?>
                            </p>
                            <?php if ($a['description']): ?>
                                <p style="color:#555; font-size:14px; margin:0; line-height:1.7;"><?php echo $a['description'] ?></p>
                            <?php endif ?>
                        </li>
                    <?php endforeach ?>
                </ul>
            <?php endif ?>
        </article>
    </main>
    <?php \App\Views\Ads\GoogleAdsense::loadAdsTag() ?>
    <?php viewComponent('footer_inner') ?>
    <?php echo $_breadcrumbsShema ?>
</body>

</html>
