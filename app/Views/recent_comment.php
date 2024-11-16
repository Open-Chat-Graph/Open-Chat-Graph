<!DOCTYPE html>
<html lang="ja">
<?php

use App\Config\AppConfig;
use App\Views\Ads\GoogleAdsence as GAd;

viewComponent('head', compact('_css', '_meta')) ?>

<body class="body">
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
            margin: 1rem 0;
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
            padding: 1.5rem 2rem;
        }

        article .search-pager {
            margin: 0 -1rem;
            width: calc(100% + 2rem);
        }

        .head-pager .search-pager {
            padding: 8px 0rem;
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
            all: unset;
        }
    </style>
    <!-- 固定ヘッダー -->
    <?php viewComponent('site_header') ?>
    <?php GAd::output(GAd::AD_SLOTS['siteTopRectangle']) ?>
    <hr class="hr-top">
    <article style="margin: 0 1rem;">
        <header class="openchat-list-title-area unset" style="margin: 1rem 0;">
            <div style="flex-direction: column;">
                <h2 class="list-title">
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
                    'listLen' => AppConfig::RECENT_COMMENT_LIST_LIMIT,
                    'omitDeleted' => false,
                    'showAds' => true
                ]
            ) ?>
        </section>
        <!-- 次のページ・前のページボタン -->
        <?php viewComponent('pager_nav', compact('pageNumber', 'maxPageNumber') + ['path' => $path]) ?>
    </article>

    <section class="unset" style="display: block; margin: 1rem 0">
        <hr class="hr-bottom">

        <?php GAd::output(GAd::AD_SLOTS['siteSeparatorResponsive']) ?>

        <hr class="hr-top">
        <aside class="top-ranking-list-aside">
            <?php viewComponent('topic_tag', compact('topPageDto')) ?>
        </aside>
        <hr class="hr-bottom">

        <?php GAd::output(GAd::AD_SLOTS['siteSeparatorResponsive']) ?>

        <hr class="hr-top">
        <aside class="top-ranking-list-aside">
            <?php viewComponent('top_ranking_recent_comments') ?>
        </aside>
        <hr class="hr-bottom">


        <?php GAd::output(GAd::AD_SLOTS['siteSeparatorResponsive']) ?>

        <hr class="hr-top">
        <aside class="top-ranking-list-aside">
            <?php viewComponent('top_ranking_comment_list_hour', ['dto' => $topPageDto]) ?>
        </aside>
        <hr class="hr-bottom">

        <?php GAd::output(GAd::AD_SLOTS['siteSeparatorResponsive']) ?>

        <hr class="hr-top">
        <aside class="top-ranking-list-aside">
            <?php viewComponent('top_ranking_comment_list_hour24', ['dto' => $topPageDto]) ?>
        </aside>
        <hr class="hr-bottom">

        <?php GAd::output(GAd::AD_SLOTS['siteSeparatorResponsive']) ?>

        <hr class="hr-top">
        <aside class="top-ranking-list-aside">
            <?php viewComponent('top_ranking_comment_list_week', ['dto' => $topPageDto]) ?>
        </aside>
        <hr class="hr-bottom">

        <?php viewComponent('pager_nav', compact('pageNumber', 'maxPageNumber') + ['path' => $path]) ?>

        <footer class="footer-elem-outer">
            <?php viewComponent('footer_share_nav', ['title' => $_meta->title]) ?>
            <?php viewComponent('footer_inner') ?>
        </footer>

    </section>

    <?php \App\Views\Ads\GoogleAdsence::loadAdsTag() ?>

    <script type="module">
        import {
            getComment
        } from '<?php echo fileUrl('/js/fetchComment.js', urlRoot: '') ?>'

        getComment(0, '<?php echo URL_ROOT ?>')
    </script>

    <script defer src="<?php echo fileUrl("/js/site_header_footer.js", urlRoot: '') ?>"></script>
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