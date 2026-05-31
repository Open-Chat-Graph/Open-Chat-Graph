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
  <?php \App\Views\Ads\GoogleAdsense::gTag() ?>
  <div class="unset openchat body" style="overflow: hidden; max-width: 600px;">
    <article class="unset" style="display: block;">
      <section class="oc-jump-section oc-info-section">
        <h2 class="oc-jump-main-title"><?php echo t('⚠️参加前の確認') ?></h2>
        <span class="oc-jump-instruction"><?php echo t('参加するオープンチャットの説明文をご確認ください。') ?></span>
        <div class="oc-jump-image-wrapper">
          <img class="talkroom_banner_img oc-jump-banner-img"
            alt="<?php echo $oc['name'] ?>"
            src="<?php echo imgUrl($oc['img_url']) ?>">
        </div>
        <div class="oc-jump-info-content">
          <h1 class="talkroom_link_h1 unset oc-jump-chat-title"><?php if ($oc['emblem'] === 1) : ?><span class="super-icon sp"></span><?php elseif ($oc['emblem'] === 2) : ?><span class="super-icon official"></span><?php endif ?><?php echo $oc['name'] ?></h1>
          <div class="oc-jump-member-count">
            <span class="number_of_members oc-jump-member-text"><?php echo sprintfT('メンバー %s人', number_format($oc['member'])) ?></span>
          </div>
          <span class="oc-jump-content-label"><?php echo t('このオープンチャットについて') ?></span>
          <div class="talkroom_description_box" id="talkroom_description_box">
            <p class="talkroom_description" id="talkroom-description">
              <span id="talkroom-description-btn"><?php echo trim(preg_replace("/(\r\n){3,}|\r{3,}|\n{3,}/", "\n\n", $oc['description'])) ?></span>
            </p>
          </div>
        </div>
      </section>
      <?php GAd::output('siteSeparatorWide', true) ?>
      <section class="oc-jump-section oc-rules-section">
        <div class="oc-rule-item">
          <h3 class="oc-jump-section-title"><?php echo t('オープンチャットの禁止事項') ?></h3>
          <span class="oc-jump-instruction"><?php echo t('以下の禁止事項をご確認のうえ、「LINEで開く」を押してください。') ?></span>
          <img src="<?php echo fileUrl('assets/line-guilde/line-guilde.webp') ?>" alt="<?php echo t('オープンチャット禁止事項') ?>"
            class="oc-jump-rule-image">
        </div>
        <ul class="oc-jump-rule-list">
          <li><b><?php echo t('思いやりのある発言をしよう') ?></b><span><?php echo t('誹謗中傷や暴言、名誉や信用を傷つける行為は禁止されています。') ?></span></li>
          <li><b><?php echo t('個人情報を大切に扱おう') ?></b><span><?php echo t('LINE ID・電話番号・住所など、個人が特定できる情報の投稿は控えましょう。') ?></span></li>
          <li><b><?php echo t('知らない人と会わないようにしよう') ?></b><span><?php echo t('面識のない人との出会いや交際を目的とする行為は禁止されています。') ?></span></li>
          <li><b><?php echo t('不適切な性的表現をさけよう') ?></b><span><?php echo t('露骨な性的描写やわいせつな表現・画像の投稿は禁止されています。') ?></span></li>
          <li><b><?php echo t('著作権を守ろう') ?></b><span><?php echo t('他人の著作物を無断で投稿・利用する行為は禁止されています。') ?></span></li>
          <li><b><?php echo t('スパム・違法行為・商用利用の禁止') ?></b><span><?php echo t('迷惑行為や法律に違反する行為、無断の宣伝・勧誘は禁止されています。') ?></span></li>
          <li><b><?php echo t('青少年の安全を守ろう') ?></b><span><?php echo t('青少年との不健全な出会いや、危険・搾取につながる行為は一切禁止です。') ?></span></li>
        </ul>
        <span class="oc-jump-source"><?php echo t('出典：') ?><a href="https://guide.line.me/ja/safety/contributionStandard" target="_blank" rel="noopener nofollow"><?php echo t('LINE 投稿に関する基準') ?></a></span>
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
    <?php GAd::output('siteSeparatorResponsive', true) ?>
    <?php viewComponent('footer_inner') ?>
  </div>
  <?php \App\Views\Ads\GoogleAdsense::loadAdsTag() ?>
  <script src="<?php echo fileUrl("/js/site_header_footer.js", urlRoot: '') ?>"></script>
  <script defer src="<?php echo fileurl("/js/security.js", urlRoot: '') ?>"></script>
</body>

</html>
