<!DOCTYPE html>
<html lang="ja">

<head prefix="og: http://ogp.me/ns#">
    <?php echo gTag(\App\Config\AppConfig::GTM_ID) ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <?php echo $_meta ?>
    <link rel="icon" type="image/png" href="<?php echo url(\App\Config\AppConfig::SITE_ICON_FILE_PATH) ?>">
    <?php foreach ($_css as $css) : ?>
        <link rel="stylesheet" href="<?php echo fileUrl($css) ?>">
    <?php endforeach ?>
    <script defer="defer" src="<?php echo fileUrl($_js) ?>"></script>
    <link rel="canonical" hrefs="<?php echo url('ranking') . ($category ? '/' . $category : '') ?>">
    <?php if (url() !== 'http://localhost') : ?>
        <script async data-overlays="bottom" src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-2330982526015125" crossorigin="anonymous"></script>
    <?php endif ?>
</head>

<body style="margin: 0;">
    <script type="application/json" id="arg-dto">
        <?php echo json_encode($_argDto, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>
    </script>
    <noscript>You need to enable JavaScript to run this app.</noscript>
    <div id="root"></div>
    <?php echo $_breadcrumbsShema ?>
</body>

</html>