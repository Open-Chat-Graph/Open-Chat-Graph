<?php

/**
 * 入室確認(jump)ページ 共通レイアウト
 *
 * 言語別ビュー(oc_content_jump / _th / _tw)から、文言と禁止事項リストだけを
 * 渡して呼び出す。レイアウト・広告・ボタン・スクリプトはここに一本化する。
 *
 * @param array  $oc            部屋データ
 * @param array  $_meta, $_css  view() から引き継ぎ
 * @param string $htmlLang      <html lang="">（既定: t('ja') で各言語に解決）
 * @param array  $txt           UI文言 [
 *                                noticeTitle, noticeLead, aboutLabel,
 *                                rulesTitle, rulesLead, sourceLabel,
 *                                sourceText, sourceUrl, openButton
 *                              ]
 * @param array  $rules         禁止事項 [ ['title'=>..., 'desc'=>...(任意)], ... ]
 * @param ?string $rulesImage   公式ガイドライン画像のfileUrlパス（任意・JAのみ）
 * @param ?string $rulesImageAlt
 */

use App\Views\Ads\GoogleAdsense as GAd;

$htmlLang   = $htmlLang   ?? t('ja');
$rules      = $rules      ?? [];
$rulesImage = $rulesImage ?? null;

$_css[] = 'pages/oc-jump';
?>
<!DOCTYPE html>
<html lang="<?php echo $htmlLang ?>">
<?php viewComponent('oc_head', compact('_css', '_meta') + ['dataOverlays' => 'bottom']); ?>

<body>
  <?php viewComponent('site_header') ?>
  <?php GAd::gTag() ?>
  <div class="unset openchat body oc-jump-wrap">
    <article class="unset oc-jump-stack">

      <!-- 1. 参加前の確認 -->
      <section class="oc-jump-notice oc-jump-rise">
        <span class="oc-jump-notice__icon" aria-hidden="true">!</span>
        <div class="oc-jump-notice__body">
          <h2 class="oc-jump-notice__title"><?php echo $txt['noticeTitle'] ?></h2>
          <span class="oc-jump-notice__lead"><?php echo $txt['noticeLead'] ?></span>
        </div>
      </section>

      <!-- 2-4. 部屋情報 + 説明文 -->
      <section class="oc-jump-card oc-jump-rise">
        <img class="oc-jump-banner" alt="<?php echo $oc['name'] ?>" src="<?php echo imgUrl($oc['img_url']) ?>">
        <div class="oc-jump-room">
          <h1 class="oc-jump-room__title"><?php if ($oc['emblem'] === 1) : ?><span class="super-icon sp"></span><?php elseif ($oc['emblem'] === 2) : ?><span class="super-icon official"></span><?php endif ?><?php echo $oc['name'] ?></h1>
          <span class="oc-jump-room__meta"><?php echo sprintfT('メンバー %s人', number_format($oc['member'])) ?></span>
          <span class="oc-jump-about__label"><?php echo $txt['aboutLabel'] ?></span>
          <div class="oc-jump-about__body talkroom_description_box" id="talkroom_description_box">
            <p class="talkroom_description" id="talkroom-description">
              <span id="talkroom-description-btn"><?php echo trim(preg_replace("/(\r\n){3,}|\r{3,}|\n{3,}/", "\n\n", $oc['description'])) ?></span>
            </p>
          </div>
        </div>
      </section>

      <!-- 広告: 説明文と禁止事項の間（最高単価の枠） -->
      <div class="oc-jump-ad oc-jump-rise">
        <?php GAd::output('siteSeparatorWide', true) ?>
      </div>

      <!-- 5. 禁止事項 -->
      <section class="oc-jump-card oc-jump-rules oc-jump-rise">
        <div class="oc-jump-rules__head">
          <h3 class="oc-jump-rules__title"><?php echo $txt['rulesTitle'] ?></h3>
          <span class="oc-jump-rules__lead"><?php echo $txt['rulesLead'] ?></span>
        </div>
        <?php if ($rulesImage) : ?>
          <img class="oc-jump-rules__image" src="<?php echo fileUrl($rulesImage) ?>" alt="<?php echo $rulesImageAlt ?? '' ?>">
        <?php endif ?>
        <ul class="oc-jump-rule-list">
          <?php foreach ($rules as $rule) : ?>
            <li><b><?php echo $rule['title'] ?></b><?php if (!empty($rule['desc'])) : ?><span><?php echo $rule['desc'] ?></span><?php endif ?></li>
          <?php endforeach ?>
        </ul>
        <span class="oc-jump-source"><?php echo $txt['sourceLabel'] ?><a href="<?php echo $txt['sourceUrl'] ?>" target="_blank" rel="noopener nofollow"><?php echo $txt['sourceText'] ?></a></span>
      </section>

      <!-- 6. 入室ボタン -->
      <?php if ($oc['url']) : ?>
        <section class="oc-jump-action oc-jump-rise">
          <div class="oc-jump-room-mini">
            <img aria-hidden="true" alt="<?php echo $oc['name'] ?>" src="<?php echo imgPreviewUrl($oc['img_url']) ?>">
            <div>
              <div class="oc-jump-room-mini__name"><?php if ($oc['emblem'] === 1) : ?><span class="super-icon sp"></span><?php elseif ($oc['emblem'] === 2) : ?><span class="super-icon official"></span><?php endif ?><?php echo $oc['name'] ?></div>
              <div class="oc-jump-room-mini__member">(<?php echo formatMember($oc['member']) ?>)</div>
            </div>
          </div>
          <a href="<?php echo lineAppUrl($oc) ?>" id="line-open-button" class="oc-jump-line-button openchat_link">
            <div class="oc-jump-line-button-content">
              <?php if ($oc['join_method_type'] !== 0) : ?>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 489.4 489.4" xml:space="preserve">
                  <path d="M99 147v51.1h-3.4c-21.4 0-38.8 17.4-38.8 38.8v213.7c0 21.4 17.4 38.8 38.8 38.8h298.2c21.4 0 38.8-17.4 38.8-38.8V236.8c0-21.4-17.4-38.8-38.8-38.8h-1v-51.1C392.8 65.9 326.9 0 245.9 0 164.9.1 99 66 99 147m168.7 206.2c-3 2.2-3.8 4.3-3.8 7.8.1 15.7.1 31.3.1 47 .3 6.5-3 12.9-8.8 15.8-13.7 7-27.4-2.8-27.4-15.8v-.1c0-15.7 0-31.4.1-47.1 0-3.2-.7-5.3-3.5-7.4-14.2-10.5-18.9-28.4-11.8-44.1 6.9-15.3 23.8-24.3 39.7-21.1 17.7 3.6 30 17.8 30.2 35.5 0 12.3-4.9 22.3-14.8 29.5M163.3 147c0-45.6 37.1-82.6 82.6-82.6 45.6 0 82.6 37.1 82.6 82.6v51.1H163.3z" />
                </svg>
              <?php endif ?>
              <span class="text"><?php echo t('LINEで開く') ?></span>
            </div>
          </a>
        </section>
      <?php endif ?>

    </article>
    <?php // 最下部広告(siteSeparatorResponsive)は撤去済み: impRPM¥45/CTR0.32%/¥5日(2026-06実測)。siteSeparatorWide(高単価)は維持 ?>
    <?php viewComponent('footer_inner') ?>
  </div>
  <?php GAd::loadAdsTag() ?>
  <script src="<?php echo fileUrl("/js/site_header_footer.js", urlRoot: '') ?>"></script>
  <script defer src="<?php echo fileurl("/js/security.js", urlRoot: '') ?>"></script>
</body>

</html>
