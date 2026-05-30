<!-- @param string $_meta -->
<!-- @param array $_css -->

<head prefix="og: http://ogp.me/ns#">
    <?php echo gTag(\App\Config\AppConfig::$gtmId) ?>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php echo $_meta ?>
    <link rel="stylesheet" href="<?php echo fileUrl('style/mvp.css', urlRoot: '') ?>">
    <?php foreach ($_css as $css) : ?>
        <link rel="stylesheet" href="<?php echo fileUrl("style/{$css}.css", urlRoot: '') ?>">
    <?php endforeach ?>
    <link rel="icon" type="image/png" href="<?php echo fileUrl(\App\Config\AppConfig::SITE_ICON_FILE_PATH, urlRoot: '') ?>">
    <?php if (isset($canonical)) : ?>
        <link rel="canonical" href="<?php echo $canonical ?>">
    <?php else : ?>
        <link rel="canonical" href="<?php echo url(strstr(path(), '?', true) ?: path()) ?>">
    <?php endif ?>
    <?php if (isset($_schema)) : ?>
        <?php echo $_schema ?>
    <?php endif ?>
    <?php if (isset($noindex)) : ?>
        <meta name="robots" content="noindex, nofollow">
    <?php endif ?>
    <?php if (!isset($disableGAd) || !$disableGAd) : ?>
        <?php //\App\Views\Ads\GoogleAdsense::gTag($dataOverlays ?? null) ?>
    <?php endif ?>
</head>