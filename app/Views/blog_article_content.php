<!DOCTYPE html>
<html lang="<?php echo t('ja') ?>">
<?php viewComponent('head', compact('_css', '_meta', '_schema')) ?>

<body>
    <?php viewComponent('site_header') ?>
    <main style="overflow: hidden;">
        <article class="terms blog-article" style="max-width: 760px; margin: 0 auto; padding: 0 1rem;">
            <nav style="font-size:12.5px; color:#999; margin: 8px 0 4px;">
                <a href="<?php echo url('') ?>" style="color:#999;">トップ</a> ›
                <a href="<?php echo url('blog') ?>" style="color:#999;">ブログ</a>
            </nav>
            <!-- $article のテキストは View 層で自動エスケープ済み（h() で二重エスケープしないこと） -->
            <h1 style="letter-spacing: 0;"><?php echo $article['title'] ?></h1>
            <p style="color:#999; font-size:12.5px; margin:4px 0 1.5rem;">
                <?php echo $article['date'] ?><?php if ($article['category']): ?> ・ <?php echo $article['category'] ?><?php endif ?>
            </p>

            <div class="blog-body" style="line-height:1.9;">
                <?php echo $_html // commonmark 出力（運営者が執筆する信頼ソース・生出力） ?>
            </div>

            <aside style="margin:2.5rem 0 1rem; padding:1.25rem; border:1px solid #e8e8e8; border-radius:12px; background:#fafafa;">
                <div style="font-weight:bold; margin-bottom:.6rem;">📈 オプチャグラフで見る</div>
                <div style="display:flex; flex-wrap:wrap; gap:8px;">
                    <a href="<?php echo url('ranking') ?>" style="display:inline-flex; align-items:center; height:34px; padding:0 14px; border:1px solid #06c755; border-radius:36px; color:#06c755; font-weight:bold; font-size:13px; text-decoration:none;">人気ランキング</a>
                    <a href="<?php echo url('') ?>" style="display:inline-flex; align-items:center; height:34px; padding:0 14px; border:1px solid #e8e8e8; border-radius:36px; color:#111; font-weight:bold; font-size:13px; text-decoration:none;">急上昇テーマ</a>
                    <a href="<?php echo url('labs/publication-analytics') ?>" style="display:inline-flex; align-items:center; height:34px; padding:0 14px; border:1px solid #e8e8e8; border-radius:36px; color:#111; font-weight:bold; font-size:13px; text-decoration:none;">掲載・圏外の分析</a>
                </div>
            </aside>
        </article>
    </main>
    <?php \App\Views\Ads\GoogleAdsense::loadAdsTag() ?>
    <?php viewComponent('footer_inner') ?>
    <?php echo $_breadcrumbsShema ?>
</body>

</html>
