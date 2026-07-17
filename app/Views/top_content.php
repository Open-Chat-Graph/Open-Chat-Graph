<!DOCTYPE html>
<html lang="<?php echo t('ja') ?>">
<?php

use App\Config\AppConfig;
use Shared\MimimalCmsConfig;

$enableAdsense = true;

/** @var \App\Services\StaticData\Dto\StaticTopPageDto $dto */
viewComponent('head', compact('_css', '_meta', '_schema')) ?>

<body class="top-page">
    <?php // トップはオファーウォールを出さない方針のため gTag（adsbygoogle.js）自体を読み込まない。
          // display広告停止中もトップ以外はオファーウォール用に gTag を出している（GoogleAdsenseConfig::$enableOfferwallTag）。
          // 復活させる場合: if ($enableAdsense) { \App\Views\Ads\GoogleAdsense::gTag(); } ?>

    <?php // トップ表示時は最上部に独自の検索を置くため、ヘッダーの検索ボタンは隠す（hideSearchButton） ?>
    <?php // ヒーロー側が h1 を持つため、ヘッダーのサイトタイトルは p に降格（demoteTitle） ?>
    <?php viewComponent('site_header', compact('_updatedAt') + ['hideSearchButton' => true, 'demoteTitle' => true]) ?>
    <div class="pad-side-top-ranking body" style="overflow: hidden; padding-top: 0;">

        <?php // ホームヒーロー: 日本語ブランドを主役にしたロックアップ + 検索。
              // 言語切り替えリンクはフッター(footer_inner)へ移設。スタイルは pages/top_page.css ?>
        <section class="oc-home-hero">
            <svg class="oc-home-hero__spark" viewBox="0 0 160 56" aria-hidden="true"><polyline points="2,50 28,42 52,45 78,30 104,34 130,16 158,5" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" /></svg>
            <div class="oc-home-hero__top">
                <div>
                    <small class="oc-home-hero__eyebrow">LINE OPENCHAT GRAPH<?php echo MimimalCmsConfig::$urlRoot ? ' ' . strtoupper(str_replace('/', '', MimimalCmsConfig::$urlRoot)) : '' ?></small>
                    <h1 class="oc-home-hero__title"><?php echo t('オプチャグラフ') ?></h1>
                </div>
                <?php // テーマ切替（フッターと同型ピル・3状態）。スタイルは site_footer.css を共用 ?>
                <div class="footer-theme-toggle-row oc-home-hero__theme" data-nosnippet>
                    <button class="footer-theme-toggle theme-toggle-btn unset" type="button" aria-label="<?php echo t('ダークモード切替') ?>">
                        <svg class="theme-icon-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                        <svg class="theme-icon-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                        <svg class="theme-icon-auto" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 3a9 9 0 0 0 0 18z" fill="currentColor" stroke="none"/></svg>
                        <span class="footer-theme-toggle-label"><?php echo t('テーマ') ?>: <span class="theme-label-light"><?php echo t('ライト') ?></span><span class="theme-label-dark"><?php echo t('ダーク') ?></span><span class="theme-label-auto"><?php echo t('自動') ?></span></span>
                    </button>
                </div>
            </div>
            <p class="oc-home-hero__tagline"><?php echo t('LINEオープンチャットの人数推移とランキングを毎時間記録') ?></p>
            <div class="oc-home-hero__metarow">
                <div class="oc-home-hero__meta"><span class="oc-home-hero__dot" aria-hidden="true"></span><?php echo t('1時間ごとに更新') ?><span class="oc-home-hero__time">・<?php echo $_updatedAt->format('G:i') ?></span></div>
                <?php // 上級者向けの分析ツール（分析Labs）とAI連携(MCP)への控えめな導線。悪目立ちさせない小さなテキストリンク。ja のみ。 ?>
                <?php if (MimimalCmsConfig::$urlRoot === ''): ?>
                    <a class="oc-home-hero__labs unset" href="<?php echo url('labs') ?>" aria-label="分析Labs（上級者向けの分析ツール）を開く">
                        <svg viewBox="0 -960 960 960" aria-hidden="true"><path d="M209-120q-42 0-70.5-28.5T110-217q0-14 3-25.5t9-21.5l228-341q10-14 15-31t5-34v-110h-20q-13 0-21.5-8.5T320-810q0-13 8.5-21.5T350-840h260q13 0 21.5 8.5T640-810q0 13-8.5 21.5T610-780h-20v110q0 17 5 34t15 31l227 341q6 9 9.5 20.5T850-217q0 41-28 69t-69 28H209Zm271-340Z"/></svg>
                        <span>分析Labs</span>
                    </a>
                    <a class="oc-home-hero__labs unset" href="<?php echo url('mcp') ?>" aria-label="AI連携・データAPI（MCP）の案内ページを開く">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2l1.9 6.1L20 10l-6.1 1.9L12 18l-1.9-6.1L4 10l6.1-1.9L12 2zm7 11l1 3.2 3.2 1-3.2 1-1 3.2-1-3.2-3.2-1 3.2-1 1-3.2z"/></svg>
                        <span>AI連携 (MCP)</span>
                    </a>
                <?php endif ?>
            </div>

            <?php // 公式LINEオープンチャットのトップに倣い「オープンチャットを検索」。送信先はヘッダー検索と同じ /ranking。 ?>
            <form method="GET" action="<?php echo url('ranking') ?>" role="search" class="oc-hero-search">
                <svg class="oc-hero-search__icon" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><path d="M15.5 14h-.79l-.28-.27a6.5 6.5 0 1 0-.7.7l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0A4.5 4.5 0 1 1 14 9.5 4.5 4.5 0 0 1 9.5 14z" /></svg>
                <input id="oc-hero-input" class="oc-hero-search__input" type="search" name="keyword" inputmode="search" enterkeyhint="search"
                    autocomplete="off" autocapitalize="off" spellcheck="false" maxlength="100" required
                    placeholder="<?php echo t('オープンチャットを検索') ?>" aria-label="<?php echo t('オープンチャットを検索') ?>">
                <span id="oc-hero-clear" class="oc-hero-search__clear" role="button" aria-label="<?php echo t('クリア') ?>" tabindex="0" hidden>&times;</span>
                <input type="hidden" name="list" value="all">
                <input type="hidden" name="sort" value="member">
                <input type="hidden" name="order" value="desc">
            </form>
        </section>
        <script>
            /* クリア✕: テーマ検索と同じ挙動。変換(IME)確定前は✕を隠し、iOSで「消去後に入力できない」状態を防ぐ。 */
            (function () {
                var input = document.getElementById('oc-hero-input');
                var clearBtn = document.getElementById('oc-hero-clear');
                if (!input || !clearBtn) return;
                var composing = false;
                var toggleClear = function () { clearBtn.hidden = !input.value || composing; };
                input.addEventListener('compositionstart', function () { composing = true; toggleClear(); });
                input.addEventListener('compositionend', function () { composing = false; toggleClear(); });
                input.addEventListener('input', toggleClear);
                var doClear = function () { input.value = ''; toggleClear(); };
                clearBtn.addEventListener('touchstart', function (e) { e.preventDefault(); doClear(); }, { passive: false });
                clearBtn.addEventListener('mousedown', function (e) { e.preventDefault(); }); // PC: 入力欄のフォーカスを奪わない
                clearBtn.addEventListener('click', function () { if (input.value) doClear(); });
                // キーボード操作: Enter/Space でクリア（tabindex=0 でフォーカス可能に）
                clearBtn.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); if (input.value) doClear(); } });
                toggleClear();
            })();
        </script>

        <?php if (MimimalCmsConfig::$urlRoot === ''): // TODO: 日本以外ではマイリストが無効
        ?>
            <div id="myListDiv" data-nosnippet style="transition: all 0.3s; opacity: 0;"></div>
        <?php endif ?>
        <div class="modify-top-padding" style="margin-bottom: 0rem;">
            <?php viewComponent('topic_tag', ['topPageDto' => $dto]);
            AppConfig::$listLimitTopRanking = 10; ?>
        </div>

        <?php if (MimimalCmsConfig::$urlRoot === ''): // 読み物（ブログ）棚。急上昇テーマ直後（≈20%深度）に置き到達率を最大化。ja のみ。 ?>
            <?php viewComponent('home_blog_shelf') ?>
        <?php endif ?>

        <?php viewComponent('top_ranking_comment_list_hour', compact('dto')) ?>
        <?php viewComponent('top_ranking_comment_list_hour24', compact('dto')) ?>

        <?php if (MimimalCmsConfig::$urlRoot === ''): ?>
            <?php viewComponent('top_ranking_recent_comments') ?>
        <?php endif ?>

        <?php viewComponent('top_ranking_comment_list_week', compact('dto')) ?>

        <?php viewComponent('top_ranking_comment_list_member', compact('dto')) ?>

        <?php viewComponent('recommend_list2', ['recommend' => $officialDto, 'id' => 0]) ?>
        <?php viewComponent('recommend_list2', ['recommend' => $officialDto2, 'id' => 0]) ?>

        <?php // シェア導線（oc ページと共通のコンポーネント）。ヒーロー内だと検索→コンテンツの流れを
              // 分断するため、コンテンツを見終えた後のページ末尾（フッター手前）に置く。
              // og:image は言語別デフォルトOGP ?>
        <?php viewComponent('share_buttons', [
            '_shareUrl' => url(),
            '_shareGa' => ['content_type' => 'top'],
        ]) ?>

        <?php // フッター前の横長枠は撤去: 視認率15%/CTR0.03%/RPM¥12（AdSense実測）で「見られない死に枠」。
              // トップは下も上もスクロール到達が壊滅的（30%到達2.6%）＝埋め込み広告に不向きなため置かない。 ?>
        <?php viewComponent('footer_inner') ?>
        <div class="refresh-time" style="width: fit-content; margin: auto; padding-bottom: 0.5rem; margin-top: -9px;">
            <div class="refresh-icon"></div><time style="font-size: 11px; color: var(--c-text-5); margin-left:3px" datetime="<?php echo $_updatedAt->format(\DateTime::ATOM) ?>"><?php echo $_updatedAt->format('Y/n/j G:i') ?></time>
        </div>
    </div>
    <?php \App\Views\Ads\GoogleAdsense::loadAdsTag() ?>

    <script defer src="<?php echo fileUrl("/js/site_header_footer.js", urlRoot: '') ?>"></script>
    <?php if ($enableAdsense): ?>
        <?php viewComponent('ad_guard') ?>
    <?php endif ?>
    <?php if (MimimalCmsConfig::$urlRoot === ''): // TODO: 日本以外ではマイリストが無効
    ?>
        <script>
            const urlRoot = '<?php echo MimimalCmsConfig::$urlRoot ?>'
            let lastList = ''

            function fetchMyList(name) {
                const cookieRegex = new RegExp(`(^|;)\\s*${name}\\s*=\\s*([^;]+)`)
                const cookieMatch = document.cookie.match(cookieRegex)
                const myListDiv = document.getElementById('myListDiv')
                if (!cookieMatch) {
                    myListDiv.textContent && (myListDiv.textContent = '')
                    return
                }

                fetch('<?php echo MimimalCmsConfig::$urlRoot ?>/mylist-api', { headers: { 'X-Ocg-Client': '1' } })
                    .then((res) => {
                        if (res.status === 200)
                            return res.text();
                        else
                            throw new Error()
                    })
                    .then((data) => {
                        if (lastList === data)
                            return

                        lastList = data
                        myListDiv.textContent = ''
                        myListDiv.insertAdjacentHTML('afterbegin', data)
                        myListDiv.style.opacity = '1'
                    })
                    .catch(error => console.error('エラー', error))
            }

            window.addEventListener("pageshow", function(event) {
                fetchMyList('myList')
            });
        </script>
        <script type="module">
            import {
                getComment
            } from '<?php echo fileUrl('/js/fetchComment.js', urlRoot: '') ?>'

            getComment(0, '<?php echo MimimalCmsConfig::$urlRoot ?>')
        </script>
    <?php endif ?>
</body>

</html>