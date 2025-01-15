<!DOCTYPE html>
<html lang="<?php echo t('ja') ?>">
<?php viewComponent('policy_head', compact('_css', '_meta') + ['noindex' => true]) ?>

<body>
    <?php viewComponent('site_header') ?>
    <main>
        <article class="terms">
            <h1 style="letter-spacing: 0px; overflow: hidden;">広告について</h1>
            <p>この広告は行動ターゲティング広告ではないため、クッキーの取得を行いません。</p>
            <p>サイト内のコンテンツに関連する広告を表示しています。</p>
        </article>
    </main>
    <footer class="footer-elem-outer">
        <?php viewComponent('footer_inner') ?>
    </footer>
</body>

</html>