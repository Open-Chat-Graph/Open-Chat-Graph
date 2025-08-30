<!DOCTYPE html>
<html lang="<?php echo t('ja') ?>">
<?php

use App\Config\AppConfig;
use App\Views\Ads\GoogleAdsense as GAd;

function ad(bool $show = true)
{
  if (!$show) return;

?>
  <div style="margin: -24px 0;">
    <?php GAd::output(GAd::AD_SLOTS['siteSeparatorResponsive']) ?>
  </div>
<?php

}

viewComponent('oc_head', compact('_css', '_meta') + ['dataOverlays' => 'bottom']); ?>

<body>
  <?php viewComponent('site_header') ?>
  <div class="unset openchat body" style="overflow: hidden;">
    <?php \App\Views\Ads\GoogleAdsense::gTag('bottom') ?>
    <article class="unset" style="display: block;">
      <section style="display: block;">
        <h2 style="margin: 1rem; text-align: center;">⚠️参加前にお読みください</h2>
        <hr class="hr-bottom" style="margin: 1rem 0; width: 100%; ">
        <h3 style="margin: 1rem; text-align: center;">参加するオープンチャットの確認</h3>
        <div style="margin: 1rem 2rem;">
          <img class="talkroom_banner_img" style="aspect-ratio: 1.8; border-radius: 0;" alt="<?php echo $oc['name'] ?>" src="<?php echo $oc['img_url'] ? imgUrl($oc['id'], $oc['img_url']) : lineImgUrl($oc['api_img_url']) ?>">
        </div>
        <div class="openchat-header-right" style="margin: 1rem 2rem; padding: 0;">
          <h1 class="talkroom_link_h1 unset" style="text-align: center;"><?php if ($oc['emblem'] === 1) : ?><span class="super-icon sp"></span><?php elseif ($oc['emblem'] === 2) : ?><span class="super-icon official"></span><?php endif ?><?php echo $oc['name'] ?></h1>
          <div style="text-align: center;">
            <span class="number_of_members" style="color: #111; font-weight: normal;"><?php echo sprintfT('メンバー %s人', number_format($oc['member'])) ?></span>
          </div>
          <div class="talkroom_description_box" id="talkroom_description_box">
            <p class="talkroom_description" id="talkroom-description">
              <span id="talkroom-description-btn"><?php echo trim(preg_replace("/(\r\n){3,}|\r{3,}|\n{3,}/", "\n\n", $oc['description'])) ?></span>
            </p>
          </div>
        </div>
      </section>
      <hr class="hr-bottom" style="margin: 1rem 0; width: 100%; ">
      <section style="margin: 1rem 0; width: 100%;">
        <h3 style="margin: 1rem; text-align: center;">オープンチャットの禁止事項</h3>
        <span style="text-align: center; display: block; margin: 1rem; font-size: 16px;">以下の各禁止事項にチェックを入れてください。</span>
        <span style="text-align: center; display: block; margin: 1rem; font-size: 16px;">全項目をチェックすると、最後までスクロールした場所にある「LINEで開く」ボタンが有効になります。</span>
        <img src="<?php echo fileUrl('assets/line-guilde/line-guilde.webp') ?>" alt="オープンチャット禁止事項" style=" max-width: 100%; height: auto; display:">
        <div style="margin: 1rem 2rem; display: flex; align-items: center;">
          <input type="checkbox" id="check-general" style="width: 20px; height: 20px; margin-right: 10px;">
          <label for="check-general" style="font-size: 15px; cursor: pointer;">オープンチャットの利用規約について確認しました</label>
        </div>
        <hr class="hr-bottom" style="margin: 1rem 0; width: 100%; ">
        <?php ad() ?>
        <h3 style="margin: 1rem; text-align: center;">「出会いを求める投稿」の禁止</h3>
        <img src="<?php echo fileUrl('assets/line-guilde/contents_istribution_images_image_a.webp') ?>" alt="オープンチャット禁止事項" style=" max-width: 100%; height: auto; display:">
        <div style="margin: 1rem 2rem; display: flex; align-items: center;">
          <input type="checkbox" id="check-dating" style="width: 20px; height: 20px; margin-right: 10px;">
          <label for="check-dating" style="font-size: 15px; cursor: pointer;">出会いを求める投稿の禁止について確認しました</label>
        </div>
        <hr class="hr-bottom" style="margin: 1rem 0; width: 100%; ">
        <?php ad() ?>
        <h3 style="margin: 1rem; text-align: center;">「個人情報投稿」の禁止</h3>
        <img src="<?php echo fileUrl('assets/line-guilde/contents_istribution_images_image_b.webp') ?>" alt="オープンチャット禁止事項" style=" max-width: 100%; height: auto; display:">
        <div style="margin: 1rem 2rem; display: flex; align-items: center;">
          <input type="checkbox" id="check-privacy" style="width: 20px; height: 20px; margin-right: 10px;">
          <label for="check-privacy" style="font-size: 15px; cursor: pointer;">個人情報投稿の禁止について確認しました</label>
        </div>
        <hr class="hr-bottom" style="margin: 1rem 0; width: 100%; ">
        <?php ad() ?>
        <h3 style="margin: 1rem; text-align: center;">「未成年の不健全な出会いや集まりの計画・勧誘」の禁止</h3>
        <img src="<?php echo fileUrl('assets/line-guilde/contents_istribution_images_image_c.webp') ?>" alt="オープンチャット禁止事項" style=" max-width: 100%; height: auto; display:">
        <div style="margin: 1rem 2rem; display: flex; align-items: center;">
          <input type="checkbox" id="check-minors" style="width: 20px; height: 20px; margin-right: 10px;">
          <label for="check-minors" style="font-size: 15px; cursor: pointer;">未成年の不健全な出会いや集まりの禁止について確認しました</label>
        </div>
        <hr class="hr-bottom" style="margin: 1rem 0; width: 100%; ">
        <?php ad() ?>
        <h3 style="margin: 1rem; text-align: center;">「人が傷つく・不快に思う可能性がある投稿」の禁止</h3>
        <img src="<?php echo fileUrl('assets/line-guilde/contents_istribution_images_image_d.webp') ?>" alt="オープンチャット禁止事項" style=" max-width: 100%; height: auto; display:">
        <div style="margin: 1rem 2rem; display: flex; align-items: center;">
          <input type="checkbox" id="check-harmful" style="width: 20px; height: 20px; margin-right: 10px;">
          <label for="check-harmful" style="font-size: 15px; cursor: pointer;">人が傷つく・不快に思う投稿の禁止について確認しました</label>
        </div>
        <hr class="hr-bottom" style="margin: 1rem 0; width: 100%; ">
        <?php ad() ?>
        <h3 style="margin: 1rem; text-align: center;">「著作権や肖像権・プライバシーを侵害する行為」の禁止</h3>
        <img src="<?php echo fileUrl('assets/line-guilde/contents_istribution_images_image_e.webp') ?>" alt="オープンチャット禁止事項" style=" max-width: 100%; height: auto; display:">
        <div style="margin: 1rem 2rem; display: flex; align-items: center;">
          <input type="checkbox" id="check-copyright" style="width: 20px; height: 20px; margin-right: 10px;">
          <label for="check-copyright" style="font-size: 15px; cursor: pointer;">著作権や肖像権・プライバシー侵害の禁止について確認しました</label>
        </div>
        <hr class="hr-bottom" style="margin: 1rem 0; width: 100%; ">
        <?php ad() ?>
        <h3 style="margin: 1rem; text-align: center;">通報について</h3>
        <img src="<?php echo fileUrl('assets/line-guilde/contents_istribution_images_image_f.webp') ?>" alt="オープンチャット禁止事項" style=" max-width: 100%; height: auto; display:">
        <div style="margin: 1rem 2rem; display: flex; align-items: center;">
          <input type="checkbox" id="check-report" style="width: 20px; height: 20px; margin-right: 10px;">
          <label for="check-report" style="font-size: 15px; cursor: pointer;">通報機能について確認しました</label>
        </div>
      </section style="margin: 1rem 0; width: 100%;">
      <hr class="hr-bottom" style="margin: 1rem 0; width: 100%;">
      <span style="text-align: center; display: block; margin: 1rem; font-size: 13px;">オープンチャットは24時間365日モニタリングを実施しています。規約違反があった場合、トークルーム・投稿の削除、オープンチャットの利用停止、さらにLINEアプリ自体の利用停止措置を行う場合があります。</span>
      <hr class="hr-bottom" style="margin: 1rem 0; width: 100%;">
      <div style="display: flex; flex-direction: row; align-items: center; gap: 6px; margin: 1rem;">
        <img class="openchat-item-title-img" aria-hidden="true" alt="<?php echo $oc['name'] ?>" src="<?php echo $oc['img_url'] ? imgPreviewUrl($oc['id'], $oc['img_url']) : linePreviewUrl($oc['api_img_url']) ?>">
        <div style="display: flex; flex-direction: column; gap: 2px;">
          <div class="title-bar-oc-name-wrapper" style="padding-right: 1.5rem;">
            <div class="title-bar-oc-name" style="color: #111; font-size: 13px; font-weight: bold;"><?php if ($oc['emblem'] === 1) : ?><span class="super-icon sp"></span><?php elseif ($oc['emblem'] === 2) : ?><span class="super-icon official"></span><?php endif ?><?php echo $oc['name'] ?></div>
            <div class="title-bar-oc-member" style="color: #111; font-size: 13px; font-weight: bold;">(<?php echo formatMember($oc['member']) ?>)</div>
          </div>
        </div>
      </div>
      <?php if ($oc['url']) : ?>
        <div id="checkbox-warning" style="color: red; font-size: 14px; text-align: center; margin: 0 1rem; font-weight: bold;">
          ※ 全ての確認項目にチェックを入れてください
        </div>
        <a href="<?php echo AppConfig::LINE_APP_URL . $oc['url'] . AppConfig::LINE_APP_SUFFIX ?>" id="line-open-button" class="openchat_link" style="font-size: 16px; padding: 20px 20px; border-radius: 8px; width: calc(100% - 2rem) !important; margin: 1rem; opacity: 0.5; pointer-events: none;">
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
    </article>
    <?php viewComponent('footer_inner') ?>
  </div>
  <?php \App\Views\Ads\GoogleAdsense::loadAdsTag() ?>
  <script>
    const admin = <?php echo isAdmin() ? 1 : 0; ?>;
  </script>
  <script src="<?php echo fileUrl("/js/site_header_footer.js", urlRoot: '') ?>"></script>
  <script defer src="<?php echo fileurl("/js/security.js", urlRoot: '') ?>"></script>
  <script>
    // Check the status of checkboxes and toggle button enable/disable
    function checkAllCheckboxes() {
      const checkboxes = [
        'check-general',
        'check-dating',
        'check-privacy',
        'check-minors',
        'check-harmful',
        'check-copyright',
        'check-report'
      ];

      const allChecked = checkboxes.every(id => {
        const checkbox = document.getElementById(id);
        return checkbox && checkbox.checked;
      });

      const button = document.getElementById('line-open-button');
      const warning = document.getElementById('checkbox-warning');

      if (button) {
        if (allChecked) {
          // Enable button when all checkboxes are checked
          button.style.opacity = '1';
          button.style.pointerEvents = 'auto';
          if (warning) {
            warning.style.display = 'none';
          }
        } else {
          // Disable button when checkboxes are incomplete
          button.style.opacity = '0.5';
          button.style.pointerEvents = 'none';
          if (warning) {
            warning.style.display = 'block';
          }
        }
      }
    }

    // Execute on page load and checkbox changes
    document.addEventListener('DOMContentLoaded', function() {
      // Add event listeners to each checkbox
      const checkboxIds = [
        'check-general',
        'check-dating',
        'check-privacy',
        'check-minors',
        'check-harmful',
        'check-copyright',
        'check-report'
      ];

      checkboxIds.forEach(id => {
        const checkbox = document.getElementById(id);
        if (checkbox) {
          checkbox.addEventListener('change', checkAllCheckboxes);
        }
      });

      // Check initial state
      checkAllCheckboxes();
    });
  </script>
</body>

</html>