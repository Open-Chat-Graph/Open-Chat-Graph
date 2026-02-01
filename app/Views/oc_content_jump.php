<!DOCTYPE html>
<html lang="<?php echo t('ja') ?>">
<?php

use App\Views\Ads\GoogleAdsense as GAd;

$_css[] = 'oc-jump';
viewComponent('oc_head', compact('_css', '_meta') + ['dataOverlays' => 'bottom']); ?>

<body>
  <style>
    .responsive-google-parent {
      padding: 0;
    }
  </style>
  <?php viewComponent('site_header') ?>
  <?php \App\Views\Ads\GoogleAdsense::gTag('bottom') ?>
  <?php GAd::output(GAd::AD_SLOTS['siteTopRectangle'], true) ?>
  <div class="unset openchat body" style="overflow: hidden; max-width: 600px;">
    <article class="unset" style="display: block;">
      <section class="oc-jump-section oc-info-section">
        <h2 class="oc-jump-main-title">⚠️参加前の確認</h2>
        <span class="oc-jump-instruction">以下の説明文をご確認ください。</span>
        <div class="oc-jump-image-wrapper">
          <img class="talkroom_banner_img" style="aspect-ratio: 1.8; border-radius: 0;"
            alt="<?php echo $oc['name'] ?>"
            src="<?php echo imgUrl($oc['img_url']) ?>">
        </div>
        <div class="oc-jump-info-content">
          <h1 class="talkroom_link_h1 unset" style="text-align: center; white-space: normal;">
            <?php if ($oc['emblem'] === 1) : ?><span
                class="super-icon sp"></span><?php elseif ($oc['emblem'] === 2) : ?><span
                class="super-icon official"></span><?php endif ?><?php echo $oc['name'] ?></h1>
          <div style="text-align: center;">
            <span class="number_of_members"
              style="color: #111; font-weight: normal;"><?php echo sprintfT('メンバー %s人', number_format($oc['member'])) ?></span>
          </div>
          <div class="talkroom_description_box" id="talkroom_description_box">
            <p class="talkroom_description" id="talkroom-description">
              <span
                id="talkroom-description-btn"><?php echo trim(preg_replace("/(\r\n){3,}|\r{3,}|\n{3,}/", "\n\n", $oc['description'])) ?></span>
            </p>
          </div>
        </div>
      </section>
      <?php GAd::output(GAd::AD_SLOTS['siteSeparatorWide'], true) ?>
      <section class="oc-jump-section oc-rules-section">
        <div class="oc-rule-item">
          <h3 class="oc-jump-section-title">オープンチャットの禁止事項</h3>
          <span class="oc-jump-instruction">以下の禁止事項をご確認後、「LINEで開く」を押してください。</span>
          <img src="<?php echo fileUrl('assets/line-guilde/line-guilde.webp') ?>" alt="オープンチャット禁止事項"
            class="oc-jump-rule-image">
        </div>
        <div style="display: flex; flex-direction: row; align-items: center; gap: 6px; margin: 1rem;">
          <img class="openchat-item-title-img" aria-hidden="true" alt="<?php echo $oc['name'] ?>" src="<?php echo imgPreviewUrl($oc['img_url']) ?>">
          <div style="display: flex; flex-direction: column; gap: 2px;">
            <div class="title-bar-oc-name-wrapper" style="padding-right: 1.5rem;">
              <div class="title-bar-oc-name" style="color: #111; font-size: 12px;"><?php if ($oc['emblem'] === 1) : ?><span class="super-icon sp"></span><?php elseif ($oc['emblem'] === 2) : ?><span class="super-icon official"></span><?php endif ?><?php echo $oc['name'] ?></div>
              <div class="title-bar-oc-member" style="color: #111; font-size: 12px;">(<?php echo formatMember($oc['member']) ?>)</div>
            </div>
          </div>
        </div>
        <?php if ($oc['url']) : ?>
          <a href="<?php echo lineAppUrl($oc) ?>" id="line-open-button" class="oc-jump-line-button openchat_link" style="max-width: 100%;">
            <div class="oc-jump-line-button-content">
              <?php if ($oc['join_method_type'] !== 0) : ?>
                <svg style="height: 12px; fill: white; margin-right: 3px;" xmlns="http://www.w3.org/2000/svg"
                  viewBox="0 0 489.4 489.4" xml:space="preserve">
                  <path
                    d="M99 147v51.1h-3.4c-21.4 0-38.8 17.4-38.8 38.8v213.7c0 21.4 17.4 38.8 38.8 38.8h298.2c21.4 0 38.8-17.4 38.8-38.8V236.8c0-21.4-17.4-38.8-38.8-38.8h-1v-51.1C392.8 65.9 326.9 0 245.9 0 164.9.1 99 66 99 147m168.7 206.2c-3 2.2-3.8 4.3-3.8 7.8.1 15.7.1 31.3.1 47 .3 6.5-3 12.9-8.8 15.8-13.7 7-27.4-2.8-27.4-15.8v-.1c0-15.7 0-31.4.1-47.1 0-3.2-.7-5.3-3.5-7.4-14.2-10.5-18.9-28.4-11.8-44.1 6.9-15.3 23.8-24.3 39.7-21.1 17.7 3.6 30 17.8 30.2 35.5 0 12.3-4.9 22.3-14.8 29.5M163.3 147c0-45.6 37.1-82.6 82.6-82.6 45.6 0 82.6 37.1 82.6 82.6v51.1H163.3z" />
                </svg>
              <?php endif ?>
              <span class="text"><?php echo t('LINEで開く') ?></span>
            </div>
          </a>
        <?php endif ?>
      </section>
    </article>
    <?php GAd::output(GAd::AD_SLOTS['siteSeparatorResponsive'], true) ?>
    <?php viewComponent('footer_inner') ?>
  </div>
  <?php \App\Views\Ads\GoogleAdsense::loadAdsTag() ?>
  <script src="<?php echo fileUrl("/js/site_header_footer.js", urlRoot: '') ?>"></script>
  <script defer src="<?php echo fileurl("/js/security.js", urlRoot: '') ?>"></script>
</body>

</html>