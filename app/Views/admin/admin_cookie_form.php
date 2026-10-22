<!DOCTYPE html>
<html lang="ja" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <link rel="stylesheet" href="<?php echo fileUrl('style/tokens.css', urlRoot: '') ?>">
    <link rel="stylesheet" href="<?php echo fileUrl("style/base/mvp.css", urlRoot: '') ?>">
    <title>管理クッキーの発行</title>
</head>

<body>
    <main>
        <h2>管理クッキーの発行</h2>
        <form method="POST" action="<?php echo url('admin/cookie') ?>">
            <label for="admin-key">管理 API キー</label>
            <input type="password" id="admin-key" name="key" autocomplete="off" required style="width: 100%; max-width: 32rem;">
            <div style="margin-top: 1rem;">
                <button type="submit">発行する</button>
            </div>
        </form>
    </main>
</body>

</html>
