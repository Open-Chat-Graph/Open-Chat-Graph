<!DOCTYPE html>
<html lang="ja">
<?php

use App\Config\AppConfig;
use App\Views\Ads\GoogleAdsence as GAd;

viewComponent('head', compact('_css', '_meta')) ?>

<body class="body">
    <style>
        hr {
            border-bottom: solid 1px var(--border-color);
            margin: 12px 0;
        }

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
            padding: 0;
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
    <footer class="footer-elem-outer">
        <?php viewComponent('footer_share_nav', ['title' => $_meta->title]) ?>
        <?php viewComponent('footer_inner') ?>
    </footer>
    <?php \App\Views\Ads\GoogleAdsence::loadAdsTag() ?>
    <script defer src="<?php echo fileurl("/js/site_header_footer.js") ?>"></script>
    <script>
        ;
        (function(el) {
            if (!el) return

            el.addEventListener('change', () => {
                el.value && (location.href = el.value)
            })
        })(document.getElementById('page-selector'))
    </script>

    <script type="module">
        import {
            applyTimeElapsedString
        } from '<?php echo fileUrl('/js/fetchComment.js') ?>'

        applyTimeElapsedString()
    </script>

    <?php echo $_breadcrumbsShema ?>
</body>

</html>