<?php

/**
 * 通用口（/admin/disable-ads）— 合言葉を入れた人だけ広告が出なくなる隠しページ
 *
 * 3つの状態を1ファイルで出し分ける:
 *   premium … いま合言葉を通した直後（会員証の発行演出 → トップへ自動移動）
 *   member  … すでにオプトアウト済み（状態表示と解除）
 *   gate    … 未オプトアウト（合言葉の入力）
 *
 * CSS はこのファイル内に閉じる。サイト共通のスタイル（mvp.css 等）を読むと表口の見た目に
 * 引きずられるうえ、ごく稀にしか開かれない隠しページのために共通アセットを増やしたくないため。
 *
 * @var bool $optedOut このブラウザが現在オプトアウト状態か
 * @var bool $premium 直前に合言葉を通したか（発行演出を出すか）
 * @var ?string $message 完了メッセージ
 * @var ?string $error エラーメッセージ
 * @var int $redirectSeconds 自動でトップへ移動するまでの秒数
 */

$topUrl = url();
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <?php echo $_meta ?>
    <?php if ($premium): ?>
        <meta http-equiv="refresh" content="<?php echo (int) $redirectSeconds ?>;url=<?php echo h($topUrl) ?>">
    <?php endif ?>
    <style>
        :root {
            --ink: #0b0d12;
            --panel: #131722;
            --edge: #232839;
            --ember: #e85d04;
            --flare: #ffa751;
            --gold: #f6c453;
            --mist: #98a1b2;
            --paper: #f5f7fa;
            --mono: ui-monospace, SFMono-Regular, Menlo, Consolas, "Courier New", monospace;
            --sans: system-ui, -apple-system, "Hiragino Kaku Gothic ProN", "Noto Sans JP", "Yu Gothic", Meiryo, sans-serif;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100svh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 28px 20px 96px;
            background: var(--ink);
            color: var(--paper);
            font-family: var(--sans);
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
        }

        /* ドアの下から漏れる光。この隠しページの世界観の芯なので3状態すべてに敷く */
        .doorlight {
            position: fixed;
            left: 50%;
            bottom: -180px;
            width: min(150vw, 1100px);
            height: 340px;
            transform: translateX(-52%);
            background: radial-gradient(ellipse at 50% 100%, rgba(246, 196, 83, .55) 0%, rgba(232, 93, 4, .34) 34%, rgba(232, 93, 4, 0) 72%);
            filter: blur(26px);
            pointer-events: none;
            z-index: 0;
        }

        main {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 420px;
        }

        .eyebrow {
            margin: 0 0 18px;
            font-family: var(--mono);
            font-size: 11.5px;
            font-weight: 700;
            letter-spacing: .34em;
            text-transform: uppercase;
            color: var(--ember);
        }

        /* ドアに貼られたサインのつもりなので、英語・大文字・字送り広めで組む */
        h1 {
            margin: 0;
            font-size: 34px;
            font-weight: 800;
            letter-spacing: .13em;
            line-height: 1.2;
            text-transform: uppercase;
        }

        .lede {
            margin: 14px 0 0;
            font-size: 14.5px;
            line-height: 1.85;
            color: var(--mist);
        }

        .field { margin-top: 30px; }

        .field label {
            display: block;
            margin-bottom: 9px;
            font-family: var(--mono);
            font-size: 11.5px;
            letter-spacing: .2em;
            color: var(--mist);
        }

        /* 合言葉は日本語なので type="password" は使わない。iOS は安全入力欄(secure text entry)で
           日本語IMEを無効にして英数キーボードに固定するため、日本語の合言葉が打てなくなる。
           伏せ字にもしない（滅多に開かない隠しページで、打った文字が見えないほうが不便）。 */
        input.secret {
            width: 100%;
            padding: 15px 16px;
            font-family: var(--mono);
            font-size: 16px;
            letter-spacing: .18em;
            color: var(--paper);
            background: var(--panel);
            border: 1px solid var(--edge);
            border-radius: 10px;
            outline: none;
            transition: border-color .18s, box-shadow .18s;
        }

        input.secret:focus-visible {
            border-color: var(--ember);
            box-shadow: 0 0 0 3px rgba(232, 93, 4, .22);
        }

        button {
            width: 100%;
            margin-top: 14px;
            padding: 15px 18px;
            font-family: var(--sans);
            font-size: 15px;
            font-weight: 700;
            letter-spacing: .08em;
            color: #1a1103;
            background: linear-gradient(135deg, var(--flare), var(--ember));
            border: 0;
            border-radius: 10px;
            cursor: pointer;
            transition: filter .18s, transform .18s;
        }

        button:hover { filter: brightness(1.08); }
        button:active { transform: translateY(1px); }
        button:focus-visible { outline: 2px solid var(--gold); outline-offset: 3px; }

        button.ghost {
            color: var(--mist);
            background: transparent;
            border: 1px solid var(--edge);
            font-weight: 600;
        }

        button.ghost:hover { color: var(--paper); border-color: var(--mist); filter: none; }

        .note {
            margin-top: 26px;
            padding-top: 18px;
            border-top: 1px solid var(--edge);
            font-family: var(--mono);
            font-size: 11.5px;
            line-height: 1.9;
            color: #6b7387;
        }

        .alert {
            margin-top: 22px;
            padding: 12px 14px;
            border: 1px solid;
            border-radius: 9px;
            font-size: 13.5px;
            line-height: 1.7;
        }

        .alert.ng { color: #ffb4a8; border-color: rgba(255, 122, 99, .38); background: rgba(255, 92, 66, .09); }
        .alert.ok { color: var(--gold); border-color: rgba(246, 196, 83, .34); background: rgba(246, 196, 83, .08); }

        /* --- 会員証 --- */
        .card {
            position: relative;
            overflow: hidden;
            padding: 24px 24px 20px;
            border: 1px solid rgba(246, 196, 83, .34);
            border-radius: 16px;
            background:
                linear-gradient(150deg, rgba(246, 196, 83, .10) 0%, rgba(232, 93, 4, .05) 46%, rgba(19, 23, 34, 0) 78%),
                var(--panel);
            box-shadow: 0 26px 60px -26px rgba(232, 93, 4, .5);
        }

        .card-brand {
            font-family: var(--mono);
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: .26em;
            color: var(--mist);
        }

        .card-tier {
            margin-top: 6px;
            font-size: 27px;
            font-weight: 800;
            letter-spacing: .3em;
            background: linear-gradient(100deg, var(--flare), var(--gold) 46%, #fff5dd 62%, var(--gold) 78%, var(--ember));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .card-foot {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 16px;
            margin-top: 18px;
        }

        .card-status {
            font-size: 13px;
            font-weight: 600;
            color: var(--gold);
        }

        /* 会員証に刻む上昇折れ線＝このサイト自身の道具。演出の主役はここ一点に絞る */
        .card-chart { display: block; flex: none; }

        .card-chart path {
            fill: none;
            stroke: var(--gold);
            stroke-width: 2.5;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .card-chart circle { fill: var(--gold); }

        h1.issued {
            margin-top: 26px;
            font-size: 25px;
            line-height: 1.5;
            letter-spacing: .02em;
        }

        .redirect {
            margin-top: 12px;
            font-size: 13.5px;
            color: var(--mist);
        }

        .redirect b { color: var(--gold); font-variant-numeric: tabular-nums; }

        .golink {
            display: inline-block;
            margin-top: 18px;
            padding: 11px 20px;
            border: 1px solid var(--edge);
            border-radius: 9px;
            color: var(--paper);
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: border-color .18s;
        }

        .golink:hover { border-color: var(--gold); }
        .golink:focus-visible { outline: 2px solid var(--gold); outline-offset: 3px; }

        /* --- 発行のひと続きの演出（読み込み時に1回だけ流れる） --- */
        @keyframes issue-rise { from { opacity: 0; transform: translateY(22px) scale(.97); } to { opacity: 1; transform: none; } }
        @keyframes issue-fade { from { opacity: 0; transform: translateY(9px); } to { opacity: 1; transform: none; } }
        @keyframes issue-shine { from { transform: translateX(-130%) skewX(-18deg); } to { transform: translateX(320%) skewX(-18deg); } }
        @keyframes issue-draw { from { stroke-dashoffset: 210; } to { stroke-dashoffset: 0; } }
        @keyframes issue-pop { from { opacity: 0; transform: scale(.2); } to { opacity: 1; transform: none; } }

        .premium .card { animation: issue-rise .62s cubic-bezier(.2, .75, .25, 1) both; }

        .premium .card::after {
            content: '';
            position: absolute;
            inset: 0 auto 0 0;
            width: 34%;
            background: linear-gradient(90deg, transparent, rgba(255, 246, 224, .3), transparent);
            animation: issue-shine 1.15s cubic-bezier(.4, 0, .3, 1) .5s both;
        }

        .premium .card-chart path { stroke-dasharray: 210; animation: issue-draw .95s ease-out .62s both; }
        .premium .card-chart circle { animation: issue-pop .3s ease-out 1.5s both; }
        .premium .issued { animation: issue-fade .5s ease-out .78s both; }
        .premium .redirect { animation: issue-fade .5s ease-out .94s both; }
        .premium .golink { animation: issue-fade .5s ease-out 1.06s both; }

        @media (prefers-reduced-motion: reduce) {
            .premium .card,
            .premium .card-chart path,
            .premium .card-chart circle,
            .premium .issued,
            .premium .redirect,
            .premium .golink { animation: none; }

            .premium .card::after { display: none; }
            .card-chart path { stroke-dasharray: none; }
        }

        @media (max-width: 400px) {
            h1 { font-size: 34px; }
            .card-tier { font-size: 23px; letter-spacing: .22em; }
        }
    </style>
</head>

<body class="<?php echo $premium ? 'premium' : '' ?>">
    <div class="doorlight" aria-hidden="true"></div>

    <main>
        <?php if ($premium): ?>

            <div class="card">
                <div class="card-brand">OPEN CHAT GRAPH</div>
                <div class="card-tier">PREMIUM</div>
                <div class="card-foot">
                    <div class="card-status">広告は表示されません</div>
                    <svg class="card-chart" width="132" height="46" viewBox="0 0 132 46" aria-hidden="true">
                        <path d="M3 40 L24 32 L45 35 L66 22 L87 26 L108 12 L129 5" />
                        <circle cx="129" cy="5" r="3.6" />
                    </svg>
                </div>
            </div>

            <h1 class="issued">プレミアムユーザーで<br>ログインしました</h1>
            <p class="redirect"><b id="countdown"><?php echo (int) $redirectSeconds ?></b> 秒後にトップページへ移動します</p>
            <a class="golink" href="<?php echo h($topUrl) ?>">いますぐ移動</a>

            <script>
                (function () {
                    var el = document.getElementById('countdown');
                    var left = <?php echo (int) $redirectSeconds ?>;
                    var timer = setInterval(function () {
                        left -= 1;
                        el.textContent = left > 0 ? left : 0;
                        if (left <= 0) { clearInterval(timer); }
                    }, 1000);
                })();
            </script>

        <?php elseif ($optedOut): ?>

            <p class="eyebrow">Open Chat Graph</p>
            <h1>Access granted</h1>
            <p class="lede">このブラウザでは広告が表示されません。ほかのブラウザやシークレットウィンドウでは、もう一度合言葉が必要です。</p>

            <?php if ($message): ?><div class="alert ok"><?php echo h($message) ?></div><?php endif ?>

            <form class="field" method="post" action="">
                <input type="hidden" name="action" value="clear">
                <button type="submit" class="ghost">広告を再表示する</button>
            </form>

            <p class="note">
                設定はこのブラウザのクッキーに保存されます。<br>
                クッキーを消すと設定も消えます。
            </p>

        <?php else: ?>

            <p class="eyebrow">Open Chat Graph</p>
            <h1>Staff entrance</h1>
            <p class="lede">合言葉を入れると、このブラウザだけ広告が出なくなります。</p>

            <?php if ($message): ?><div class="alert ok"><?php echo h($message) ?></div><?php endif ?>
            <?php if ($error): ?><div class="alert ng"><?php echo h($error) ?></div><?php endif ?>

            <form method="post" action="">
                <div class="field">
                    <label for="passphrase">合言葉</label>
                    <input type="text" class="secret" id="passphrase" name="passphrase" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" required autofocus>
                </div>
                <button type="submit">通る</button>
            </form>

            <p class="note">
                このページは検索に出ません。<br>
                広告を消しても、サイトの表示や機能は変わりません。
            </p>

        <?php endif ?>
    </main>
</body>

</html>
