<!DOCTYPE html>
<html lang="jp">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo fileUrl("style/mvp.css", urlRoot: '') ?>">
    <link rel="stylesheet" href="<?php echo fileUrl("style/site_header.css", urlRoot: '') ?>">
    <link rel="stylesheet" href="<?php echo fileUrl("style/site_footer.css", urlRoot: '') ?>">
    <title><?php echo $title ?? '確認' ?></title>
</head>

<body>
    <?php viewComponent('site_header') ?>
    <main>
        <h2><?php echo $title ?></h2>
        <p><?php echo nl2br($description ?? '') ?></p>
        <form method="POST" action="<?php echo $action ?>">
            <input type="hidden" name="confirmed" value="1">
            <?php foreach ($params as $key => $value): ?>
                <input type="hidden" name="<?php echo htmlspecialchars($key) ?>" value="<?php echo htmlspecialchars((string)$value) ?>">
            <?php endforeach ?>
            <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                <button type="submit" style="background-color: #d32f2f; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 4px; cursor: pointer; font-size: 1rem;">実行する</button>
                <a href="<?php echo $cancelUrl ?? '/' ?>" style="display: inline-flex; align-items: center; padding: 0.75rem 1.5rem; border-radius: 4px; text-decoration: none; background: #e0e0e0; color: #333; font-size: 1rem;">キャンセル</a>
            </div>
        </form>
    </main>
    <footer>
        <?php viewComponent('footer_inner') ?>
    </footer>
    <script defer src="<?php echo fileUrl("/js/site_header_footer.js", urlRoot: '') ?>"></script>
</body>

</html>
