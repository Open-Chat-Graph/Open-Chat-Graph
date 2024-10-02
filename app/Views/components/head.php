<!-- @param string $_meta -->
<!-- @param array $_css -->

<head prefix="og: http://ogp.me/ns#">
    <?php echo gTag(\App\Config\AppConfig::GTM_ID) ?>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php echo $_meta ?>
    <link rel="stylesheet" href="<?php echo fileUrl('style/mvp.css') ?>">
    <?php foreach ($_css as $css) : ?>
        <link rel="stylesheet" href="<?php echo fileUrl("style/{$css}.css") ?>">
    <?php endforeach ?>
    <link rel="icon" type="image/png" href="<?php echo url(\App\Config\AppConfig::SITE_ICON_FILE_PATH) ?>">
    <?php if (isset($canonical)) : ?>
        <link rel="canonical" hrefs="<?php echo $canonical ?>">
    <?php else : ?>
        <link rel="canonical" hrefs="<?php echo url(strstr(path(), '?', true) ?: path()) ?>">
    <?php endif ?>
    <?php if (isset($_schema)) : ?>
        <?php echo $_schema ?>
    <?php endif ?>
    <?php if (isset($noindex)) : ?>
        <meta name="robots" content="noindex, nofollow">
    <?php endif ?>
    <?php if (url() !== 'http://localhost:8000') : ?>
        <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-2330982526015125" crossorigin="anonymous"></script>
    <?php endif ?>
</head>