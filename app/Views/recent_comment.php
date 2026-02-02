<!DOCTYPE html>
<html lang="<?php echo t('ja') ?>">
<?php

use App\Config\AppConfig;
use App\Views\Ads\GoogleAdsense as GAd;
use Shared\MimimalCmsConfig;

$enableAdsense = MimimalCmsConfig::$urlRoot === ''; // 日本語版のみ広告表示

viewComponent('head', compact('_css', '_meta') + ['dataOverlays' => 'bottom']) ?>

<body class="body">
    <?php if ($enableAdsense): ?>
        <?php \App\Views\Ads\GoogleAdsense::gTag() ?>
    <?php endif ?>
    <style>
        .list-title {
            color: #111;
            all: unset;
            font-size: 20px;
            font-weight: bold;
        }

        .page-select {
            /* margin-top: 1.75rem;
            padding-bottom: 0.85rem; */
            margin: 12px 0 18px 0;
        }

        .page-select form,
        .page-select select {
            height: 40px;
        }

        .recent-comment-list {
            padding: 0;
            margin: 1rem 0;
        }

        .p-small {
            font-size: 13px;
            color: #777;
        }

        .search-pager {
            background: rgb(250, 250, 250);
            width: 100%;
            justify-content: space-evenly;
            padding: 4px 2rem;
        }

        article .search-pager {
            margin: 0 -1rem;
            width: calc(100% + 2rem);
            border-bottom: 1px solid #efefef;
            border-top: 1px solid #efefef;
        }

        .head-pager .search-pager {
            padding: 4px 0rem;
        }

        .head-pager .button01 a {
            height: 36px;
        }

        .top-ranking-list-aside {
            all: unset;
            display: block;
        }

        .top-ranking-list-aside .top-ranking,
        .top-ranking-list-aside .recent-comment-list {
            margin-left: 1rem;
            margin-right: 1rem;
        }

        .button01.prev a {
            border: solid 1px var(--border-color);
            border-radius: var(--border-radius);
        }

        section aside:hover {
            box-shadow: unset;
        }
    </style>
    <!-- 固定ヘッダー -->
    <?php viewComponent('site_header') ?>
    <article style="margin: .5rem 1rem; margin-bottom: 1rem;">
        <header class="openchat-list-title-area unset" style="margin: 0 0 .5rem 0;">
            <div style="flex-direction: column;">
                <h2 class="openchat-list-title" style="font-size: 20px;">
                    コメントのタイムライン
                </h2>
                <p style="margin: 0;">
                    <small class="p-small">オプチャグラフに投稿されたすべてのコメントを表示</small>
                </p>
            </div>
        </header>

        <div class="head-pager">
            <?php viewComponent('pager_nav', compact('pageNumber', 'maxPageNumber') + ['path' => $path]) ?>
        </div>

        <!-- select要素ページネーション -->
        <nav class="page-select unset">
            <form class="unset">
                <select id="page-selector" class="unset">
                    <?php echo $_select ?>
                </select>
                <label for="page-selector" class="unset"><span><?php echo $_label ?></span></label>
            </form>
        </nav>

        <section class="recent-comment-list unset">
            <?php viewComponent(
                'open_chat_list_ranking_comment2',
                compact('openChatList') + [
                    'listLen' => AppConfig::LIST_LIMIT_RECENT_COMMENT,
                    'omitDeleted' => false,
                ]
            ) ?>
        </section>
        <!-- 次のページ・前のページボタン -->
        <?php viewComponent('pager_nav', compact('pageNumber', 'maxPageNumber') + ['path' => $path]) ?>
    </article>
    <section class="unset" style="display: block;">

        <?php viewComponent('footer_inner') ?>

    </section>

    <?php GAd::loadAdsTag() ?>

    <script type="module">
        import {
            applyTimeElapsedString
        } from '<?php echo fileUrl('/js/fetchComment.js') ?>'

        applyTimeElapsedString()
    </script>

    <script defer src="<?php echo fileUrl("/js/site_header_footer.js", urlRoot: '') ?>"></script>
    <?php if ($enableAdsense): ?>
        <script defer src="<?php echo fileurl("/js/security.js", urlRoot: '') ?>"></script>
    <?php endif ?>
    <script>
        ;
        (function(el) {
            if (!el) return

            el.addEventListener('change', () => {
                el.value && (location.href = el.value)
            })
        })(document.getElementById('page-selector'))
    </script>

    <?php echo $_breadcrumbsShema ?>
</body>

</html>