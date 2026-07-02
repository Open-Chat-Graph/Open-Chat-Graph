<!DOCTYPE html>
<html lang="<?php echo t('ja') ?>">
<?php

use App\Config\AppConfig;
use App\Views\Ads\GoogleAdsense as GAd;
use Shared\MimimalCmsConfig;

$enableAdsense = !isset($_adminDto);

viewComponent('oc_head', compact('_css', '_meta', '_schema') + ['dataOverlays' => 'bottom']); ?>

<body>
  <?php viewComponent('site_header') ?>

  <div class="unset openchat body" style="overflow: hidden;">
    <article class="unset" style="display: block;">
      <section class="openchat-header unset" style="padding: 10px 1rem 8px 1rem;">
        <div class="talkroom_banner_img_area">
          <img class="talkroom_banner_img" aria-hidden="true" alt="<?php echo $oc['name'] ?>" src="<?php echo imgUrl($oc['img_url']) ?>">
          <?php if (MimimalCmsConfig::$urlRoot === ''): ?>
            <nav class="my-list-form">
              <label class="checkbox-label" for="my-list-checkbox">
                <input type="checkbox" id="my-list-checkbox">
                <span>トップにピン留め</span>
              </label>
            </nav>
          <?php endif ?>
        </div>

        <div class="openchat-header-right">
          <div>
            <h1 class="talkroom_link_h1 unset"><?php if ($oc['emblem'] === 1) : ?><span class="super-icon sp"></span><?php elseif ($oc['emblem'] === 2) : ?><span class="super-icon official"></span><?php endif ?><?php echo $oc['name'] ?></h1>
            <a class="link-mark" style="text-decoration: none; width: fit-content;" title="<?php echo $oc['name'] ?>" rel="external" target="_blank" href="<?php echo AppConfig::LINE_OPEN_URL[MimimalCmsConfig::$urlRoot] . $oc['emid'] . AppConfig::LINE_OPEN_URL_SUFFIX ?>"><span class="link-title" style="background: unset; color: var(--c-text-5); -webkit-text-fill-color: unset; font-weight: normal; line-height: 125%; margin-bottom: -1px;"><!-- <span aria-hidden="true" style="font-size: 10px; margin-right:2px;">🔗</span> --><?php echo t('LINEオープンチャット') ?></span></a>
          </div>

          <div class="talkroom_description_box close" id="talkroom_description_box">
            <p class="talkroom_description" id="talkroom-description">
              <span id="talkroom-description-btn"><?php echo $formatedDescription ?></span>
            </p>
            <button id="talkroom-description-close-btn" class="close-btn" title="<?php echo t('一部を表示') ?>"><?php echo t('一部を表示') ?></button>
            <div class="more" id="read_more_btn">
              <div class="more-separater">&nbsp;</div>
              <button class="unset more-text" style="font-weight: bold; color: var(--c-text-1);" title="<?php echo t('すべて見る') ?>">…<?php echo t('すべて見る') ?></button>
            </div>
          </div>

          <?php // 毎時変動する人数・増減はGoogle検索スニペットから除外（説明文はスニペットに使われるべきなので付けない） ?>
          <div class="talkroom_number_of_members" data-nosnippet>
            <span class="number_of_members"><?php echo sprintfT('メンバー %s人', number_format($oc['member'])) ?></span>
          </div>

          <?php if (isset($_hourlyRange)) : ?>
            <div class="talkroom_number_of_stats stats-hourly" data-nosnippet style="line-height: 135%; margin-top: 3px;">
              <div class="number-box ">
                <span aria-hidden="true" style="margin-right: 1px; font-size: 9px; user-select: none;">🔥</span>
                <span style="margin-right: 4px;" class="openchat-itme-stats-title"><?php echo $_hourlyRange ?></span>
                <div>
                  <span class="openchat-item-stats"><?php echo sprintfT('%s人', signedNumF($oc['rh_diff_member'])) ?></span><span class="openchat-item-stats percent">(<?php echo signedNum(signedCeil($oc['rh_percent_increase'] * 10) / 10) ?>%)</span>
                </div>
              </div>
            </div>
          <?php endif ?>

          <div class="talkroom_number_of_stats stats-daily" data-nosnippet>

            <?php if (isset($oc['rh24_diff_member']) && $oc['rh24_diff_member'] >= AppConfig::RECOMMEND_MIN_MEMBER_DIFF_H24) : ?>
              <div class="number-box " style="margin-right: 6px;">
                <span aria-hidden="true" style="margin-right: 1px; font-size: 9px; user-select: none;">🚀</span>
                <span class="openchat-itme-stats-title"><?php echo t('24時間') ?></span>
                <div>
                  <span class="openchat-item-stats"><?php echo sprintfT('%s人', signedNumF($oc['rh24_diff_member'])) ?></span><span class="openchat-item-stats percent">(<?php echo signedNum(signedCeil($oc['rh24_percent_increase'] * 10) / 10) ?>%)</span>
                </div>
              </div>
            <?php elseif (isset($oc['rh24_diff_member'])) : ?>
              <div class="number-box" style="margin-right: 6px;">
                <span class="openchat-itme-stats-title"><?php echo t('24時間') ?></span>
                <?php if (($oc['rh24_diff_member'] ?? 0) !== 0) : ?>
                  <div>
                    <span class="openchat-item-stats"><?php echo sprintfT('%s人', signedNumF($oc['rh24_diff_member'])) ?></span><span class="openchat-item-stats percent">(<?php echo signedNum(signedCeil($oc['rh24_percent_increase'] * 10) / 10) ?>%)</span>
                  </div>
                <?php elseif ($oc['rh24_diff_member'] === 0) : ?>
                  <span class="zero-stats">±0</span>
                <?php endif ?>
              </div>
            <?php endif ?>

            <?php if (isset($oc['rw_diff_member']) && $oc['rw_diff_member'] >= AppConfig::RECOMMEND_MIN_MEMBER_DIFF_H24) : ?>
              <div class="number-box " style="margin-right: 6px;">
                <svg class="MuiSvgIcon-root MuiSvgIcon-fontSizeMedium show-north css-162gv95" focusable="false" aria-hidden="true" viewBox="0 0 24 24" data-testid="NorthIcon">
                  <path d="m5 9 1.41 1.41L11 5.83V22h2V5.83l4.59 4.59L19 9l-7-7-7 7z"></path>
                </svg>
                <span class="openchat-itme-stats-title"><?php echo t('1週間') ?></span>
                <div>
                  <span class="openchat-item-stats"><?php echo sprintfT('%s人', signedNumF($oc['rw_diff_member'])) ?></span><span class="openchat-item-stats percent">(<?php echo signedNum(signedCeil($oc['rw_percent_increase'] * 10) / 10) ?>%)</span>
                </div>
              </div>
            <?php elseif (isset($oc['rw_diff_member'])) : ?>
              <div class="number-box" style="margin-right: 6px;">
                <span class="openchat-itme-stats-title"><?php echo t('1週間') ?></span>
                <?php if (($oc['rw_diff_member'] ?? 0) !== 0) : ?>
                  <div>
                    <span class="openchat-item-stats"><?php echo sprintfT('%s人', signedNumF($oc['rw_diff_member'])) ?></span><span class="openchat-item-stats percent">(<?php echo signedNum(signedCeil($oc['rw_percent_increase'] * 10) / 10) ?>%)</span>
                  </div>
                <?php elseif ($oc['rw_diff_member'] === 0) : ?>
                  <span class="zero-stats">±0</span>
                <?php endif ?>
              </div>
            <?php endif ?>

          </div>
        </div>

      </section>

      <?php /* ナビチップのスタイルは style/pages/room_page.css に移設済み (margin/padding/border 含む)。
               ヘッダーとの区切り罫線(hr)は廃止し、余白のみで区切る */ ?>

      <nav class="oc-desc-nav">
        <aside class="oc-desc-nav-category" style="display: flex; align-items:center; min-width: calc(50% - 1rem);">
          <span class="oc-nav-chips">
            <?php if (is_int($oc['api_created_at'])) : ?>
              <span class="oc-nav-pair">
                <span class="oc-nav-pair__label"><?php echo t('カテゴリー') ?></span>
                <a class="oc-nav-chip oc-nav-chip--category" href="<?php echo url('ranking' . ($oc['category'] ? ('/' . $oc['category']) : '')) ?>"><?php echo $category ?></a>
              </span>
            <?php endif ?>
            <?php // タグチップは getOpenChatByIdWithTag が返す tag1 を直接使う（recommend は静的キャッシュ側で描画） ?>
            <?php if (!empty($oc['tag1'])) : ?>
              <span class="oc-nav-pair">
                <span class="oc-nav-pair__label"><?php echo t('タグ') ?></span>
                <a class="oc-nav-chip oc-nav-chip--tag" href="<?php echo url('recommend/' . urlencode(htmlspecialchars_decode((string)$oc['tag1']))) ?>"><?php echo $oc['tag1'] ?></a>
              </span>
            <?php endif ?>
          </span>
        </aside>

        <div class="oc-desc-nav-actions">
          <?php if (isset($_adminDto)) : ?>
            <a href="<?php echo url('oc', $oc['id']) ?>" style="display: flex; align-items: center; justify-content: center; padding: 0 14px; background: var(--c-grad-blue-btn); border-radius: 99px; color: var(--c-text-inverse); text-decoration: none; font-size: 18px;">✕</a>
          <?php else : ?>
            <a id="admin-gear-btn" href="<?php echo url('oc', $oc['id'], 'admin') ?>" style="display: none; align-items: center; justify-content: center; padding: 0 14px; background: var(--c-grad-orange-btn); border-radius: 99px; color: var(--c-text-inverse); text-decoration: none; font-size: 18px;">⚙</a>
          <?php endif ?>
          <section class="open-btn sp-btn" style="flex: 1; margin: 0; padding: 0;">
            <?php if ($oc['url']) : ?>
              <a href="<?php echo url('oc', $oc['id'], 'jump') ?>" class="openchat_link" style="font-size: 16px;">
                <div style="display: flex; align-items: center; justify-content: center;">
                  <?php if ($oc['join_method_type'] !== 0) : ?>
                    <svg style="height: 12px; fill: var(--c-text-inverse); margin-right: 3px;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 489.4 489.4" xml:space="preserve">
                      <path d="M99 147v51.1h-3.4c-21.4 0-38.8 17.4-38.8 38.8v213.7c0 21.4 17.4 38.8 38.8 38.8h298.2c21.4 0 38.8-17.4 38.8-38.8V236.8c0-21.4-17.4-38.8-38.8-38.8h-1v-51.1C392.8 65.9 326.9 0 245.9 0 164.9.1 99 66 99 147m168.7 206.2c-3 2.2-3.8 4.3-3.8 7.8.1 15.7.1 31.3.1 47 .3 6.5-3 12.9-8.8 15.8-13.7 7-27.4-2.8-27.4-15.8v-.1c0-15.7 0-31.4.1-47.1 0-3.2-.7-5.3-3.5-7.4-14.2-10.5-18.9-28.4-11.8-44.1 6.9-15.3 23.8-24.3 39.7-21.1 17.7 3.6 30 17.8 30.2 35.5 0 12.3-4.9 22.3-14.8 29.5M163.3 147c0-45.6 37.1-82.6 82.6-82.6 45.6 0 82.6 37.1 82.6 82.6v51.1H163.3z" />
                    </svg>
                  <?php endif ?>
                  <span class="text"><?php echo t('LINEで開く') ?></span>
                </div>
              </a>
            <?php endif ?>
          </section>
        </div>
      </nav>

      <?php // シェア導線（nav の外・全幅）。統一サイズの円形アイコンボタンで、LINE/X はブランド色にして
            // 「どこへ共有するか」が一目で分かるようにする。モバイルは OS ネイティブ共有も出す。
            // 共有リンクの og:image は統計グラフ入りの動的カード(/oc/{id}/card)で展開される。 ?>
      <div class="oc-share">
        <span class="oc-share__label"><?php echo t('共有') ?></span>
        <button type="button" class="oc-share__btn oc-share__btn--sub" id="oc-share-native" hidden aria-label="<?php echo t('共有') ?>">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 16.1c-.8 0-1.5.3-2 .8l-7.1-4.2c0-.2.1-.5.1-.7s0-.5-.1-.7L16 7.2c.5.5 1.2.8 2 .8a3 3 0 1 0-3-3c0 .2 0 .5.1.7L8 9.8a3 3 0 1 0 0 4.4l7.1 4.2c0 .2-.1.4-.1.6a3 3 0 1 0 3-2.9z"/></svg>
        </button>
        <a class="oc-share__btn oc-share__btn--line" id="oc-share-line" href="https://social-plugins.line.me/lineit/share?url=<?php echo urlencode(url('oc', $oc['id'])) ?>" target="_blank" rel="noopener" aria-label="LINE">
          <img src="<?php echo fileUrl('assets/line.svg', urlRoot: '') ?>" alt="" width="44" height="44">
        </a>
        <a class="oc-share__btn oc-share__btn--x" id="oc-share-x" href="https://x.com/intent/post?text=<?php echo urlencode(htmlspecialchars_decode((string)$oc['name']) . "\n" . url('oc', $oc['id']) . "\n") ?>" target="_blank" rel="noopener" aria-label="X">
          <svg viewBox="0 0 240 240" aria-hidden="true"><path d="M88.2 60.66L169.46 178.81H151.42L70.16 60.66H88.2ZM92.93 51.66H53.04L146.68 187.81H186.57L92.93 51.66Z"/><path d="M132.54 109.25L182.24 51.66H170.99L127.55 101.99L132.54 109.25Z"/><path d="M105.36 127.72L53.04 188.34H64.3L110.35 134.98L105.36 127.72Z"/></svg>
        </a>
        <button type="button" class="oc-share__btn oc-share__btn--sub" id="oc-share-copy" aria-label="<?php echo t('リンクをコピー') ?>">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 1H4a2 2 0 0 0-2 2v14h2V3h12V1zm3 4H8a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2zm0 16H8V7h11v14z"/></svg>
        </button>
      </div>
      <div class="oc-share-toast" id="oc-share-toast" role="status" aria-live="polite"><?php echo t('コピーしました') ?></div>
      <style>
        .oc-share { display: flex; align-items: center; flex-wrap: wrap; gap: 12px; margin: var(--sp-section-gap) 1rem 0; }
        .oc-share__label { font-size: 13px; font-weight: 700; letter-spacing: .04em; color: var(--c-text-4, #8b95a7); }
        .oc-share__btn { width: 44px; height: 44px; flex: 0 0 44px; border-radius: 50%; overflow: hidden; display: inline-flex; align-items: center; justify-content: center; padding: 0; border: none; background: none; cursor: pointer; text-decoration: none; transition: transform .12s ease, filter .12s ease; }
        .oc-share__btn:hover { transform: translateY(-2px); filter: brightness(1.08); }
        .oc-share__btn:active { transform: translateY(0); }
        .oc-share__btn:focus-visible { outline: 2px solid var(--c-grad-blue-btn, #5a8cff); outline-offset: 2px; }
        .oc-share__btn svg { width: 22px; height: 22px; display: block; }
        .oc-share__btn--line { background: #06C755; }
        /* LINEアイコンは周囲に緑の余白を残す（円いっぱいに敷くと切り抜き感が強いため縮小して中央寄せ） */
        .oc-share__btn--line img { width: 68%; height: 68%; display: block; }
        /* X は真っ黒なのでダーク背景で溶ける。薄い枠で輪郭を出す */
        .oc-share__btn--x { background: #000; border: 1px solid rgba(255, 255, 255, .22); }
        .oc-share__btn--x svg { width: 20px; height: 20px; fill: #fff; }
        .oc-share__btn--sub { background: var(--c-surface-2, rgba(127, 127, 127, .14)); border: 1px solid var(--c-border, rgba(127, 127, 127, .24)); }
        .oc-share__btn--sub svg { fill: var(--c-text-3, #c2cad6); }
        .oc-share__btn[hidden] { display: none; }
        /* コピー完了の軽いトースト（すぐ消える） */
        .oc-share-toast { position: fixed; left: 50%; bottom: 28px; transform: translateX(-50%) translateY(10px); z-index: 9999; padding: 10px 18px; border-radius: 10px; background: rgba(22, 26, 42, .96); color: #fff; font-size: 14px; font-weight: 700; box-shadow: 0 8px 28px rgba(0, 0, 0, .38); opacity: 0; pointer-events: none; transition: opacity .18s ease, transform .18s ease; }
        .oc-share-toast.is-visible { opacity: 1; transform: translateX(-50%) translateY(0); }
        @media (prefers-reduced-motion: reduce) { .oc-share-toast { transition: opacity .18s ease; transform: translateX(-50%); } }
      </style>
      <script>
        (function () {
          var url = <?php echo json_encode(url('oc', $oc['id'])) ?>;
          var d = (window.dataLayer = window.dataLayer || []);
          var ocId = <?php echo (int)$oc['id'] ?>;
          var ocName = <?php echo json_encode((string)$oc['name'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
          // 共有ボタン押下を GA4(GTM dataLayer経由)で計測。method で LINE/X/コピー/OS共有 を区別
          function track(method) {
            try { d.push({ event: 'share', method: method, oc_id: ocId, oc_name: ocName }); } catch (e) {}
          }
          // 共有本文は「ページタイトル(サイト名入り)＋改行＋URL」で統一
          function shareText() { return document.title + '\n' + url; }

          var xBtn = document.getElementById('oc-share-x');
          if (xBtn) {
            // X はページタイトルを使い、URLの後にも改行を入れる（本文だけで組み立てる）
            xBtn.href = 'https://x.com/intent/post?text=' + encodeURIComponent(shareText() + '\n');
            xBtn.addEventListener('click', function () { track('x'); });
          }
          var lineBtn = document.getElementById('oc-share-line');
          if (lineBtn) { lineBtn.addEventListener('click', function () { track('line'); }); }

          var nativeBtn = document.getElementById('oc-share-native');
          if (navigator.share) {
            nativeBtn.hidden = false;
            nativeBtn.addEventListener('click', function () {
              track('native');
              navigator.share({ title: document.title, url: url }).catch(function () {});
            });
          }

          var toast = document.getElementById('oc-share-toast');
          var toastTimer;
          function showToast() {
            if (!toast) return;
            toast.classList.add('is-visible');
            clearTimeout(toastTimer);
            toastTimer = setTimeout(function () { toast.classList.remove('is-visible'); }, 1600);
          }
          var copyBtn = document.getElementById('oc-share-copy');
          copyBtn.addEventListener('click', function () {
            if (!navigator.clipboard) return;
            navigator.clipboard.writeText(shareText()).then(function () {
              track('copy');
              showToast();
            }).catch(function () {});
          });
        })();
      </script>
      <?php if (isset($_adminDto)) : ?>
        <?php viewComponent('oc_content_admin', compact('_adminDto')); ?>
      <?php endif ?>
      <?php // 分析はキャッシュ済み「データ」からリクエスト時にレンダリング（url()等はここで解決される）。未生成は空 ?>
      <?php if ($_narrative !== null): ?>
        <?php viewComponent('oc_narrative_section', ['narrative' => $_narrative]) ?>
      <?php endif ?>
      <?php // 変動データ(日付・人数・グラフ)のセクション全体をGoogle検索スニペットから除外 ?>
      <section class="openchat-graph-section" data-nosnippet style="padding-bottom: 0rem; padding-top: var(--sp-section-gap);">
        <div class="title-bar" style="margin-bottom: 1.5rem;">
          <img class="openchat-item-title-img" aria-hidden="true" alt="<?php echo $oc['name'] ?>" src="<?php echo imgPreviewUrl($oc['img_url']) ?>">
          <div style="display: flex; flex-direction: column; gap: 2px;">
            <h2 class="graph-title">
              <div><?php echo t('メンバー数推移') ?></div>
            </h2>
            <div class="title-bar-oc-name-wrapper">
              <div class="title-bar-oc-name"><?php if ($oc['emblem'] === 1) : ?><span class="super-icon sp"></span><?php elseif ($oc['emblem'] === 2) : ?><span class="super-icon official"></span><?php endif ?><?php echo $oc['name'] ?></div>
              <div class="title-bar-oc-member">(<?php echo formatMember($oc['member']) ?>)</div>
            </div>
          </div>
          <div style="margin-left: auto; display: flex; flex-direction: column; gap: 2px;">
            <?php if (isset($oc['api_created_at'])) : ?>
              <span class="number-box created-at">
                <div class="openchat-itme-stats-title"><?php echo t('ルーム開設') ?></div>
                <div class="openchat-itme-stats-title" style="margin-left: 4px;"><?php echo convertDatetime($oc['api_created_at'], format: 'Y/m/d') ?></div>
              </span>
            <?php endif ?>
            <span class="number-box created-at registed">
              <div class="openchat-itme-stats-title"><?php echo t('登録') ?></div>
              <div class="openchat-itme-stats-title" style="margin-left: 4px;"><?php echo convertDatetime($oc['created_at'], format: 'Y/m/d') ?></div>
            </span>
          </div>
        </div>

        <div style="position: relative; margin: auto; padding-bottom: 1rem; transition: all 0.3s ease 0s; opacity: 0" id="graph-box">
          <div class="chart-canvas-box" id="dummy-canvas"></div>
          <div id="app" style="<?php if (!is_int($oc['api_created_at'])) echo 'min-height: 0px;' ?>"></div>
        </div>
        <script type="application/json" id="chart-arg">
          <?php echo json_encode($_chartArgDto, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>
        </script>
        <?php // グラフ初回ロードのタブ/ボタン出し分け「可用性メタ」を事前計算済みなら埋め込む（null=未生成→フロントは meta=1 でライブ計算） ?>
        <script type="application/json" id="chart-meta"><?php echo json_encode($_chartMeta ?? null, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?></script>
        <?php // 統計データ(#stats-dto)はサーバー注入をやめ、graph(React)が /oc/{id}/stats から初回も非同期取得する ?>
        <script async type="module" crossorigin src="/<?php echo getFilePath('js/oc-app', 'graph-*.js') ?>"></script>
      </section>
    </article>

    <?php if ($enableAdsense): ?>
      <?php // ocTopWide2(手動横長)は撤去済み: impRPM¥14/CTR0.21%で「関連ルーム」棚への回遊を遮るだけだった(2026-06実測)。gTag は自動広告に必要なので維持 ?>
      <?php GAd::gTag() ?>
    <?php endif ?>

    <?php // 関連ルーム(類似サイズ/おすすめ)は recommend 静的キャッシュ(.dat)から都度組み立て（MySQL不使用） ?>
    <?php viewComponent('oc_recommend_aside', ['similarSize' => $_similarSize, 'recommend' => $_recommend, 'oc' => $oc]) ?>


    <?php if (MimimalCmsConfig::$urlRoot === ''): // TODO:日本以外ではコメントが無効
    ?>
      <section class="comment-section" style="padding-top: var(--sp-section-gap); padding-bottom: 12px;" id="comment-section">
        <div style="display: flex; flex-direction: row; align-items: center; gap: 6px; margin-bottom: -2px;">
          <img class="openchat-item-title-img" aria-hidden="true" alt="<?php echo $oc['name'] ?>" src="<?php echo imgPreviewUrl($oc['img_url']) ?>">
          <div style="display: flex; flex-direction: column; gap: 2px;">
            <h2 class="graph-title">
              <div>オープンチャットについてのコメント</div>
            </h2>
            <div class="title-bar-oc-name-wrapper" style="padding-right: 1.5rem;">
              <div class="title-bar-oc-name"><?php if ($oc['emblem'] === 1) : ?><span class="super-icon sp"></span><?php elseif ($oc['emblem'] === 2) : ?><span class="super-icon official"></span><?php endif ?><?php echo $oc['name'] ?></div>
              <div class="title-bar-oc-member">(<?php echo formatMember($oc['member']) ?>)</div>
            </div>
          </div>
        </div>
        <script type="application/json" id="comment-app-init-dto">
          <?php echo json_encode($_commentArgDto, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>
        </script>
        <div id="comment-root"></div>
      </section>
    <?php endif ?>

    <?php if ($enableAdsense): ?>
      <?php // コメントの下・フッター直前にOC横長1枠（固定。高さ確保済みでCLSなし）。
            // 広告ブロック検出(ad_guard)はページに ins.adsbygoogle が1つも無いと動作しないため、その維持も兼ねる ?>
      <?php GAd::output('ocTopHorizontal') ?>
    <?php endif ?>
    <?php viewComponent('footer_inner') ?>

  </div>
  <?php \App\Views\Ads\GoogleAdsense::loadAdsTag() ?>
  <script async>
    (function() {
      const isCollapsed = <?php echo $formatedDescription !== $formatedRowDescription ? 'true' : 'false' ?>

      const readMoreBtn = document.getElementById('read_more_btn')
      const talkroomDesc = document.getElementById('talkroom-description')
      const talkroomDescBox = document.getElementById('talkroom_description_box')

      const closeId = 'talkroom-description-close-btn'

      if (talkroomDesc.offsetHeight >= talkroomDesc.scrollHeight && !isCollapsed) {
        talkroomDescBox.classList.add('hidden')
      } else {
        const open = document.getElementById(closeId)
        const close = document.getElementById('talkroom-description-close-btn')
        const description = document.getElementById('talkroom-description-btn')

        const openAndfetchDescription = () => {
          if (talkroomDescBox.classList.contains('close')) {
            talkroomDescBox.classList.remove('close')
            description.textContent = (<?php echo json_encode([htmlspecialchars_decode($formatedRowDescription)], flags: JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)[0]
          }
        }

        readMoreBtn.style.visibility = "visible"
        talkroomDesc.addEventListener('click', (e) => e.target.id !== closeId && openAndfetchDescription())
        readMoreBtn.addEventListener('click', (e) => e.target.id !== closeId && openAndfetchDescription())

        close.addEventListener('click', () => {
          talkroomDescBox.classList.add('close')
          description.textContent = (<?php echo json_encode([htmlspecialchars_decode($formatedDescription)], flags: JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)[0]
          window.scrollTo({
            top: 0,
          });
        })
      }
    })();
  </script>

  <?php if (MimimalCmsConfig::$urlRoot === ''): // TODO:日本以外ではコメントが無効 
  ?>
    <link rel="stylesheet" crossorigin href="/<?php echo getFilePath('js/oc-app', 'comments-*.css') ?>">
    <script defer type="module" crossorigin src="/<?php echo getFilePath('js/oc-app', 'comments-*.js') ?>"></script>
  <?php endif ?>

  <script src="<?php echo fileUrl("/js/site_header_footer.js", urlRoot: '') ?>"></script>

  <?php if ($enableAdsense): ?>
    <?php viewComponent('ad_guard') ?>
  <?php endif ?>

  <?php if (MimimalCmsConfig::$urlRoot === ''): // TODO:日本以外ではマイリストが無効
  ?>
    <script type="module">
      import {
        JsonCookie
      } from '<?php echo fileUrl('/js/JsonCookie.js', urlRoot: '') ?>'

      const OPEN_CHAT_ID = <?php echo $oc['id'] ?>;
      const LIST_LIMIT_MY_LIST = <?php echo AppConfig::LIST_LIMIT_MY_LIST ?>;

      const myListCheckbox = document.getElementById('my-list-checkbox')
      const myListJsonCookie = new JsonCookie('myList')

      if (myListCheckbox && myListJsonCookie.get(OPEN_CHAT_ID))
        myListCheckbox.checked = true

      myListCheckbox && myListCheckbox.addEventListener('change', () => {
        const listLen = (Object.keys(myListJsonCookie.get() || {}).length)

        if (!myListCheckbox.checked) {
          // チェック解除で削除する場合
          if (listLen <= 2) {
            myListJsonCookie.remove()
          } else {
            const expiresTimestamp = myListJsonCookie.remove(OPEN_CHAT_ID)
            myListJsonCookie.set('expires', expiresTimestamp)
          }
          return
        }

        if (listLen > LIST_LIMIT_MY_LIST) {
          // リストの上限数を超えている場合
          const label = document.querySelector('.my-list-form label span')
          label.textContent = 'ピン留めの最大数を超えました。'
          label.style.color = 'Red'
          return
        }

        // リストに追加する場合
        const expiresTimestamp = myListJsonCookie.set(OPEN_CHAT_ID, 1)
        myListJsonCookie.set('expires', expiresTimestamp)
      })
    </script>
  <?php endif ?>

  <?php echo $_breadcrumbsShema ?>
</body>

</html>