<?php
$enableAdsense = \Shared\MimimalCmsConfig::$urlRoot === ''; // 日本語版のみ広告表示 
?>
<!DOCTYPE html>
<html lang="<?php echo t('ja') ?>">

<head prefix="og: http://ogp.me/ns#">
    <?php echo gTag(\App\Config\AppConfig::$gtmId) ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <?php echo $_meta ?>
    <link rel="icon" type="image/png" href="<?php echo fileUrl(\App\Config\AppConfig::SITE_ICON_FILE_PATH, urlRoot: '') ?>">
    <?php foreach ($_css as $css) : ?>
        <link rel="stylesheet" href="<?php echo fileUrl($css, urlRoot: '') ?>">
    <?php endforeach ?>
    <script defer="defer" src="<?php echo fileUrl($_js, urlRoot: '') ?>"></script>
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