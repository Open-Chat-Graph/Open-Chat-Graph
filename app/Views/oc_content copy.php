<!DOCTYPE html>
<html lang="<?php echo t('ja') ?>">
<?php

use App\Config\AppConfig;
use App\Services\Recommend\TagDefinition\Ja\RecommendUtility;
use App\Views\Ads\GoogleAdsense as GAd;
use Shared\MimimalCmsConfig;

viewComponent('oc_head', compact('_css', '_meta', '_schema', '_chartArgDto', '_commentArgDto') + ['dataOverlays' => 'bottom']); ?>

<body>
  <!-- 固定ヘッダー -->
  <?php viewComponent('site_header') ?>
  <div class="unset openchat body" style="overflow: hidden;">
    <?php \App\Views\Ads\GoogleAdsense::gTag('bottom') ?>

    <article class="unset" style="display: block;">
      <!-- オープンチャット表示ヘッダー -->
      <section class="openchat-header unset" style="padding: 10px 1rem 8px 1rem;">
        <div class="talkroom_banner_img_area">
          <img class="talkroom_banner_img" aria-hidden="true" alt="<?php echo $oc['name'] ?>" src="<?php echo $oc['img_url'] ? imgUrl($oc['id'], $oc['img_url']) : lineImgUrl($oc['api_img_url']) ?>">
        </div>

        <div class="openchat-header-right">
          <div>
            <a title="<?php echo $oc['name'] ?>" rel="external" target="_blank" href="<?php echo AppConfig::LINE_OPEN_URL[MimimalCmsConfig::$urlRoot] . $oc['emid'] . AppConfig::LINE_OPEN_URL_SUFFIX ?>" class="h1-link unset">
              <h1 class="talkroom_link_h1 unset"><?php if ($oc['emblem'] === 1) : ?><span class="super-icon sp"></span><?php elseif ($oc['emblem'] === 2) : ?><span class="super-icon official"></span><?php endif ?><?php echo $oc['name'] ?></h1>
            </a>
            <div class="link-mark"><span class="link-title" style="background: unset; color: #b7b7b7; -webkit-text-fill-color: unset; font-weight: normal; line-height: 125%; margin-bottom: -1px;"><?php echo t('LINEオープンチャット') ?></span></div>
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
        </div>
      </section>

      <?php GAd::output(GAd::AD_SLOTS['ocSeparatorResponsive']) ?>

      <?php viewComponent('footer_inner') ?>
  </div>
  <?php \App\Views\Ads\GoogleAdsense::loadAdsTag() ?>

  <script>
    const admin = <?php echo isAdmin() ? 1 : 0; ?>;
  </script>
  <script src="<?php echo fileUrl("/js/site_header_footer.js", urlRoot: '') ?>"></script>

  <?php if (RecommendUtility::isAdEnhancementTag($recommend[2] ?? '')): ?>
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
    <?php if (AppConfig::$enableCloudflare): ?>
      <script type="module">
        import {
          getComment
        } from '<?php echo fileUrl('/js/fetchComment.js', urlRoot: '') ?>'

        getComment(0, '<?php echo MimimalCmsConfig::$urlRoot ?>')
      </script>
    <?php else: ?>
      <script type="module">
        import {
          applyTimeElapsedString
        } from '<?php echo fileUrl('/js/fetchComment.js') ?>'

        applyTimeElapsedString()
      </script>
    <?php endif ?>
  <?php endif ?>

  <?php echo $_breadcrumbsShema ?>
</body>

</html>