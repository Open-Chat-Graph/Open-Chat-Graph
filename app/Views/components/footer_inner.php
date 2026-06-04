<?php if (isset($adSlot) && $adSlot) \App\Views\Ads\GoogleAdsense::output($adSlot) ?>
<footer class="footer-elem-outer" style="padding: 0;">
    <hr class="hr-top" style="margin-bottom: 11px;">
    <nav class="footer-link-box-outer">
        <?php
        // 人気テーマ（locale別）。全ページ共通フッターから主要 /recommend ページへ恒常的な
        // 内部リンクを張り、(ja)上位テーマの順位押し上げ・(th)ดีล等の未インデックス解消を狙う。
        // slug は全て実機で 200 を確認済み。表示ラベルは extractTag で整形（非jaは原文のまま返る）。
        $_popularThemes = [
            '' => ['下ネタ', 'パチンコ・スロット（パチスロ）', 'なりきり', 'ポケモンカード（ポケカ）', '対荒らし', '恋愛', 'ポーランドボール', 'カラフルピーチ（からぴち）', 'ブロスタ', '雑談'],
            '/tw' => ['代購', '團購', '台股・股市', '育兒・教養交流', '連鎖門市・好康情報'],
            '/th' => ['ดีล', 'มวย', 'เชียงใหม่', 'รถตู้', 'ROV', 'อสังหา'],
        ][\Shared\MimimalCmsConfig::$urlRoot] ?? [];
        ?>
        <?php if ($_popularThemes): ?>
            <section class="footer-popular-themes">
                <div class="footer-theme-label"><?php echo t('人気テーマ') ?></div>
                <ul class="footer-theme-list">
                    <?php foreach ($_popularThemes as $_theme) : ?>
                        <li><a class="footer-theme-chip" href="<?php echo url('recommend/' . urlencode(htmlspecialchars_decode($_theme))) ?>"><?php echo \App\Services\Recommend\TagDefinition\Ja\RecommendUtility::extractTag($_theme) ?></a></li>
                    <?php endforeach ?>
                </ul>
            </section>
        <?php endif ?>
        <section class="unset footer-link-box" style="padding: 0 1rem;">
            <ul class="footer-link-inner">
                <li><a class="unset" href="<?php echo url('') ?>"><?php echo t('トップ') ?></a></il>
                <li><a class="unset" href="<?php echo url('policy/privacy') ?>"><?php echo t('プライバシーポリシー') ?></a></il>
                    <? if (\Shared\MimimalCmsConfig::$urlRoot === ''): ?>
                <li><a class="unset" href="<?php echo url('policy/term') ?>">利用規約</a></il>
                <? endif ?>
            </ul>
            <ul class="footer-link-inner">
                <li><a class="unset" href="<?php echo url('policy') ?>"><?php echo t('オプチャグラフとは？') ?></a></il>
                <? if (\Shared\MimimalCmsConfig::$urlRoot === ''): ?>
                    <li><a class="unset" href="<?php echo url('labs') ?>">分析Labs</a></il>
                <? endif ?>
            </ul>
        </section>
        <hr class="hr-bottom" style="margin: 0 1rem; padding: 3.5px 0; margin-top: 4px;">
        <aside class="open-btn2" style="padding: 0 1rem;">
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
        <div class="copyright">© <?php echo t('オプチャグラフ') ?><span><a class="unset" style="cursor: pointer;" href="https://github.com/Open-Chat-Graph" target="_blank">Project on GitHub @Open-Chat-Graph</a><span class="line-link-icon777"></span></span></div>
    </nav>
</footer>