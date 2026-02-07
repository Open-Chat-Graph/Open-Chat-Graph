<!DOCTYPE html>
<html lang="<?php echo t('ja') ?>">
<?php

use App\Config\AppConfig;
use App\Services\Recommend\TagDefinition\Ja\RecommendUtility;
use App\Views\Ads\GoogleAdsense as GAd;
use Shared\MimimalCmsConfig;

$enableAdsense = MimimalCmsConfig::$urlRoot === ''; // 日本語版のみ広告表示

viewComponent('oc_head', compact('_css', '_meta', '_schema') + ['dataOverlays' => 'bottom']); ?>

<body>
  <?php viewComponent('site_header') ?>
  <?php if ($enableAdsense): ?>
    <?php \App\Views\Ads\GoogleAdsense::gTag() ?>
    <?php GAd::output(GAd::AD_SLOTS['ocTopRectangle'], true) ?>
  <?php endif ?>

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
            <a class="link-mark" style="text-decoration: none; width: fit-content;" title="<?php echo $oc['name'] ?>" rel="external" target="_blank" href="<?php echo AppConfig::LINE_OPEN_URL[MimimalCmsConfig::$urlRoot] . $oc['emid'] . AppConfig::LINE_OPEN_URL_SUFFIX ?>"><span class="link-title" style="background: unset; color: #b7b7b7; -webkit-text-fill-color: unset; font-weight: normal; line-height: 125%; margin-bottom: -1px;"><!-- <span aria-hidden="true" style="font-size: 10px; margin-right:2px;">🔗</span> --><?php echo t('LINEオープンチャット') ?></span></a>
          </div>

          <div class="talkroom_description_box close" id="talkroom_description_box">
            <p class="talkroom_description" id="talkroom-description">
              <span id="talkroom-description-btn"><?php echo $formatedDescription ?></span>
            </p>
            <button id="talkroom-description-close-btn" class="close-btn" title="<?php echo t('一部を表示') ?>"><?php echo t('一部を表示') ?></button>
            <div class="more" id="read_more_btn">
              <div class="more-separater">&nbsp;</div>
              <button class="unset more-text" style="font-weight: bold; color: #111;" title="<?php echo t('すべて見る') ?>">…<?php echo t('すべて見る') ?></button>
            </div>
          </div>

          <div class="talkroom_number_of_members">
            <span class="number_of_members"><?php echo sprintfT('メンバー %s人', number_format($oc['member'])) ?></span>
          </div>

          <?php if (isset($_hourlyRange)) : ?>
            <div class="talkroom_number_of_stats" style="line-height: 135%; margin-top: 3px;">
              <div class="number-box ">
                <span aria-hidden="true" style="margin-right: 1px; font-size: 9px; user-select: none;">🔥</span>
                <span style="margin-right: 4px;" class="openchat-itme-stats-title"><?php echo $_hourlyRange ?></span>
                <div>
                  <span class="openchat-item-stats"><?php echo sprintfT('%s人', signedNumF($oc['rh_diff_member'])) ?></span><span class="openchat-item-stats percent">(<?php echo signedNum(signedCeil($oc['rh_percent_increase'] * 10) / 10) ?>%)</span>
                </div>
              </div>
            </div>
          <?php endif ?>

          <div class="talkroom_number_of_stats">

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

            <?php if (isset($oc['diff_member2']) && $oc['diff_member2'] >= AppConfig::RECOMMEND_MIN_MEMBER_DIFF_H24) : ?>
              <div class="number-box " style="margin-right: 6px;">
                <svg class="MuiSvgIcon-root MuiSvgIcon-fontSizeMedium show-north css-162gv95" focusable="false" aria-hidden="true" viewBox="0 0 24 24" data-testid="NorthIcon">
                  <path d="m5 9 1.41 1.41L11 5.83V22h2V5.83l4.59 4.59L19 9l-7-7-7 7z"></path>
                </svg>
                <span class="openchat-itme-stats-title"><?php echo t('1週間') ?></span>
                <div>
                  <span class="openchat-item-stats"><?php echo sprintfT('%s人', signedNumF($oc['diff_member2'])) ?></span><span class="openchat-item-stats percent">(<?php echo signedNum(signedCeil($oc['percent_increase2'] * 10) / 10) ?>%)</span>
                </div>
              </div>
            <?php elseif (isset($oc['diff_member2'])) : ?>
              <div class="number-box" style="margin-right: 6px;">
                <span class="openchat-itme-stats-title"><?php echo t('1週間') ?></span>
                <?php if (($oc['diff_member2'] ?? 0) !== 0) : ?>
                  <div>
                    <span class="openchat-item-stats"><?php echo sprintfT('%s人', signedNumF($oc['diff_member2'])) ?></span><span class="openchat-item-stats percent">(<?php echo signedNum(signedCeil($oc['percent_increase2'] * 10) / 10) ?>%)</span>
                  </div>
                <?php elseif ($oc['diff_member2'] === 0) : ?>
                  <span class="zero-stats">±0</span>
                <?php endif ?>
              </div>
            <?php endif ?>

          </div>
        </div>

      </section>

      <hr class="hr-top" style="margin-bottom: 8px;">

      <nav style="margin: 0 1rem; padding: 8px 0 10px 0; border: unset;" class="oc-desc-nav">
        <aside class="oc-desc-nav-category" style="display: flex; align-items:center; min-width: calc(50% - 1rem);">
          <span class="openchat-list-date" style="flex-direction: row; height: fit-content; flex-wrap: nowrap; color: #111; margin: 0 auto;">
            <div style="display: flex; flex-direction: column; justify-content: flex-start; gap: 8px; line-height: 1.5; height: 100%; word-break: keep-all; font-weight: bold; align-items: center;">
              <?php if (is_int($oc['api_created_at'])) : ?>
                <div><?php echo t('カテゴリー') ?>&nbsp;</div>
              <?php endif ?>
              <?php if (isset($recommend[2]) && $recommend[2] && MimimalCmsConfig::$urlRoot === '') : ?>
                <div>タグ&nbsp;</div>
              <?php endif ?>
            </div>
            <div style="display: flex; flex-direction: column; justify-content: space-between; gap: 8px; line-height: 1.5; height: 100%">
              <?php if (is_int($oc['api_created_at'])) : ?>
                <a href="<?php echo url('ranking' . ($oc['category'] ? ('/' . $oc['category']) : '')) ?>" style="width:fit-content; color:inherit; text-wrap: wrap;"><?php echo $category ?></a>
              <?php endif ?>
              <?php if (isset($recommend[2]) && $recommend[2] && MimimalCmsConfig::$urlRoot === '') : ?>
                <a href="<?php echo url('recommend/' . urlencode(htmlspecialchars_decode($recommend[2]))) ?>" style="width:fit-content; color:inherit; text-wrap: wrap;"><?php echo $recommend[2] ?></a>
              <?php endif ?>
            </div>
          </span>
        </aside>

        <div style="display: flex; flex-direction: column; width: 100%; margin: auto 0;">
          <section class="open-btn sp-btn" style="width: 100%; margin: 0; padding: 0;">
            <?php if ($oc['url']) : ?>
              <a href="<?php echo MimimalCmsConfig::$urlRoot === '' ? url('oc', $oc['id'], 'jump') : lineAppUrl($oc) ?>" class="openchat_link" style="font-size: 16px;">
                <div style="display: flex; align-items: center; justify-content: center;">
                  <?php if ($oc['join_method_type'] !== 0) : ?>
                    <svg style="height: 12px; fill: white; margin-right: 3px;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 489.4 489.4" xml:space="preserve">
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
      <?php if (isset($_adminDto)) : ?>
        <?php viewComponent('oc_content_admin', compact('_adminDto')); ?>
      <?php endif ?>
      <hr class="hr-top" style="margin-bottom: 8px;">
      <section class="openchat-graph-section" style="padding-bottom: 0rem; padding-top: 0.5rem;">
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
        <script type="application/json" id="stats-dto">
          <?php echo json_encode($_statsDto, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>
        </script>
        <script async type="module" crossorigin src="/<?php echo getFilePath('js/chart', 'index-*.js') ?>"></script>
      </section>
    </article>

    <?php if ($enableAdsense): ?>
      <?php GAd::output(GAd::AD_SLOTS['ocTopWide2'], true) ?>
    <?php endif ?>

    <?php if ($recommend[0] || $recommend[3]) : ?>
      <aside class="recommend-list-aside" id="recommend-list-aside1">
        <?php $recommendDto1 = $recommend[0] ?: $recommend[3] ?>
        <?php viewComponent('recommend_list2', ['recommend' => $recommendDto1, 'member' => $oc['member'], 'tag' => $recommend[2], 'id' => $oc['id'], 'showTags' => true, 'disableGAd' => true]) ?>
      </aside>
    <?php endif ?>

    <hr class="hr-top" style="margin-bottom: 8px;">

    <?php if (MimimalCmsConfig::$urlRoot === ''): // TODO:日本以外ではコメントが無効 
    ?>
      <section class="comment-section" style="padding-top: 12px; padding-bottom: 12px;" id="comment-section">
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
      <?php GAd::output(GAd::AD_SLOTS['ocSeparatorResponsive'], true) ?>
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
    <script defer type="module" crossorigin src="/<?php echo getFilePath('js/comment', 'index-*.js') ?>"></script>
  <?php endif ?>

  <script src="<?php echo fileUrl("/js/site_header_footer.js", urlRoot: '') ?>"></script>

  <?php if ($enableAdsense): ?>
    <script defer src="<?php echo fileurl("/js/security.js", urlRoot: '') ?>"></script>
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