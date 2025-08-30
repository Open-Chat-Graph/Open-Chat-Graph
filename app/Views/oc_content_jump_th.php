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
        <h2 style="margin: 1rem;">⚠️โปรดอ่านก่อนเข้าร่วม</h2>
        <hr class="hr-bottom" style="margin: 1rem 0; width: 100%; ">
        <h3 style="margin: 1rem;">ตรวจสอบ Open Chat ที่จะเข้าร่วม</h3>
        <div style="margin: 1rem 2rem;">
          <img class="talkroom_banner_img" style="aspect-ratio: 1.8; border-radius: 0;" alt="<?php echo $oc['name'] ?>" src="<?php echo $oc['img_url'] ? imgUrl($oc['id'], $oc['img_url']) : lineImgUrl($oc['api_img_url']) ?>">
        </div>
        <div class="openchat-header-right" style="margin: 1rem 2rem; padding: 0;">
          <h1 class="talkroom_link_h1 unset" style="text-align: center;"><?php if ($oc['emblem'] === 1) : ?><span class="super-icon sp"></span><?php elseif ($oc['emblem'] === 2) : ?><span class="super-icon official"></span><?php endif ?><?php echo $oc['name'] ?></h1>
          <div style="text-align: center;">
            <span class="number_of_members" style="color: #111; font-weight: normal;"><?php echo sprintfT('สมาชิก %s คน', number_format($oc['member'])) ?></span>
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
        <h3 style="margin: 1rem; ">มาตรฐานเกี่ยวกับการโพสต์บน LINE</h3>
        <span style=" display: block; margin: 1rem; font-size: 16px;">โปรดทำเครื่องหมายในแต่ละข้อด้านล่าง</span>
        <span style=" display: block; margin: 1rem; font-size: 16px;">เมื่อทำเครื่องหมายครบทุกข้อ ปุ่ม “เปิดใน LINE” ที่อยู่ด้านล่างสุดจะใช้งานได้</span>
        <hr class="hr-bottom" style="margin: 1rem 0; width: 100%; ">
        <h3 style="margin: 1rem; ">ไม่นัดพบคนไม่รู้จัก</h3>
        <b style="display: block; margin: 1rem; font-size: 13px;">ไม่นัดพบเพศตรงข้ามที่ไม่รู้จัก หรือชักชวนมีเพศสัมพันธ์ที่ผิดกฎหมาย</b>
        <span style="display: block; margin: 1rem; font-size: 13px;">
          LINE คือเครื่องมือติดต่อสื่อสารกับคนที่คุณรู้จัก ไม่แนะนำให้กระทำการที่เป็นการรบกวนหรือนัดพบกันซึ่งอาจนำไปสู่การกระทำที่ผิดกฎหมาย
          <br>
          <br>
          ・"ฉันกำลังหาแฟน" (หาคู่รักเพื่อออกเดต)
          <br>
          ・"เรามาแลกข้อมูลกันนอก LINE เถอะ" (เชิญชวนให้ใช้โซเชียลเน็ตเวิร์คอื่นหรือแอปพลิเคชันหาคู่)
          <br>
          ・"ยินดีที่ได้รู้จัก มาเข้ากลุ่มเราไหม" (เชิญคนที่ไม่รู้จักให้มาเข้ากลุ่มแชท)
          <br>
          ・การกระทำอื่นๆ ที่ LINE เห็นว่าไม่เหมาะสม
          <br>
          <br>
          หาก LINE พบการกระทำดังกล่าวผ่านการรายงานปัญหา หรืออื่นๆ จะดำเนินการตามความเหมาะสม เช่น ซ่อนโพสต์ หรือระงับการใช้งานบัญชี (ชั่วคราวและถาวร)
        </span>
        <div style="margin: 1rem 2rem; display: flex; align-items: center;">
          <input type="checkbox" id="check-general" style="width: 20px; height: 20px; margin-right: 10px;">
          <label for="check-general" style="font-size: 14px; cursor: pointer;">ฉันยืนยันว่าได้ตรวจสอบข้อ “ไม่นัดพบคนไม่รู้จัก” แล้ว</label>
        </div>
        <hr class="hr-bottom" style="margin: 1rem 0; width: 100%; ">
        <?php ad() ?>
        <h3 style="margin: 1rem; ">ไม่แชร์เนื้อหาอนาจาร</h3>
        <b style="display: block; margin: 1rem; font-size: 13px;">ไม่อนุญาตให้โพสต์ข้อความที่มีการแสดงออกทางเพศหรือรูปลามกอนาจาร</b>
        <span style="display: block; margin: 1rem; font-size: 13px;">
          LINE ไม่อนุญาตให้ผู้ใช้โพสต์ข้อความหรือรูปลามกอนาจาร รวมถึงการร้องขอสิ่งเหล่านั้นจากผู้อื่น เนื้อหาที่ไม่อนุญาตมีดังต่อไปนี้
          <br>
          <br>
          ・รูปภาพหรือวิดีโอที่แสดงถึงการร่วมเพศ
          <br>
          ・คำพูดลามกอนาจาร คำพูดเกี่ยวกับการร่วมเพศ การโพสต์ลิงก์เว็บไซต์ลามกอนาจาร
          <br>
          ・ภาพเปลือยที่ไม่เซ็นเซอร์
          <br>
          ・ภาพที่มีการแสดงออกทางเพศของเด็ก
          <br>
          ・ภาพลามกอนาจารของเด็ก
          <br>
          ・การกระทำอื่นๆ ที่ LINE เห็นว่าไม่เหมาะสม
          <br>
          <br>
          หาก LINE พบการกระทำดังกล่าวผ่านการรายงานปัญหา หรืออื่นๆ จะดำเนินการตามความเหมาะสม เช่น ซ่อนโพสต์ หรือระงับการใช้งานบัญชี (ชั่วคราวและถาวร)
        </span>
        <div style="margin: 1rem 2rem; display: flex; align-items: center;">
          <input type="checkbox" id="check-adult" style="width: 20px; height: 20px; margin-right: 10px;">
          <label for="check-adult" style="font-size: 14px; cursor: pointer;">ฉันยืนยันว่าได้ตรวจสอบข้อ "ไม่แชร์เนื้อหาอนาจาร" แล้ว</label>
        </div>
        <hr class="hr-bottom" style="margin: 1rem 0; width: 100%; ">
        <?php ad() ?>
        <h3 style="margin: 1rem; ">สแปม</h3>
        <b style="display: block; margin: 1rem; font-size: 13px;">ไม่อนุญาตให้ก่อกวนโดยใช้วิธีทางเทคนิค</b>
        <span style="display: block; margin: 1rem; font-size: 13px;">
          LINE ให้บริการในหลายช่องทางเพื่อให้คุณได้ติดต่อสื่อสารกับคนที่รู้จัก ไม่อนุญาตให้ใช้วิธีการติดต่อสื่อสารเหล่านั้นกับคนที่ไม่รู้จักโดยไม่เจาะจงเพื่อเพิ่มเป็นเพื่อนหรือเพิ่มในกลุ่มแชท รวมถึงห้ามทำการใดๆ ที่ LINE พิจารณาว่าเป็นสแปม หรือใช้เทคโนโลยีในการกระทำที่เป็นการก่อกวน
          <br>
          <br>
          ・โพสต์สติกเกอร์หรือความคิดเห็นอัตโนมัติอย่างต่อเนื่อง โดยใช้กลุ่มคำสั่งที่สามารถทำงานอัตโนมัติ หรือเครื่องมืออื่นๆ
          <br>
          ・โพสต์ LINE ID ลิงก์ หรือคิวอาร์โค้ดบนเว็บไซต์ภายนอกเพื่อเชิญชวนคนอื่นๆ โดยไม่เจาะจง
          <br>
          ・เชิญชวนผู้ใช้โดยไม่เจาะจงไปยังแอปพลิเคชันหรือเว็บไซต์หาคู่ หรือห้องแชทลามกอนาจาร
          <br>
          ・เชิญชวนผู้ใช้โดยไม่เจาะจงไปยังบล็อกการตลาดแบบล่องหน (การโฆษณาในรูปแบบบทความทั่วไป)
          <br>
          ・การกระทำอื่นๆ ที่ LINE เห็นว่าไม่เหมาะสม
          <br>
          <br>
          หาก LINE พบการกระทำดังกล่าวผ่านการรายงานปัญหา หรืออื่นๆ จะดำเนินการตามความเหมาะสม เช่น ซ่อนโพสต์ หรือระงับการใช้งานบัญชี (ชั่วคราวและถาวร)
        </span>
        <div style="margin: 1rem 2rem; display: flex; align-items: center;">
          <input type="checkbox" id="check-spam" style="width: 20px; height: 20px; margin-right: 10px;">
          <label for="check-spam" style="font-size: 14px; cursor: pointer;">ฉันยืนยันว่าได้ตรวจสอบข้อ "สแปม" แล้ว</label>
        </div>
        <hr class="hr-bottom" style="margin: 1rem 0; width: 100%; ">
        <?php ad() ?>
        <h3 style="margin: 1rem; ">การใช้ LINE และ LINE Official Account ในเชิงพาณิชย์อย่างไม่เหมาะสม</h3>
        <b style="display: block; margin: 1rem; font-size: 13px;">ไม่อนุญาตให้ใช้งานในเชิงพาณิชย์โดยไม่ได้รับอนุญาตจาก LINE</b>
        <span style="display: block; margin: 1rem; font-size: 13px;">
          ห้ามจำหน่ายสินค้า เชิญชวนผู้คน หรือรับสมัครงานใดๆ (ยกเว้นกรณีที่ได้รับอนุญาตจาก LINE) โปรดระวังร้านค้าที่เชิญชวนให้ซื้อสินค้าแบรนด์เนมปลอม บริการทางเพศ และข้อมูลเกี่ยวกับการลงทุนเพื่อผลกำไร
          <br>
          <br>
          ・โพสต์ของร้านค้าที่นำเสนอหรือดูเหมือนมีการนำเสนอบริการทางเพศ
          <br>
          ・โพสต์ที่มีเนื้อหาลามกอนาจาร โพสต์ธุรกิจเครือข่าย (ธุรกิจปิระมิด) และอื่นๆ
          <br>
          ・โพสต์เพื่อเสนอขายข้อมูล (การลงทุนเพื่อผลกำไร การพนัน การพัฒนาตนเอง และอื่นๆ)
          <br>
          ・โพสต์ของร้านค้าที่นำเสนอหรือดูเหมือนมีการนำเสนอสินค้าแบรนด์เนมปลอม (จำหน่าย โฆษณา เชิญชวน)
          <br>
          ・ธุรกิจและการกระทำอื่นๆ ที่ LINE เห็นว่าไม่เหมาะสม
          <br>
          <br>
          หาก LINE พบการกระทำดังกล่าวผ่านการรายงานปัญหา หรืออื่นๆ จะดำเนินการตามความเหมาะสม เช่น ซ่อนโพสต์ หรือระงับการใช้งานบัญชี (ชั่วคราวและถาวร)
        </span>
        <div style="margin: 1rem 2rem; display: flex; align-items: center;">
          <input type="checkbox" id="check-commercial" style="width: 20px; height: 20px; margin-right: 10px;">
          <label for="check-commercial" style="font-size: 14px; cursor: pointer;">ฉันยืนยันว่าได้ตรวจสอบข้อ "การใช้ LINE และ LINE Official Account ในเชิงพาณิชย์อย่างไม่เหมาะสม" แล้ว</label>
        </div>
        <hr class="hr-bottom" style="margin: 1rem 0; width: 100%; ">
        <?php ad() ?>
        <h3 style="margin: 1rem; ">การกระทำที่ก่อให้เกิดผลเสียต่อ LINE</h3>
        <b style="display: block; margin: 1rem; font-size: 13px;">ไม่อนุญาตให้แอบอ้างชื่อหรือปล่อยข่าวลือที่เป็นเท็จ</b>
        <span style="display: block; margin: 1rem; font-size: 13px;">
          ไม่อนุญาตให้แอบอ้างชื่อบัญชีทางการของ LINE Corporation หรือปล่อยข่าวลือที่เป็นเท็จเกี่ยวกับบริการของ LINE รวมถึงการกระทำที่รบกวนผู้ใช้ LINE
          <br>
          <br>
          ・"แจ้งข่าวจาก LINE รับสติกเกอร์ LINE ฟรีเพียงแค่คุณ 〇〇!" (แอบอ้างชื่อบัญชีทางการของ LINE Corporation)
          <br>
          ・"ฉันต้องการขายบัญชี LINE ของฉัน" (ละเมิดนโยบายของ LINE)
          <br>
          ・โพสต์ข่าวลือที่เป็นเท็จ
          <br>
          ・การใช้ โฆษณา จำหน่ายกลุ่มคำสั่งที่สามารถทำงานอัตโนมัติ หรือเครื่องมือที่ไม่ใช่ทางการเพื่อทำการต่อไปนี้ การกระทำที่สร้างความเสียหายให้แก่กลุ่มบริษัท LINE, การกระทำที่จงใจสร้างปัญหาให้กับ LINE ของผู้ใช้อื่น, การกระทำที่ทำให้ไม่สามารถใช้ LINE ได้ตามปกติ
          <br>
          ・"เพิ่มฉันเป็นเพื่อนสิ แล้วจะให้สติกเกอร์ LINE ฟรี!" (ประกาศให้ของขวัญแก่ผู้ใช้ทั่วไป)
          <br>
          ・"เพิ่มคนนี้เป็นเพื่อน คุณจะได้รับของขวัญ" (ประกาศให้ของขวัญโดยบุคคลที่สาม)
          <br>
          ・การกระทำอื่นๆ ที่ LINE เห็นว่าไม่เหมาะสม
          <br>
          <br>
          หาก LINE พบการกระทำดังกล่าวผ่านการรายงานปัญหา หรืออื่นๆ จะดำเนินการตามความเหมาะสม เช่น ซ่อนโพสต์ หรือระงับการใช้งานบัญชี (ชั่วคราวและถาวร)
        </span>
        <div style="margin: 1rem 2rem; display: flex; align-items: center;">
          <input type="checkbox" id="check-harmful" style="width: 20px; height: 20px; margin-right: 10px;">
          <label for="check-harmful" style="font-size: 14px; cursor: pointer;">ฉันยืนยันว่าได้ตรวจสอบข้อ "การกระทำที่ก่อให้เกิดผลเสียต่อ LINE" แล้ว</label>
        </div>
        <hr class="hr-bottom" style="margin: 1rem 0; width: 100%; ">
        <?php ad() ?>
        <h3 style="margin: 1rem; ">ไม่ทำให้ผู้อื่นขุ่นเคืองใจ</h3>
        <b style="display: block; margin: 1rem; font-size: 13px;">ไม่ใช้คำพูดที่ทำให้ขุ่นเคืองใจและทำสิ่งที่เป็นการก่อกวน</b>
        <span style="display: block; margin: 1rem; font-size: 13px;">
          ไม่แชร์ข้อความหรือรูปที่ทำให้ผู้อื่นขุ่นเคืองใจ LINE ห้ามโพสต์เนื้อหาต่อไปนี้แม้จะไม่เป็นการผิดกฎหมายก็ตาม
          <br>
          <br>
          ・คำพูดหรือรูปภาพที่สร้างความขุ่นเคืองใจต่อบุคคลใดบุคคลหนึ่ง (หรือเป็นการรังแก)
          <br>
          ・คำแนะนำให้ฆ่าตัวตาย
          <br>
          ・รูปภาพที่ทำให้คนที่เห็นรู้สึกไม่สบายใจ
          <br>
          ・ลิงก์ที่มีวัตถุประสงค์เพื่อหลอกลวง
          <br>
          ・การกระทำอื่นๆ ที่ LINE เห็นว่าไม่เหมาะสม
          <br>
          <br>
          หาก LINE พบการกระทำดังกล่าวผ่านการรายงานปัญหา หรืออื่นๆ จะดำเนินการตามความเหมาะสม เช่น ซ่อนโพสต์ หรือระงับการใช้งานบัญชี (ชั่วคราวและถาวร)
        </span>
        <div style="margin: 1rem 2rem; display: flex; align-items: center;">
          <input type="checkbox" id="check-offensive" style="width: 20px; height: 20px; margin-right: 10px;">
          <label for="check-offensive" style="font-size: 14px; cursor: pointer;">ฉันยืนยันว่าได้ตรวจสอบข้อ "ไม่ทำให้ผู้อื่นขุ่นเคืองใจ" แล้ว</label>
        </div>
        <hr class="hr-bottom" style="margin: 1rem 0; width: 100%; ">
        <?php ad() ?>
        <h3 style="margin: 1rem; ">การกระทำที่ผิดกฎหมายและการกระทำเพื่อสนับสนุน</h3>
        <b style="display: block; margin: 1rem; font-size: 13px;">ห้ามการกระทำที่ผิดกฎหมาย (เช่น อาชญากรรม การใช้ยาเสพติด) และการกระทำเพื่อสนับสนุน</b>
        <span style="display: block; margin: 1rem; font-size: 13px;">
          ไม่ขู่บังคับหรือเรียกร้องให้ทำสิ่งผิดกฎหมาย และไม่เผยแพร่การกระทำที่ผิดกฎหมายของคุณหรือบุคคลอื่น
          <br>
          <br>
          ・การกระทำที่เป็นอาชญากรรม
          <br>
          ・จำหน่ายและซื้อยาเสพติดหรือยาเสพติดที่ถูกดัดแปลงเพื่อเลี่ยงกฎหมาย (Designer drug)
          <br>
          ・จำหน่ายและซื้อสินค้าในราคาที่สูงเกินไปอย่างเห็นได้ชัด
          <br>
          ・แลกเปลี่ยนบัญชีออนไลน์ สกุลเงิน และรูปประจำตัวเป็นเงิน
          <br>
          ・อาชญากรรมที่ไม่รุนแรง (เช่น การดื่มสุราและสูบบุหรี่ของเยาวชน การลักขโมยสินค้าในร้าน) และการกระทำเพื่อเผยแพร่
          <br>
          ・การกระทำอื่นๆ ที่ LINE เห็นว่าไม่เหมาะสม
          <br>
          <br>
          หาก LINE พบการกระทำดังกล่าวผ่านการรายงานปัญหา หรืออื่นๆ จะดำเนินการตามความเหมาะสม เช่น ซ่อนโพสต์ หรือระงับการใช้งานบัญชี (ชั่วคราวและถาวร)
        </span>
        <div style="margin: 1rem 2rem; display: flex; align-items: center;">
          <input type="checkbox" id="check-illegal" style="width: 20px; height: 20px; margin-right: 10px;">
          <label for="check-illegal" style="font-size: 14px; cursor: pointer;">ฉันยืนยันว่าได้ตรวจสอบข้อ "การกระทำที่ผิดกฎหมายและการกระทำเพื่อสนับสนุน" แล้ว</label>
        </div>
        <hr class="hr-bottom" style="margin: 1rem 0; width: 100%; ">
        <?php ad() ?>
        <h3 style="margin: 1rem; ">ข้อห้ามอื่นๆ</h3>
        <b style="display: block; margin: 1rem; font-size: 13px;">ไม่คุกคามผู้อื่น เพื่อให้ใช้งาน LINE อย่างไม่ปลอดภัย</b>
        <span style="display: block; margin: 1rem; font-size: 13px;">
          ไม่อนุญาตให้ทำการใดๆ ที่ LINE เห็นว่าไม่เหมาะสม ที่ส่งผลต่อความมั่นใจด้านความปลอดภัยของผู้ใช้งานทั้งหมด
          <br>
          <br>
          ・แชร์ข้อมูลส่วนบุคคล เช่น LINE ID คิวอาร์โค้ด หมายเลขโทรศัพท์ ที่อยู่ และอื่นๆ
          <br>
          ・ปรับแต่งโลโก้และภาพตัวละครของ LINE ให้แสดงถึงความลามกอนาจารและความรุนแรง
          <br>
          ・การกระทำอื่นๆ ที่ LINE เห็นว่าไม่เหมาะสม
          <br>
          <br>
          หาก LINE พบการกระทำดังกล่าวผ่านการรายงานปัญหา หรืออื่นๆ จะดำเนินการตามความเหมาะสม เช่น ซ่อนโพสต์ หรือระงับการใช้งานบัญชี (ชั่วคราวและถาวร)
        </span>
        <div style="margin: 1rem 2rem; display: flex; align-items: center;">
          <input type="checkbox" id="check-other" style="width: 20px; height: 20px; margin-right: 10px;">
          <label for="check-other" style="font-size: 14px; cursor: pointer;">ฉันยืนยันว่าได้ตรวจสอบข้อ "ข้อห้ามอื่นๆ" แล้ว</label>
        </div>
        <hr class="hr-bottom" style="margin: 1rem 0; width: 100%; ">
      </section style="margin: 1rem 0; width: 100%;">
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
        <div id="checkbox-warning" style="color: red; font-size: 14px;  margin: 0 1rem; font-weight: bold;">
          ※ โปรดทำเครื่องหมายในช่องตรวจสอบสำหรับทุกข้อ
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
      const checkboxes = document.querySelectorAll('input[type="checkbox"]');

      const allChecked = Array.from(checkboxes).every(checkbox => checkbox.checked);

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
      const checkboxes = document.querySelectorAll('input[type="checkbox"]');

      checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', checkAllCheckboxes);
      });
    });

    // Check initial state
    checkAllCheckboxes();
  </script>
</body>

</html>