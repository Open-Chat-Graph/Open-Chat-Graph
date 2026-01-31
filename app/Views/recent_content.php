<!DOCTYPE html>
<html lang="<?php echo t('ja') ?>">
<?php viewComponent('head', compact('_css', '_meta') + ['noindex' => true] + ['disableGAd' => false]) ?>

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
            margin-top: 1.75rem;
            padding-bottom: 0.85rem;
        }

        .p-small {
            font-size: 13px;
            color: #777;

        }
    </style>
    <!-- 固定ヘッダー -->
    <?php viewComponent('site_header') ?>
    <main class="ranking-page-main" style="margin-top: 8px; padding-top: 0; overflow: hidden;">
        <article>
            <header class="openchat-list-title-area unset">
                <div style="flex-direction: column;">
                    <h2 class="list-title">
                        オプチャグラフに最近登録されたオープンチャット
                    </h2>
                    <p>
                        <small class="p-small">このページではオプチャグラフに登録されたオープンチャットを登録日時順で表示します。</small>
                    </p>
                    <p>
                        <small class="p-small"><a style="color:#777" rel="external nofollow noopener" href="https://openchat.line.me/jp/explore" target="_blank">ランキング（LINE公式サイト）<span class="line-link-icon777"></span></a>にランクインしたオープンチャットは、オプチャグラフに随時登録されます。</small>
                    </p>
                    <p>
                        <small class="p-small">LINE公式のランキングは1時間毎に更新されるため、オプチャグラフはその時間帯に合わせて公式サイトからデータを取得しています。</small>
                    </p>
                </div>
            </header>
            <!-- select要素ページネーション -->
            <hr>
            <nav class="page-select unset">
                <form class="unset">
                    <select id="page-selector" class="unset">
                        <?php echo $_select ?>
                    </select>
                    <label for="page-selector" class="unset"><span><?php echo $_label ?></span></label>
                </form>
            </nav>
            <?php viewComponent('open_chat_list', compact('openChatList', 'isAdmin')) ?>
            <!-- 次のページ・前のページボタン -->
            <?php viewComponent('pager_nav', compact('pageNumber', 'maxPageNumber') + ['path' => $path]) ?>
        </article>
    </main>
    <?php viewComponent('footer_inner') ?>
    <?php \App\Views\Ads\GoogleAdsense::loadAdsTag() ?>
    <script>
        const admin = <?php echo isAdsAdmin() ? 1 : 0; ?>;
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