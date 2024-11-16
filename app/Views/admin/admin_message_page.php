<!DOCTYPE html>
<html lang="jp">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo fileUrl("style/mvp.css", urlRoot: '') ?>">
    <link rel="stylesheet" href="<?php echo fileUrl("style/site_header.css", urlRoot: '') ?>">
    <link rel="stylesheet" href="<?php echo fileUrl("style/site_footer.css", urlRoot: '') ?>">
    <title><?php echo $title ?? 'admin' ?></title>
</head>

<body>
    <?php viewComponent('site_header') ?>
    <main>
        <p><?php echo nl2br($message) ?? '' ?></p>
    </main>
    <footer>
        <?php viewComponent('footer_inner') ?>
    </footer>
    <script defer src="<?php echo fileUrl("/js/site_header_footer.js", urlRoot: '') ?>"></script>
</body>

</html>