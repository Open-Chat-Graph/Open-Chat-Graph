<!-- @param string $_meta -->
<!-- @param array $_css -->

<head prefix="og: http://ogp.me/ns#">
    <?php echo gTag(\App\Config\AppConfig::GTM_ID) ?>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php echo $_meta ?>
    <link rel="stylesheet" href="<?php echo fileUrl('style/mvpmin.css') ?>">
    <?php foreach ($_css as $css) : ?>
        <link rel="stylesheet" href="<?php echo fileUrl("style/{$css}.css") ?>">
    <?php endforeach ?>
    <link rel="icon" type="image/png" href="<?php echo url(\App\Config\AppConfig::SITE_ICON_FILE_PATH) ?>">
    <script defer src="<?php echo fileurl("/js/site_header_footer.js") ?>"></script>
    <link rel="canonical" href="<?php echo url(path()) ?>">
    <?php if (isset($noindex)) : ?>
        <meta name="robots" content="noindex, nofollow">
    <?php endif ?>
    <?php \App\Views\Ads\GoogleAdsence::gTag() ?>
</head>