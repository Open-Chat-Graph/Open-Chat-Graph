<!-- @param string $_meta -->
<!-- @param array $_css -->
<!-- @param int $id -->

<head prefix="og: http://ogp.me/ns#">
    <?php echo gTag(\App\Config\AppConfig::GTM_ID) ?>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php echo $_meta ?>
    <link rel="stylesheet" href="<?php echo fileUrl('style/mvpmin.css', urlRoot: '') ?>">
    <?php foreach ($_css as $css) : ?>
        <link rel="stylesheet" href="<?php echo fileUrl("style/{$css}.css", urlRoot: '') ?>">
    <?php endforeach ?>
    <link rel="icon" type="image/png" href="<?php echo fileUrl(\App\Config\AppConfig::SITE_ICON_FILE_PATH, urlRoot: '') ?>">
    <script type="application/json" id="chart-arg">
        <?php echo json_encode($_chartArgDto, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>
    </script>
    <script type="application/json" id="stats-dto">
        <?php echo json_encode($_statsDto, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>
    </script>
    <script type="application/json" id="comment-app-init-dto">
        <?php echo json_encode($_commentArgDto, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>
    </script>
    <link rel="canonical" href="<?php echo url(strstr(path(), '?', true) ?: path()) ?>">
    <?php if (isset($_schema)) : ?>
        <?php echo $_schema ?>
    <?php endif ?>
    <?php \App\Views\Ads\GoogleAdsence::gTag($dataOverlays ?? null) ?>
</head>