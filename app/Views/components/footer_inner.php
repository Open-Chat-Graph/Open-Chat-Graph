<nav class="footer-link-box-outer">
    <section class="unset footer-link-box">
        <ul class="footer-link-inner">
            <li><a class="unset" href="<?php echo url('') ?>"><?php echo t('トップ') ?></a></il>
            <li><a class="unset" href="<?php echo url('policy') ?>"><?php echo t('オプチャグラフについて') ?></a></il>
                <? if (\Shared\MimimalCmsConfig::$urlRoot === ''): ?>
            <li><a class="unset" style="margin-bottom: 0;" href="https://x.com/openchat_graph" target="_blank">オプチャグラフ公式X<span class="line-link-icon777"></span></a></li>
        <? endif ?>
        </ul>
        <ul class="footer-link-inner">
            <li><a class="unset" href="<?php echo url('policy/privacy') ?>"><?php echo t('プライバシーポリシー') ?></a></il>
                <? if (\Shared\MimimalCmsConfig::$urlRoot === ''): ?>
            <li><a class="unset" href="<?php echo url('policy/term') ?>">利用規約</a></il>
            <? endif ?>
        </ul>
    </section>
    <aside class="open-btn2">
        <a href="<?php echo t('https://openchat.line.me/jp') ?>" class="app_link app-dl" target="_blank">
            <span class="text"><?php echo t('【公式】LINEオープンチャット') ?></span>
        </a>
        <a href="<?php echo t('https://openchat-jp.line.me/other/beginners_guide') ?>" class="app_link app-dl" target="_blank">
            <span class="text"><?php echo t('はじめてのLINEオープンチャットガイド（LINE公式）') ?></span>
        </a>
        <a href="https://line.me/download" class="app_link app-dl" target="_blank">
            <span class="text"><?php echo t('LINEアプリをダウンロード（LINE公式）') ?></span>
        </a>
    </aside>
    <div class="copyright">© OpenChat Graph<span><a class="unset" style="text-decoration: underline; cursor: pointer;" href="https://github.com/pika-0203" target="_blank">Project on GitHub @pika-0203</a></span></div>
</nav>