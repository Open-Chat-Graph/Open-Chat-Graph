<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-NTK2GPTF" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->
<header class="site_header_outer" id="site_header">
    <div class="site_header">
        <a class="<?php echo t('header_site_title') ?> unset" href="<?php echo url() ?>">
            <img src="<?php echo fileUrl(\App\Config\AppConfig::SITE_ICON_FILE_PATH, urlRoot: '') ?>" alt="">
            <?php if (strpos(path(), '/oc') === false || isset($titleP)) : ?>
                <h1><?php echo t('サイトタイトル'); if (\App\Config\AppConfig::$isStaging) echo '<span style="font-size: 0.7em; color: var(--c-text-mid-3);">' . t(' (開発環境)') . '</span>'; ?></h1>
            <?php else : ?>
                <p><?php echo t('サイトタイトル'); if (\App\Config\AppConfig::$isStaging) echo '<span style="font-size: 0.7em; color: var(--c-text-mid-3);">' . t(' (開発環境)') . '</span>'; ?></p>
            <?php endif ?>
        </a>
        <?php // 右側アクション群（ブログ=日本語のみ／カテゴリ）。検索ボタン(右端absolute)の50px分を確保、トップでは詰める ?>
        <div class="header-actions"<?php if ($hideSearchButton ?? false) echo ' style="margin-right: 0;"' ?>>
            <?php if (\Shared\MimimalCmsConfig::$urlRoot === '') : ?>
                <a class="blog-button unset" href="<?php echo url('blog') ?>"><span>ブログ</span></a>
            <?php endif ?>
            <a class="category-button" href="<?php echo url('ranking') ?>">
                <span><?php echo t('カテゴリーから探す') ?></span>
            </a>
        </div>
        <nav class="header-nav unset" style="height: 48px;<?php if ($hideSearchButton ?? false) echo ' display: none;' ?>">
            <?php // ダークモード切替（実装は public/js/theme.js。アイコンの出し分けは site_header.css） ?>
            <button class="header-button unset theme-toggle-btn" type="button" aria-label="<?php echo t('ダークモード切替') ?>">
                <svg class="theme-icon-moon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                <svg class="theme-icon-sun" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
            </button>
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