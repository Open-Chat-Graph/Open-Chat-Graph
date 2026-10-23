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
        <p>このブラウザを管理者として扱うためのクッキーを発行します。この環境の管理 API キー（<code>adminApiKey</code>）を入力してください。</p>
        <p>キーをブラウザのパスワード管理に保存しておくと、スマホなど同じアカウントの別の端末でも自動入力で発行できます。</p>
        <?php if ($error): ?>
            <p style="color: var(--c-accent-red);"><?php echo $error ?></p>
        <?php endif ?>
        <form method="POST" action="<?php echo url('admin/cookie') ?>">
            <?php // パスワード管理（Chrome・iCloud キーチェーン等）に保存・自動入力させるため、ユーザー名欄を隠して置き current-password にする ?>
            <input type="text" name="username" value="admin" autocomplete="username" hidden>
            <label for="admin-key">管理 API キー</label>
            <input type="password" id="admin-key" name="key" autocomplete="current-password" required style="width: 100%; max-width: 32rem;">
            <div style="margin-top: 1rem;">
                <button type="submit">発行する</button>
            </div>
        </form>
    </main>
</body>

</html>
