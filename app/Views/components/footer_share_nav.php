<nav class="share-nav unset">
    <h3><span aria-hidden="true">\&nbsp;</span><?php echo t('このページをシェアする') ?><span aria-hidden="true">&nbsp;/</span></h3>
    <div class="share-nav-inner">
        <div class="share-menu-outer">
            <?php $url = urlencode(url(path())) ?>
            <a class="share-menu-item unset" href="https://twitter.com/intent/tweet?url=<?php echo $url ?>&text=<?php echo urlencode($title) ?>" rel="nofollow noopener" target="_blank" title="<?php echo t('ポストする') ?>">
                <span class="share-menu-icon-twitter share-menu-icon"></span>
            </a>
            <?php if (\Shared\MimimalCmsConfig::$urlRoot === 'oc') : ?>
                <a class="share-menu-item unset" href="https://b.hatena.ne.jp/entry/s/<?php echo getHostAndUri() ?>" rel="nofollow noopener" target="_blank" title="はてなブックマークでブックマーク">
                    <span class="share-menu-icon-hatena share-menu-icon"></span>
                </a>
            <?php endif ?>
            <a class="share-menu-item unset" href="https://social-plugins.line.me/lineit/share?url=<?php echo $url ?>" rel="nofollow noopener" target="_blank" title="<?php echo t('LINEでシェア') ?>">
                <span class="share-menu-icon-line share-menu-icon"></span>
            </a>
            <a class="share-menu-item unset" href="https://www.facebook.com/share.php?u=<?php echo $url ?>" rel="nofollow noopener" target="_blank" title="<?php echo t('Facebookでシェア') ?>">
                <span class="share-menu-icon-facebook share-menu-icon"></span>
            </a>
            <div class="copy-btn-outer" id="copy-btn-outer">
                <button class="share-menu-item unset" id="copy-btn" title="<?php echo t('このページのタイトルとURLをコピーする') ?>">
                    <span class="copy-btn-icon link-icon"></span>
                </button>
                <div class="description1" id="copy-description">
                    <div class="copy-btn-inner">
                        <span class="copy-btn-icon copy-icon"></span>
                        <span><?php echo t('コピーしました') ?></span>
                    </div>
                    <hr style="margin: 0.25rem 0 0.45rem 0;">
                    <div class="copy-btn-text" id="copy-btn-title"></div>
                    <div class="copy-btn-text" id="copy-btn-url"></div>
                </div>
            </div>
        </div>
    </div>
</nav>