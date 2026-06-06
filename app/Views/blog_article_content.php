<!DOCTYPE html>
<html lang="<?php echo t('ja') ?>">
<?php viewComponent('head', compact('_css', '_meta', '_schema')) ?>

<body>
    <?php viewComponent('site_header') ?>
    <main class="no-pad blog-main">
        <!-- $article のテキストは View 層で自動エスケープ済み。$_html/$_faqHtml は commonmark 済みの信頼ソースで生出力 -->
        <article class="blog blog-article">
            <nav class="blog-crumb">
                <a href="<?php echo url('') ?>">トップ</a><span class="sep">›</span><a href="<?php echo url('blog') ?>">ブログ</a>
            </nav>

            <h1 class="blog-title"><?php echo $article->title ?></h1>

            <div class="blog-meta">
                <span class="author">オプチャグラフ編集部</span>
                <span>公開 <?php echo $article->date ?></span>
                <?php // YYYY-MM-DD の文字列比較。> で「公開日より古い更新日」（入力ミス）を表示しない ?>
                <?php if ($article->updated > $article->date): ?><span>更新 <?php echo $article->updated ?></span><?php endif ?>
                <span>約<?php echo $article->readingMinutes ?>分</span>
                <?php if ($article->category): ?><span class="cat"><?php echo $article->category ?></span><?php endif ?>
            </div>

            <div class="blog-body">
                <?php echo $_html ?>
            </div>

            <div class="blog-cta">
                <div class="blog-cta-h">📈 オプチャグラフで<span class="em">実データ</span>を見る</div>
                <div class="blog-cta-row">
                    <a class="blog-btn blog-btn--primary" href="<?php echo url('ranking') ?>">人気ランキング</a>
                    <a class="blog-btn" href="<?php echo url('') ?>">急上昇テーマ</a>
                    <a class="blog-btn" href="<?php echo url('labs/publication-analytics') ?>">掲載・圏外の分析</a>
                </div>
            </div>

            <?php // 広告: ブログ専用ユニットは未作成のため既存レスポンシブスロットを流用（レポートは他ページと合算される）。
                  // SEO 流入の初見読者に Offerwall を出さないよう、blog では Offerwall のみタグ側で抑制する ?>
            <?php \App\Views\Ads\GoogleAdsense::gTag(suppressOfferwall: true) ?>
            <?php \App\Views\Ads\GoogleAdsense::output('siteSeparatorResponsive') ?>

            <?php if (!empty($_faqHtml)): ?>
                <div class="blog-faq"><?php echo $_faqHtml ?></div>
            <?php endif ?>

            <?php if (!empty($related)): ?>
                <nav class="blog-related">
                    <div class="blog-related-h">関連記事</div>
                    <?php foreach ($related as $r): ?>
                        <a href="<?php echo url('blog/' . $r->slug) ?>">
                            <div class="t"><?php echo $r->title ?></div>
                            <?php if ($r->category): ?><div class="c"><?php echo $r->category ?></div><?php endif ?>
                        </a>
                    <?php endforeach ?>
                </nav>
            <?php endif ?>
        </article>
    </main>
    <?php \App\Views\Ads\GoogleAdsense::loadAdsTag() ?>
    <?php viewComponent('footer_inner') ?>
    <?php echo $_breadcrumbsShema ?>
</body>

</html>
