<?php if (isset($adSlot) && $adSlot) \App\Views\Ads\GoogleAdsense::output(\App\Views\Ads\GoogleAdsense::AD_SLOTS[$adSlot]) ?>
<footer class="footer-elem-outer" style="padding: 0;">
    <hr class="hr-top" style="margin-bottom: 11px;">
    <nav class="footer-link-box-outer">
        <section class="unset footer-link-box" style="padding: 0 1rem;">
            <ul class="footer-link-inner">
                <li><a class="unset" href="<?php echo url('') ?>"><?php echo t('トップ') ?></a></il>
                <li><a class="unset" href="<?php echo url('policy') ?>"><?php echo t('オプチャグラフとは？') ?></a></il>
                <li><a class="unset" href="<?php echo url('policy/privacy') ?>"><?php echo t('プライバシーポリシー') ?></a></il>
                    <? if (\Shared\MimimalCmsConfig::$urlRoot === ''): ?>
                <li><a class="unset" href="<?php echo url('policy/term') ?>">利用規約</a></il>
                <? endif ?>
            </ul>
            <ul class="footer-link-inner">
                <? if (\Shared\MimimalCmsConfig::$urlRoot === ''): ?>
                    <li><a class="unset" href="<?php echo url('labs/live') ?>">ライブトーク分析ツール</a></il>
                    <li><a class="unset" href="<?php echo url('labs/publication-analytics') ?>">公式ランキング掲載の分析</a></il>
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
        <div class="copyright">© <?php echo t('オプチャグラフ') ?><span><a class="unset" style="cursor: pointer;" href="https://github.com/pika-0203" target="_blank">Project on GitHub @pika-0203</a><span class="line-link-icon777"></span></span></div>
    </nav>
</footer>