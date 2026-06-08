<!DOCTYPE html>
<html lang="<?php echo t('ja') ?>">

<head prefix="og: http://ogp.me/ns#">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <?php echo $_meta ?>
    <link rel="icon" type="image/png" href="<?php echo fileUrl(\App\Config\AppConfig::SITE_ICON_FILE_PATH, urlRoot: '') ?>">

    <!-- PWA: マニフェスト＋テーマ色＋iOS用アイコン（ホーム画面追加に対応） -->
    <meta name="theme-color" content="#10b981">
    <link rel="manifest" href="<?php echo fileUrl('js/alpha/manifest.webmanifest', urlRoot: '') ?>">
    <link rel="apple-touch-icon" href="<?php echo fileUrl('js/alpha/icons/icon-192x192.png', urlRoot: '') ?>">

    <!-- ハッシュ付きバンドルを glob で解決（getFilePath は js/all-room-stats 等と同じ先例）。
         URL にハッシュが入るため内容が変わるたび別URLになり、1リロードで新版を取得できる。 -->
    <link rel="stylesheet" href="/<?php echo getFilePath('js/alpha', 'index-*.css') ?>">
    <script defer="defer" src="/<?php echo getFilePath('js/alpha', 'index-*.js') ?>"></script>

    <!-- PWA: Service Worker を /alpha スコープで登録（sw.js は Service-Worker-Allowed: / 配信） -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register('/js/alpha/sw.js', { scope: '/alpha' })
                    .catch(function (e) { console.error('SW registration failed', e) })
            })
        }
    </script>
</head>

<body>
    <noscript>You need to enable JavaScript to run this app.</noscript>

    <!-- React マウントポイント -->
    <div id="alpha-root">
    </div>
</body>

</html>