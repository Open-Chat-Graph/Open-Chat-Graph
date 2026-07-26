<?php

/**
 * 広告オプトアウト設定ページ（/admin/disable-ads）
 *
 * @var bool $optedOut このブラウザが現在オプトアウト状態か
 * @var ?string $message 成功メッセージ
 * @var ?string $error エラーメッセージ
 */
?>
<!DOCTYPE html>
<html lang="ja" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <link rel="stylesheet" href="<?php echo fileUrl('style/tokens.css', urlRoot: '') ?>">
    <link rel="stylesheet" href="<?php echo fileUrl('style/base/mvp.css', urlRoot: '') ?>">
    <link rel="stylesheet" href="<?php echo fileUrl('style/base/unset.css', urlRoot: '') ?>">
    <title>広告の非表示設定</title>
    <style>
        .ad-opt-out {
            max-width: 480px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .ad-opt-out .state {
            border-radius: 12px;
            padding: 14px 16px;
            margin: 1rem 0;
            font-size: 15px;
            line-height: 1.7;
        }

        .ad-opt-out .state.on {
            background: #eefaf1;
            border: 1px solid #bfe6cb;
            color: #1c6b38;
        }

        .ad-opt-out .state.off {
            background: #f5f6f8;
            border: 1px solid #e2e5ea;
            color: #4a4d57;
        }

        .ad-opt-out .notice {
            border-radius: 12px;
            padding: 12px 16px;
            margin: 1rem 0;
            font-size: 14.5px;
            line-height: 1.7;
        }

        .ad-opt-out .notice.ok {
            background: #eef4ff;
            border: 1px solid #c7d9f7;
            color: #1f4b93;
        }

        .ad-opt-out .notice.ng {
            background: #fdf0ef;
            border: 1px solid #f3c9c5;
            color: #a02c22;
        }

        .ad-opt-out input[type="password"] {
            width: 100%;
            box-sizing: border-box;
            padding: 12px 14px;
            font-size: 16px;
            border: 1px solid #d6d9df;
            border-radius: 10px;
            margin: 6px 0 14px;
        }

        .ad-opt-out button {
            width: 100%;
            box-sizing: border-box;
            border: 0;
            border-radius: 10px;
            padding: 13px 18px;
            font-size: 15px;
            font-weight: 600;
            font-family: inherit;
            color: #fff;
            cursor: pointer;
            background: linear-gradient(135deg, #ffa751, #e85d04);
        }

        .ad-opt-out button.secondary {
            background: #f5f6f8;
            color: #4a4d57;
            border: 1px solid #d6d9df;
        }

        .ad-opt-out .hint {
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid #eef0f3;
            font-size: 12.5px;
            line-height: 1.8;
            color: #9499a3;
        }
    </style>
</head>

<body>
    <main class="ad-opt-out">
        <h1 style="font-size: 20px;">広告の非表示設定</h1>

        <?php if ($message): ?>
            <div class="notice ok"><?php echo h($message) ?></div>
        <?php endif ?>
        <?php if ($error): ?>
            <div class="notice ng"><?php echo h($error) ?></div>
        <?php endif ?>

        <?php if ($optedOut): ?>
            <div class="state on">このブラウザでは広告が表示されません。</div>
            <form method="post" action="">
                <input type="hidden" name="action" value="clear">
                <button type="submit" class="secondary">広告を再表示する</button>
            </form>
        <?php else: ?>
            <div class="state off">このブラウザでは広告が表示されます。</div>
            <form method="post" action="">
                <label for="passphrase">合言葉</label>
                <input type="password" id="passphrase" name="passphrase" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" required>
                <button type="submit">広告を非表示にする</button>
            </form>
        <?php endif ?>

        <div class="hint">
            設定はこのブラウザのクッキーに保存されます。ブラウザやシークレットウィンドウを変えると、
            もう一度合言葉の入力が必要です。クッキーを消すと設定も消えます。
        </div>
    </main>
</body>

</html>
