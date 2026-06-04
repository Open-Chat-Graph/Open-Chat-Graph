<!DOCTYPE html>
<html lang="<?php echo t('ja') ?>">
<?php

use App\Config\AppConfig;
use Shared\MimimalCmsConfig;
use App\Views\Ads\GoogleAdsense as GAd;

$enableAdsense = true;

/** @var \App\Services\StaticData\Dto\StaticTopPageDto $dto */
viewComponent('head', compact('_css', '_meta', '_schema')) ?>

<body class="top-page">
    <?php if ($enableAdsense): ?>
        <?php \App\Views\Ads\GoogleAdsense::gTag() ?>
    <?php endif ?>

    <?php // トップ表示時は最上部に独自の検索を置くため、ヘッダーの検索ボタンは隠す（hideSearchButton） ?>
    <?php viewComponent('site_header', compact('_updatedAt') + ['hideSearchButton' => true]) ?>
    <div class="pad-side-top-ranking body" style="overflow: hidden; padding-top: 0;">

        <div style="padding: 0 1rem; margin-bottom: 1rem;">
            <div style="margin: 1rem 0;">
                <small style="display: block; color: #000; font-size: 11px; font-weight: bold; line-height: 1;">LINE</small>
                <h1 style="margin: 0; padding: 0; font-size: 28px; font-weight: bold; line-height: 1;">OPENCHAT Graph <?php echo MimimalCmsConfig::$urlRoot ? strtoupper(str_replace('/', '', MimimalCmsConfig::$urlRoot)) : '' ?>📈</h1>
            </div>
            <small style="display: block; color: #000; font-size: 10px; margin: .5rem 0 1rem 0;">
                <?php
                $languages = array_keys(AppConfig::LINE_OPEN_URL);
                ?>

                <?php foreach ($languages as $key => $lang): ?>
                    <?php if ($lang === MimimalCmsConfig::$urlRoot): ?>
                        <span style="color: inherit; font-weight: bold;"><?php echo t('オプチャグラフ', $lang) ?></span>
                    <?php else: ?>
                        <a href="<?php echo url(["urlRoot" => "", "paths" => [$lang]]) ?>" style="color: inherit;"><?php echo t('オプチャグラフ', $lang) ?></a>
                    <?php endif; ?>
                    <?php if ($key !== count($languages) - 1): ?>／<?php endif; ?>
                <?php endforeach; ?>
            </small>
        </div>

        <?php // 公式LINEオープンチャットのトップに倣い、最上部に「オープンチャットを検索」を設置。送信先はヘッダー検索と同じ /ranking。
              // CSSは自己完結（theme_discovery 非依存）。 ?>
        <div style="padding: 0 1rem; margin-bottom: 1rem;">
            <form method="GET" action="<?php echo url('ranking') ?>" role="search" class="oc-hero-search">
                <svg class="oc-hero-search__icon" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" style="position:absolute;left:14px;top:50%;width:20px;height:20px;transform:translateY(-50%);fill:#9aa3af;pointer-events:none"><path d="M15.5 14h-.79l-.28-.27a6.5 6.5 0 1 0-.7.7l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0A4.5 4.5 0 1 1 14 9.5 4.5 4.5 0 0 1 9.5 14z" /></svg>
                <input id="oc-hero-input" class="oc-hero-search__input" type="search" name="keyword" inputmode="search" enterkeyhint="search"
                    autocomplete="off" autocapitalize="off" spellcheck="false" maxlength="100" required
                    placeholder="<?php echo t('オープンチャットを検索') ?>" aria-label="<?php echo t('オープンチャットを検索') ?>">
                <span id="oc-hero-clear" class="oc-hero-search__clear" role="button" aria-label="<?php echo t('クリア') ?>" tabindex="0" hidden>&times;</span>
                <input type="hidden" name="list" value="all">
                <input type="hidden" name="sort" value="member">
                <input type="hidden" name="order" value="desc">
            </form>
        </div>
        <style>
            /* トップ最上部「オープンチャットを検索」。自己完結CSS（グローバルな汎用 form 枠は打ち消す）。 */
            .oc-hero-search{position:relative;display:block;width:100%;margin:0;padding:0;border:0;background:none;box-shadow:none}
            .oc-hero-search__icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);width:20px;height:20px;fill:#9aa3af;pointer-events:none}
            /* iOS Safari のフォーカス時オートズーム回避のため font-size は16px以上 */
            .oc-hero-search__input{display:block;width:100%;box-sizing:border-box;height:48px;margin:0;padding:0 44px;font-size:16px;color:#0f1620;background:#f6f8fa;border:1.5px solid #e4e8ee;border-radius:12px;outline:none;-webkit-appearance:none;appearance:none;transition:border-color .15s,background .15s,box-shadow .15s}
            .oc-hero-search__input::placeholder{color:#9aa3af}
            .oc-hero-search__input::-webkit-search-cancel-button{-webkit-appearance:none;appearance:none;display:none}
            .oc-hero-search__input:focus{background:#fff;border-color:#06c755;box-shadow:0 0 0 3px rgba(6,199,85,.14)}
            .oc-hero-search__clear{position:absolute;right:6px;top:0;bottom:0;width:40px;display:flex;align-items:center;justify-content:center;color:#9aa3af;font-size:20px;line-height:1;cursor:pointer;-webkit-user-select:none;user-select:none}
            .oc-hero-search__clear:hover{color:#5b6573}
            .oc-hero-search__clear[hidden]{display:none}
        </style>
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
            <div id="myListDiv" style="transition: all 0.3s; opacity: 0;"></div>
        <?php endif ?>
        <hr class="hr-top" style="margin-bottom: 8px;">
        <div class="modify-top-padding" style="margin-bottom: 0rem;">
            <?php viewComponent('topic_tag', ['topPageDto' => $dto]);
            AppConfig::$listLimitTopRanking = 10; ?>
        </div>
        <hr class="hr-top" style="margin-bottom: 8px;">
        <div class="modify-top-padding" style="margin-bottom: 0rem;">
            <?php viewComponent('popular_themes', ['prominent' => true]) ?>
        </div>
        <hr class="hr-top" style="margin-bottom: 8px;">
        <?php viewComponent('top_ranking_comment_list_hour', compact('dto')) ?>
        <hr class="hr-top" style="margin-bottom: 8px;">
        <?php viewComponent('top_ranking_comment_list_hour24', compact('dto')) ?>
        <hr class="hr-top" style="margin-bottom: 8px;">
        <?php if (MimimalCmsConfig::$urlRoot === ''): ?>
            <?php viewComponent('top_ranking_recent_comments') ?>
        <?php endif ?>

        <hr class="hr-top" style="margin-bottom: 8px;">
        <?php viewComponent('top_ranking_comment_list_week', compact('dto')) ?>

        <hr class="hr-top" style="margin-bottom: 8px;">
        <?php viewComponent('top_ranking_comment_list_member', compact('dto')) ?>

        <hr class="hr-top" style="margin-bottom: 8px;">
        <?php viewComponent('recommend_list2', ['recommend' => $officialDto, 'id' => 0]) ?>
        <hr class="hr-top" style="margin-bottom: 8px;">
        <?php viewComponent('recommend_list2', ['recommend' => $officialDto2, 'id' => 0]) ?>

        <?php viewComponent('footer_inner') ?>
        <div class="refresh-time" style="width: fit-content; margin: auto; padding-bottom: 0.5rem; margin-top: -9px;">
            <div class="refresh-icon"></div><time style="font-size: 11px; color: #b7b7b7; margin-left:3px" datetime="<?php echo $_updatedAt->format(\DateTime::ATOM) ?>"><?php echo $_updatedAt->format('Y/n/j G:i') ?></time>
        </div>
    </div>
    <?php \App\Views\Ads\GoogleAdsense::loadAdsTag() ?>

    <script defer src="<?php echo fileUrl("/js/site_header_footer.js", urlRoot: '') ?>"></script>
    <?php if ($enableAdsense): ?>
        <script defer src="<?php echo fileurl("/js/security.js", urlRoot: '') ?>"></script>
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

                fetch('<?php echo MimimalCmsConfig::$urlRoot ?>/mylist-api')
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