<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-NTK2GPTF" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->
<header class="site_header_outer" id="site_header">
    <div class="site_header">
        <a class="<?php echo t('header_site_title') ?> unset" href="<?php echo url() ?>">
            <img src="<?php echo fileUrl(\App\Config\AppConfig::SITE_ICON_FILE_PATH, urlRoot: '') ?>" alt="">
            <?php if (strpos(path(), '/oc') === false || isset($titleP)) : ?>
                <h1><?php echo t('サイトタイトル'); if (\App\Config\AppConfig::$isStaging) echo '<span style="font-size: 0.7em; color: #888;">' . t(' (開発環境)') . '</span>'; ?></h1>
            <?php else : ?>
                <p><?php echo t('サイトタイトル'); if (\App\Config\AppConfig::$isStaging) echo '<span style="font-size: 0.7em; color: #888;">' . t(' (開発環境)') . '</span>'; ?></p>
            <?php endif ?>
        </a>
        <a class="category-button" href="<?php echo url('ranking') ?>">
            <span><?php echo t('カテゴリーから探す') ?></span>
        </a>
        <nav class="header-nav unset" style="height: 48px;<?php if ($hideSearchButton ?? false) echo ' display: none;' ?>">
            <button class="header-button unset" id="search_button" aria-label="><?php echo t('検索') ?>">
                <span class="search-button-icon"></span>
            </button>
        </nav>
    </div>
    <div class="backdrop" id="backdrop" role="button" aria-label="<?php echo t('閉じる') ?>"></div>
    <div class="search-form site_header">
        <form class="search-form-inner" method="GET" action="<?php echo url('ranking') ?>">
            <label for="q">
            </label>
            <input type="text" id="q" name="keyword" placeholder="<?php echo t('オープンチャットを検索') ?>" maxlength="1000" autocomplete="off" required>
            <input type="hidden" name="list" value="all">
            <input type="hidden" name="sort" value="member">
            <input type="hidden" name="order" value="desc">
        </form>
    </div>
</header>