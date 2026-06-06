<!DOCTYPE html>
<html lang="<?php echo t('ja') ?>">

<head prefix="og: http://ogp.me/ns#">
    <?php

    echo gTag(\App\Config\AppConfig::$gtmId) ?>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <?php /* OSステータスバー/ツールバーの色。初期HTMLに必須（JS生成だけだとiOSが拾わないことがある）。
             実際の値は theme.js が解決テーマに合わせて即時更新する */ ?>
    <meta name="theme-color" content="#ffffff">
    <?php /* 「ホーム画面に追加」(PWA)時のみ有効: ページがステータスバー/ダイナミックアイランドの
             下まで広がり、blur付きヘッダーとコンテンツが周囲に透ける（通常のSafari縦持ちでは
             この帯はSafari描画のため不可）。black-translucent はステータス文字が常に白の
             ためライトのPWA起動では視認性が落ちるトレードオフあり */ ?>
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
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
    <?php //\App\Views\Ads\GoogleAdsense::gTag() ?>
</head>

<body class="body">
    <style>
        * {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }

        /* Increase size of the main heading */
        h1 {
            font-size: 5rem;
        }

        /* Break long lines in the code section */
        code {
            word-wrap: break-word;
        }

        /* Set width, center, and add padding to the ordered list */
        ol {
            width: fit-content;
            margin: 0 auto;
            margin-top: 1.5rem;
            padding: 0 1rem;
        }

        /* Break URLs to fit in the list */
        a {
            word-break: break-all;
        }

        .main {
            max-width: var(--width);
            margin-left: 0;
        }

        @media screen and (min-width: 512px) {
            .main .recommend-list li:nth-child(n + 5) {
                display: block;
            }

            .main .recommend-list li:nth-child(n + 9) {
                display: none;
            }

            .main .recommend-list.show-all li:nth-child(n + 9) {
                display: block;
            }
        }
    </style>

    <!-- 固定ヘッダー -->
    <main class="main pad-side-top-ranking body">
        <div style="margin: 0 -1rem; ">
            <?php viewComponent('site_header') ?>
        </div>
        <header style="padding: 1rem 1rem 0 1rem; text-align: center">
            <p style="color: var(--c-text-1); font-size: 11px; text-align: left;">「<?php echo $recommend[2] ?? '' ?>」 ID:<?php echo $open_chat_id ?></p>
            <p style="font-weight: bold; color: var(--c-text-3)">このオープンチャットはオプチャグラフから削除されました😇</p>
            <p style="color: var(--c-text-4); font-size: 13px">LINE内でルームが削除された可能性があります</p>
        </header>
        <?php if (isset($recommend[0]) && $recommend[0]) : ?>
            <aside class="recommend-list-aside">
                <?php viewComponent('recommend_list2', ['recommend' => $recommend[0], 'member' => 0, 'tag' => $recommend[2], 'id' => 0]) ?>
            </aside>
            <hr class="hr-bottom">
        <?php endif ?>
        <?php if (isset($recommend[1]) && $recommend[1]) : ?>
            <aside class="recommend-list-aside">
                <?php viewComponent('recommend_list2', ['recommend' => $recommend[1], 'member' => 0, 'tag' => $recommend[2], 'id' => 0]) ?>
            </aside>
            <hr class="hr-bottom">
        <?php endif ?>
        <aside class="recommend-list-aside">
            <?php viewComponent('top_ranking_comment_list_hour24', ['dto' => $topPageDto]) ?>
        </aside>
        <hr class="hr-bottom">
        <aside class="recommend-list-aside">
            <?php viewComponent('top_ranking_comment_list_hour', ['dto' => $topPageDto]) ?>
        </aside>
        <hr class="hr-bottom">
        <aside class="recommend-list-aside">
            <article class="top-ranking">
                <a class="readMore-btn top-ranking-readMore unset" href="<?php echo url('ranking') ?>">
                    <span class="ranking-readMore">カテゴリーからオプチャを探す<span class="small" style="font-size: 11.5px;">24カテゴリー</span></span>
                </a>
            </article>
        </aside>
    </main>
    <footer>
        <?php viewComponent('footer_inner') ?>
    </footer>
    <script defer src="<?php echo fileUrl("/js/site_header_footer.js", urlRoot: '') ?>"></script>
</body>

</html>