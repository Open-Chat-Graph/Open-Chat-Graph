<?php if (isset($adSlot) && $adSlot) \App\Views\Ads\GoogleAdsense::output($adSlot) ?>
<?php // 罫線(hr)レス: 区切りは余白のみ。値は tokens.css の --sp-* で一元管理 ?>
<footer class="footer-elem-outer" style="padding: var(--sp-section-gap) 0 0 0;">
    <nav class="footer-link-box-outer">
        <?php // フッターの定型文をGoogle検索スニペットに出さない。data-nosnippetはnavに付けられない(div/section/spanのみ)ためdivで包む ?>
        <div data-nosnippet>
        <section class="unset footer-link-box" style="padding: 0 1rem;">
            <ul class="footer-link-inner">
                <li><a class="unset" href="<?php echo url('') ?>"><?php echo t('トップ') ?></a></li>
                <li><a class="unset" href="<?php echo url('policy/privacy') ?>"><?php echo t('プライバシーポリシー') ?></a></li>
                    <?php if (\Shared\MimimalCmsConfig::$urlRoot === ''): ?>
                <li><a class="unset" href="<?php echo url('policy/term') ?>">利用規約</a></li>
                <?php endif ?>
            </ul>
            <ul class="footer-link-inner">
                <li><a class="unset" href="<?php echo url('policy') ?>"><?php echo t('オプチャグラフとは？') ?></a></li>
                <?php if (\Shared\MimimalCmsConfig::$urlRoot === ''): ?>
                    <li><a class="unset" href="<?php echo url('labs') ?>">分析Labs</a></li>
                    <li><a class="unset" href="<?php echo url('blog') ?>">ブログ</a></li>
                <?php endif ?>
            </ul>
        </section>
        <aside class="open-btn2" style="padding: 0 1rem; margin-top: var(--sp-section-gap-sm);">
            <a href="<?php echo t('https://openchat.line.me/jp') ?>" class="app_link app-dl" target="_blank">
                <span class="text"><?php echo t('【公式】LINEオープンチャット') ?><span class="line-link-icon777"></span></span>
            </a>
            <a href="<?php echo t('https://openchat-jp.line.me/other/beginners_guide') ?>" class="app_link app-dl" target="_blank">
                <span class="text"><?php echo t('はじめてのLINEオープンチャットガイド（LINE公式）') ?><span class="line-link-icon777"></span></span>
            </a>
            <a href="https://line.me/D" class="app_link app-dl" target="_blank">
                <span class="text"><?php echo t('LINEアプリをダウンロード（LINE公式）') ?><span class="line-link-icon777"></span></span>
            </a>
        </aside>
        <?php // テーマ切替（3状態）。アイコン出し分けは site_header.css の [data-theme-pref] 規則を共用 ?>
            <div class="footer-theme-toggle-row">
                <button class="footer-theme-toggle theme-toggle-btn unset" type="button" aria-label="<?php echo t('ダークモード切替') ?>">
                    <svg class="theme-icon-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                    <svg class="theme-icon-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                    <svg class="theme-icon-auto" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 3a9 9 0 0 0 0 18z" fill="currentColor" stroke="none"/></svg>
                    <span class="footer-theme-toggle-label"><?php echo t('テーマ') ?>: <span class="theme-label-light"><?php echo t('ライト') ?></span><span class="theme-label-dark"><?php echo t('ダーク') ?></span><span class="theme-label-auto"><?php echo t('自動') ?></span></span>
                </button>
            </div>
            <?php // 言語切り替え（トップのヒーローから移設）。各言語のブランド名をアンカーにする ?>
            <div class="footer-lang-row" aria-label="Language">
                <span class="footer-lang-globe" aria-hidden="true">🌐</span>
                <?php foreach (array_keys(\App\Config\AppConfig::LINE_OPEN_URL) as $lang): ?>
                    <?php if ($lang === \Shared\MimimalCmsConfig::$urlRoot): ?>
                        <span class="footer-lang-current"><?php echo t('オプチャグラフ', $lang) ?></span>
                    <?php else: ?>
                        <a class="unset" href="<?php echo url(["urlRoot" => "", "paths" => [$lang]]) ?>"><?php echo t('オプチャグラフ', $lang) ?></a>
                    <?php endif ?>
                <?php endforeach ?>
            </div>
            <div class="copyright">© <?php echo t('オプチャグラフ') ?><span><a class="unset" style="cursor: pointer;" href="https://github.com/Open-Chat-Graph" target="_blank">Project on GitHub @Open-Chat-Graph</a><span class="line-link-icon777"></span></span></div>
        </div>
    </nav>
</footer>