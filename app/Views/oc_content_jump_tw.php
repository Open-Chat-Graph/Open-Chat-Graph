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
    <?php GAd::output('siteSeparatorResponsive') ?>
  </div>
<?php

}

$_css[] = 'oc-jump';
viewComponent('oc_head', compact('_css', '_meta') + ['dataOverlays' => 'bottom']); ?>

<body>
  <?php viewComponent('site_header') ?>
  <div class="unset openchat body" style="overflow: hidden;">
    <?php \App\Views\Ads\GoogleAdsense::gTag() ?>
    <article class="unset" style="display: block;">
      <section class="oc-jump-section oc-info-section">
        <h2 class="oc-jump-main-title">⚠️加入前的確認</h2>
        <hr class="hr-bottom">
        <h3 class="oc-jump-section-title">確認您要加入的開放式聊天</h3>
        <div class="oc-jump-image-wrapper">
          <img class="talkroom_banner_img oc-jump-banner-img" alt="<?php echo $oc['name'] ?>" src="<?php echo imgUrl($oc['img_url']) ?>">
        </div>
        <div class="oc-jump-info-content">
          <h1 class="talkroom_link_h1 unset oc-jump-chat-title"><?php if ($oc['emblem'] === 1) : ?><span class="super-icon sp"></span><?php elseif ($oc['emblem'] === 2) : ?><span class="super-icon official"></span><?php endif ?><?php echo $oc['name'] ?></h1>
          <div class="oc-jump-member-count">
            <span class="number_of_members oc-jump-member-text"><?php echo sprintfT('メンバー %s人', number_format($oc['member'])) ?></span>
          </div>
          <div class="talkroom_description_box" id="talkroom_description_box">
            <p class="talkroom_description" id="talkroom-description">
              <span id="talkroom-description-btn"><?php echo trim(preg_replace("/(\r\n){3,}|\r{3,}|\n{3,}/", "\n\n", $oc['description'])) ?></span>
            </p>
          </div>
        </div>
      </section>
      <hr class="hr-bottom">
      <section class="oc-jump-section oc-rules-section">
        <div class="oc-rule-item">
          <h3 class="oc-jump-section-title">LINE 社群（開放式聊天）禁止事項</h3>
          <span class="oc-jump-instruction">請於點擊最下方的「以LINE開啟」按鈕前，先閱讀以下各項禁止事項。</span>
        </div>
        <hr class="hr-bottom">
        <div class="oc-rule-item">
          <h3 class="oc-jump-section-title">禁止揭露個人 LINE ID</h3>
          <b class="oc-jump-rule-title">禁止在聊天內容中揭露個人 LINE ID（官方帳號的 ID 則不在此限）</b>
        </div>
        <hr class="hr-bottom">
        <?php ad() ?>
        <h3 class="oc-jump-section-title">禁止單獨會面相關對話</h3>
        <b class="oc-jump-rule-title">禁止在 LINE 社群中溝通與「有單獨會面意圖」的所有對話</b>
        <hr class="hr-bottom">
        <?php ad() ?>
        <h3 class="oc-jump-section-title">禁止直銷相關討論</h3>
        <b class="oc-jump-rule-title">禁止傳送直銷相關討論</b>
        <hr class="hr-bottom">
        <?php ad() ?>
        <h3 class="oc-jump-section-title">禁止有害兒少內容</h3>
        <b class="oc-jump-rule-title">禁止色情、暴力、血腥、恐怖等「有害兒少身心健康」相關討論及內容</b>
        <hr class="hr-bottom">
        <?php ad() ?>
        <h3 class="oc-jump-section-title">禁止菸酒及管制物品討論</h3>
        <b class="oc-jump-rule-title">菸（包括雪茄、加熱菸、類菸品如電子菸等）、酒類，或法令禁止或列管兒少接觸物品之討論，應符合法令</b>
        <hr class="hr-bottom">
        <?php ad() ?>
        <h3 class="oc-jump-section-title">禁止博弈與投注</h3>
        <b class="oc-jump-rule-title">禁止博弈（包括麻將、撲克）、運動賽事投注相關討論</b>
        <hr class="hr-bottom">
        <?php ad() ?>
        <h3 class="oc-jump-section-title">禁止違法行為</h3>
        <b class="oc-jump-rule-title">禁止任何違法行為（包括禁止販售：仿冒品、活體寵物、處方箋藥物等，任何違法行為）</b>
        <hr class="hr-bottom">
        <span class="oc-jump-source">資料來源：<a href="https://line-tw-official.weblog.to/archives/82859412.html" target="_blank" rel="noopener nofollow">LINE 台灣官方部落格・LINE 社群使用規範</a></span>
      </section>
      <div class="oc-jump-footer-info">
        <img class="openchat-item-title-img" aria-hidden="true" alt="<?php echo $oc['name'] ?>" src="<?php echo imgPreviewUrl($oc['img_url']) ?>">
        <div class="oc-jump-footer-text">
          <div class="oc-jump-footer-name-wrapper">
            <div class="oc-jump-footer-name"><?php if ($oc['emblem'] === 1) : ?><span class="super-icon sp"></span><?php elseif ($oc['emblem'] === 2) : ?><span class="super-icon official"></span><?php endif ?><?php echo $oc['name'] ?></div>
            <div class="oc-jump-footer-member">(<?php echo formatMember($oc['member']) ?>)</div>
          </div>
        </div>
      </div>
      <?php if ($oc['url']) : ?>
        <a href="<?php echo lineAppUrl($oc) ?>" id="line-open-button" class="openchat_link oc-jump-line-button">
          <div class="oc-jump-line-button-content">
            <?php if ($oc['join_method_type'] !== 0) : ?>
              <svg style="height: 12px; fill: white; margin-right: 3px;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 489.4 489.4" xml:space="preserve">
                <path d="M99 147v51.1h-3.4c-21.4 0-38.8 17.4-38.8 38.8v213.7c0 21.4 17.4 38.8 38.8 38.8h298.2c21.4 0 38.8-17.4 38.8-38.8V236.8c0-21.4-17.4-38.8-38.8-38.8h-1v-51.1C392.8 65.9 326.9 0 245.9 0 164.9.1 99 66 99 147m168.7 206.2c-3 2.2-3.8 4.3-3.8 7.8.1 15.7.1 31.3.1 47 .3 6.5-3 12.9-8.8 15.8-13.7 7-27.4-2.8-27.4-15.8v-.1c0-15.7 0-31.4.1-47.1 0-3.2-.7-5.3-3.5-7.4-14.2-10.5-18.9-28.4-11.8-44.1 6.9-15.3 23.8-24.3 39.7-21.1 17.7 3.6 30 17.8 30.2 35.5 0 12.3-4.9 22.3-14.8 29.5M163.3 147c0-45.6 37.1-82.6 82.6-82.6 45.6 0 82.6 37.1 82.6 82.6v51.1H163.3z" />
              </svg>
            <?php endif ?>
            <span class="text"><?php echo t('LINEで開く') ?></span>
          </div>
        </a>
      <?php endif ?>
    </article>
    <?php viewComponent('footer_inner') ?>
  </div>
  <?php \App\Views\Ads\GoogleAdsense::loadAdsTag() ?>
  <script src="<?php echo fileUrl("/js/site_header_footer.js", urlRoot: '') ?>"></script>
  <script defer src="<?php echo fileurl("/js/security.js", urlRoot: '') ?>"></script>
</body>

</html>
