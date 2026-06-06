<!-- @param string $_meta -->
<!-- @param array $_css -->
<!-- @param int $id -->

<head prefix="og: http://ogp.me/ns#">
    <?php echo gTag(\App\Config\AppConfig::$gtmId) ?>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <?php /* OSステータスバー/ツールバーの色。初期HTMLに必須（JS生成だけだとiOSが拾わないことがある）。
             実際の値は theme.js が解決テーマに合わせて即時更新する */ ?>
    <meta name="theme-color" content="#ffffff">
    <?php echo $_meta ?>
    <?php /* テーマ確定はCSSより先（FOUC防止のため同期読み込み） */ ?>
    <script src="<?php echo fileUrl('/js/theme.js', urlRoot: '') ?>"></script>
    <link rel="stylesheet" href="<?php echo fileUrl('style/tokens.css', urlRoot: '') ?>">
    <link rel="stylesheet" href="<?php echo fileUrl('style/base/mvpmin.css', urlRoot: '') ?>">
    <link rel="stylesheet" href="<?php echo fileUrl('style/base/unset.css', urlRoot: '') ?>">
    <?php foreach ($_css as $css) : ?>
        <link rel="stylesheet" href="<?php echo fileUrl("style/{$css}.css", urlRoot: '') ?>">
    <?php endforeach ?>
    <link rel="icon" type="image/png" href="<?php echo fileUrl(\App\Config\AppConfig::SITE_ICON_FILE_PATH, urlRoot: '') ?>">
    <link rel="canonical" href="<?php echo url(strstr(path(), '?', true) ?: path()) ?>">
    <?php if (isset($_schema)) : ?>
        <?php echo $_schema ?>
    <?php endif ?>
    <?php //\App\Views\Ads\GoogleAdsense::gTag($dataOverlays ?? null) ?>
</head>