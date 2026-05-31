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
        <h2 class="oc-jump-main-title">⚠️โปรดอ่านก่อนเข้าร่วม</h2>
        <span class="oc-jump-instruction">โปรดตรวจสอบรายละเอียดของ Open Chat ที่จะเข้าร่วม</span>
        <div class="oc-jump-image-wrapper">
          <img class="talkroom_banner_img oc-jump-banner-img" alt="<?php echo $oc['name'] ?>" src="<?php echo imgUrl($oc['img_url']) ?>">
        </div>
        <div class="oc-jump-info-content">
          <h1 class="talkroom_link_h1 unset oc-jump-chat-title"><?php if ($oc['emblem'] === 1) : ?><span class="super-icon sp"></span><?php elseif ($oc['emblem'] === 2) : ?><span class="super-icon official"></span><?php endif ?><?php echo $oc['name'] ?></h1>
          <div class="oc-jump-member-count">
            <span class="number_of_members oc-jump-member-text"><?php echo sprintfT('สมาชิก %s คน', number_format($oc['member'])) ?></span>
          </div>
          <span class="oc-jump-content-label">เกี่ยวกับ Open Chat นี้</span>
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
          <h3 class="oc-jump-section-title">มาตรฐานเกี่ยวกับการโพสต์บน LINE</h3>
          <span class="oc-jump-instruction">โปรดอ่านข้อห้ามแต่ละข้อด้านล่าง ก่อนกดปุ่ม "เปิดใน LINE" ที่อยู่ด้านล่างสุด</span>
        </div>
        <ul class="oc-jump-rule-list">
          <li><b>ไม่นัดพบคนไม่รู้จัก</b><span>LINE คือเครื่องมือติดต่อสื่อสารกับคนที่คุณรู้จัก ไม่แนะนำให้กระทำการที่เป็นการรบกวนหรือนัดพบกันซึ่งอาจนำไปสู่การกระทำที่ผิดกฎหมาย</span></li>
          <li><b>ไม่แชร์เนื้อหาอนาจาร</b><span>LINE ไม่อนุญาตให้ผู้ใช้โพสต์ข้อความหรือรูปลามกอนาจาร รวมถึงการร้องขอสิ่งเหล่านั้นจากผู้อื่น</span></li>
          <li><b>สแปม</b><span>ไม่อนุญาตให้ใช้วิธีการติดต่อสื่อสารกับคนที่ไม่รู้จักโดยไม่เจาะจงเพื่อเพิ่มเป็นเพื่อนหรือเพิ่มในกลุ่มแชท รวมถึงห้ามทำการใดๆ ที่ LINE พิจารณาว่าเป็นสแปม หรือใช้เทคโนโลยีในการก่อกวน</span></li>
          <li><b>การใช้ LINE ในเชิงพาณิชย์อย่างไม่เหมาะสม</b><span>ห้ามจำหน่ายสินค้า เชิญชวนผู้คน หรือรับสมัครงานใดๆ (ยกเว้นกรณีที่ได้รับอนุญาตจาก LINE) โปรดระวังการชักชวนซื้อสินค้าแบรนด์เนมปลอม บริการทางเพศ และการลงทุนเพื่อผลกำไร</span></li>
          <li><b>การกระทำที่ก่อให้เกิดผลเสียต่อ LINE</b><span>ไม่อนุญาตให้แอบอ้างชื่อบัญชีทางการของ LINE Corporation หรือปล่อยข่าวลือที่เป็นเท็จเกี่ยวกับบริการของ LINE รวมถึงการกระทำที่รบกวนผู้ใช้ LINE</span></li>
          <li><b>ไม่ทำให้ผู้อื่นขุ่นเคืองใจ</b><span>ไม่แชร์ข้อความหรือรูปที่ทำให้ผู้อื่นขุ่นเคืองใจ เช่น การกลั่นแกล้ง การชักชวนให้ฆ่าตัวตาย รูปภาพที่ทำให้รู้สึกไม่สบายใจ หรือลิงก์ที่มีวัตถุประสงค์เพื่อหลอกลวง</span></li>
          <li><b>การกระทำที่ผิดกฎหมายและการกระทำเพื่อสนับสนุน</b><span>ไม่ขู่บังคับหรือเรียกร้องให้ทำสิ่งผิดกฎหมาย (เช่น อาชญากรรม การใช้ยาเสพติด) และไม่เผยแพร่การกระทำที่ผิดกฎหมายของคุณหรือบุคคลอื่น</span></li>
          <li><b>ข้อห้ามอื่นๆ</b><span>ไม่อนุญาตให้ทำการใดๆ ที่ LINE เห็นว่าไม่เหมาะสม ที่ส่งผลต่อความปลอดภัยของผู้ใช้งานทั้งหมด เช่น การแชร์ข้อมูลส่วนบุคคล (LINE ID, คิวอาร์โค้ด, เบอร์โทรศัพท์, ที่อยู่)</span></li>
        </ul>
        <span class="oc-jump-source">ที่มา：<a href="https://guide.line.me/th/safety/contributionStandard" target="_blank" rel="noopener nofollow">มาตรฐานเกี่ยวกับการโพสต์บน LINE</a></span>
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
          <a href="<?php echo lineAppUrl($oc) ?>" id="line-open-button" class="openchat_link oc-jump-line-button" style="max-width: 100%;">
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
