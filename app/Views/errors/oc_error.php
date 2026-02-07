<!DOCTYPE html>
<html lang="<?php echo t('ja') ?>">

<head prefix="og: http://ogp.me/ns#">
    <?php

    echo gTag(\App\Config\AppConfig::$gtmId) ?>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php echo $_meta ?>
    <link rel="stylesheet" href="<?php echo fileUrl('style/mvpmin.css', urlRoot: '') ?>">
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
            <p style="color: #111; font-size: 11px; text-align: left;">「<?php echo $recommend[2] ?? '' ?>」 ID:<?php echo $open_chat_id ?></p>
            <p style="font-weight: bold; color: #777">このオープンチャットはオプチャグラフから削除されました😇</p>
            <p style="color: #aaa; font-size: 13px">LINE内でルームが削除された可能性があります</p>
        </header>
        <?php if (isset($recommend[0]) && $recommend[0]) : ?>
            <aside class="recommend-list-aside">
                <?php viewComponent('recommend_list2', ['recommend' => $recommend[0], 'member' => 0, 'tag' => $recommend[2], 'id' => 0, 'showTags' => true]) ?>
            </aside>
            <hr class="hr-bottom">
        <?php endif ?>
        <?php if (isset($recommend[1]) && $recommend[1]) : ?>
            <aside class="recommend-list-aside">
                <?php viewComponent('recommend_list2', ['recommend' => $recommend[1], 'member' => 0, 'tag' => $recommend[2], 'id' => 0, 'showTags' => true]) ?>
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