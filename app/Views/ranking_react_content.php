<?php
$enableAdsense = true;
?>
<!DOCTYPE html>
<html lang="<?php echo t('ja') ?>">

<head prefix="og: http://ogp.me/ns#">
    <?php echo gTag(\App\Config\AppConfig::$gtmId) ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1, viewport-fit=cover" />
    <?php /* OSステータスバー/ツールバーの色。初期HTMLに必須（JS生成だけだとiOSが拾わないことがある）。
             実際の値は theme.js が解決テーマに合わせて即時更新する */ ?>
    <meta name="theme-color" content="#ffffff">
    <?php /* 「ホーム画面に追加」(PWA)時のみ有効: ページがステータスバー/ダイナミックアイランドの
             下まで広がり、blur付きヘッダーとコンテンツが周囲に透ける（通常のSafari縦持ちでは
             この帯はSafari描画のため不可）。black-translucent はステータス文字が常に白の
             ためライトのPWA起動では視認性が落ちるトレードオフあり */ ?>
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <?php echo $_meta ?>
    <link rel="icon" type="image/png" href="<?php echo fileUrl(\App\Config\AppConfig::SITE_ICON_FILE_PATH, urlRoot: '') ?>">
    <?php /* テーマ確定はCSSより先（FOUC防止のため同期読み込み） */ ?>
    <script src="<?php echo fileUrl('/js/theme.js', urlRoot: '') ?>"></script>
    <link rel="stylesheet" href="<?php echo fileUrl('style/tokens.css', urlRoot: '') ?>">
    <?php foreach ($_css as $css) : ?>
        <link rel="stylesheet" href="<?php echo fileUrl($css, urlRoot: '') ?>">
    <?php endforeach ?>
    <script type="module" crossorigin src="<?php echo fileUrl($_js, urlRoot: '') ?>"></script>
    <link rel="canonical" href="<?php echo url('ranking') . ($category ? '/' . $category : '') ?>">
    <?php if ($enableAdsense): ?>
        <?php \App\Views\Ads\GoogleAdsense::gTag() ?>
    <?php endif ?>

</head>

<body style="margin: 0;">
    <!-- <style>
        .grippy-host {
            display: none;
        }

        .right-side-rail-dismiss-btn {
            display: none;
        }

        .left-side-rail-dismiss-btn {
            display: none;
        }
    </style> -->
    <script type="application/json" id="arg-dto">
        <?php echo json_encode($_argDto, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>
    </script>
    <noscript>You need to enable JavaScript to run this app.</noscript>
    <div id="root"></div>
    <?php echo $_breadcrumbsShema ?>
    <?php if ($enableAdsense): ?>
        <script defer src="<?php echo fileurl("/js/security.js", urlRoot: '') ?>"></script>
    <?php endif ?>
</body>

</html>