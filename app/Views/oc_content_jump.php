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
          <li><b><?php echo t('1. 個人情報の掲載') ?></b><span><?php echo t('自分自身や他人の個人を特定できる情報（LINE ID・電話番号・氏名・住所・顔写真など）を掲載することは禁止されています。') ?></span></li>
          <li><b><?php echo t('2. 誹謗中傷・過度な批判的表現') ?></b><span><?php echo t('特定の個人等に対し、名誉を毀損する行為や、苛烈な表現・他人に不快感や嫌悪感を感じさせる表現を用いることは禁止されています。') ?></span></li>
          <li><b><?php echo t('3. 差別的発言・ヘイトスピーチ') ?></b><span><?php echo t('人種・民族・国・性別・性的指向・病気・障がい・宗教などへの差別やヘイトスピーチに当たる投稿、又はこれらを煽動する行為は禁止されています。') ?></span></li>
          <li><b><?php echo t('4. 知的財産権・プライバシー・肖像権等の権利侵害') ?></b><span><?php echo t('第三者の著作物・商標・肖像等を無断で掲載・利用するなど、第三者の権利を侵害する行為は禁止されています。') ?></span></li>
          <li><b><?php echo t('5. 自殺・自傷、他人に対する危害等の予告等') ?></b><span><?php echo t('自殺や自傷行為、他人や物に対する危害を予告・示唆したり、これらの方法を具体的に提示したりする行為は禁止されています。') ?></span></li>
          <li><b><?php echo t('6. 児童ポルノコンテンツの投稿または青少年を危険に晒す行為') ?></b><span><?php echo t('18歳未満の児童を性的に描写・搾取すると判断される画像・動画等の投稿、又は青少年を危険に晒す行為は禁止されています。') ?></span></li>
          <li><b><?php echo t('7. わいせつ・暴力的など一般人が不快と感じる内容を投稿する行為') ?></b><span><?php echo t('わいせつ・暴力的・猟奇的、過激な描写、動物虐待等、一般人が不快だと感じる可能性のある内容の投稿は禁止されています。') ?></span></li>
          <li><b><?php echo t('8. 出会いを目的とする行為') ?></b><span><?php echo t('面識のない他人との出会いや交際のみを目的とする投稿、又は不健全な目的で人を引き合わせる行為は禁止されています。') ?></span></li>
          <li><b><?php echo t('9. 法令違反または法令違反につながる恐れのある行為') ?></b><span><?php echo t('投稿自体が法令違反（犯罪を含む）を構成し得る内容、又は法令違反を誘発・助長する恐れのある行為は禁止されています。') ?></span></li>
          <li><b><?php echo t('10. 明らかな偽誤情報の拡散・流布') ?></b><span><?php echo t('明らかに事実と異なり社会的に混乱を招くおそれのある投稿や、健康被害等をもたらす可能性のある偽誤情報の拡散・流布は禁止されています。') ?></span></li>
          <li><b><?php echo t('11. なりすまし') ?></b><span><?php echo t('ユーザー本人以外の人物や組織などになりすます行為は禁止されています。') ?></span></li>
          <li><b><?php echo t('12. サイバーセキュリティリスクの恐れがある行為') ?></b><span><?php echo t('システムにアクセス負荷をかけたり脆弱性の詮索を行うなど、サーバー又はネットワークの機能を破壊・妨害する行為は禁止されています。') ?></span></li>
          <li><b><?php echo t('13. サービス運営の妨害（荒らし行為）') ?></b><span><?php echo t('ユーザー間の意見交換・交流を著しく困難なものとするなど、サービスの適正な運営を阻害するおそれがある行為は禁止されています。') ?></span></li>
          <li><b><?php echo t('14. 商用宣伝を目的とする行為') ?></b><span><?php echo t('本サービスを商業目的や広告目的に利用することは禁止されています（身元が明確かつ違法でない場合を除く）。') ?></span></li>
          <li><b><?php echo t('15. その他当社が不適切とみなす行為') ?></b><span><?php echo t('社会通念上、他人やほかのユーザーが不快・迷惑と感じる行為や、サービスの趣旨にそぐわない行為は禁止されています。') ?></span></li>
        </ul>
        <span class="oc-jump-source"><?php echo t('出典：') ?><a href="https://openchat-jp.line.me/other/prohibited_activities" target="_blank" rel="noopener nofollow"><?php echo t('LINEオープンチャット 禁止規定') ?></a></span>
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
