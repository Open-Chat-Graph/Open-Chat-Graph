<!DOCTYPE html>
<html lang="jp">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo fileUrl("style/mvp.css", urlRoot: '') ?>">
    <link rel="stylesheet" href="<?php echo fileUrl("style/site_header.css", urlRoot: '') ?>">
    <link rel="stylesheet" href="<?php echo fileUrl("style/site_footer.css", urlRoot: '') ?>">
    <link rel="stylesheet" href="<?php echo fileUrl("style/room_list.css", urlRoot: '') ?>">
    <title><?php echo $title ?? 'admin' ?></title>
</head>

<body style="padding: 0;">
    <main style="max-width: 812px;">
        <?php viewComponent('site_header') ?>
        <div style="margin-top: 5rem;">
            <p>ユーザー数: <?php echo count($result) ?></p>
            <?php foreach ($result as $el) : ?>
                <div style="margin-bottom: 2rem;">
                    <div style="font-size: 13px;">
                        <div>最終アクセス: <b><?php echo timeElapsedString($el['time']) ?></b> <?php echo $el['time'] ?></div>
                        <div>アクセス数: <?php echo $el['count'] ?></div>
                        <div>リスト数: <?php echo count(json_decode($el['oc_list'], true)) ?></div>
                        <div>ua: <?php echo $el['ua'] ?></div>
                        <div>user_id: <?php echo base62Hash($el['user_id'], 'fnv132') ?></div>
                    </div>
                    <?php viewComponent('open_chat_list', ['openChatList' => $el['oc']]) ?>
                </div>
            <?php endforeach ?>
        </div>
    </main>
    <footer>
        <?php viewComponent('footer_inner') ?>
    </footer>
    <script defer src="<?php echo fileUrl("/js/site_header_footer.js", urlRoot: '') ?>"></script>
</body>

</html>