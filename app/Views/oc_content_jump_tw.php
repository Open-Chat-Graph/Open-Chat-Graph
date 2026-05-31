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
          <h3 class="oc-jump-section-title">LINE 貼文準則</h3>
          <span class="oc-jump-instruction">請於點擊最下方的「以LINE開啟」按鈕前，先閱讀以下各項禁止事項。</span>
        </div>
        <hr class="hr-bottom">
        <div class="oc-rule-item">
          <h3 class="oc-jump-section-title">請勿與陌生人見面</h3>
          <b class="oc-jump-rule-title">請勿尋求違法的性交易或異性邀約</b>
          <span class="oc-jump-rule-description">
            LINE 是與認識的人聯繫的工具，不建議從事可能造成他人困擾、或涉及犯罪的約會邀約等行為。
            <br>
            <br>
            ・「我在找交往對象」（尋找可約會的戀愛對象）
            <br>
            ・「我們到 LINE 以外的地方交換資訊吧」（邀請至其他社群網站或約會交友軟體）
            <br>
            ・「很高興認識你，要不要加入我們的群組？」（邀請陌生人加入聊天群組）
            <br>
            ・其他 LINE 認為不適當的行為
            <br>
            <br>
            若 LINE 透過檢舉等方式發現上述行為，將採取隱藏貼文或停用帳號（暫時及永久）等適當處置。
          </span>
        </div>
        <hr class="hr-bottom">
        <?php ad() ?>
        <h3 class="oc-jump-section-title">請勿張貼猥褻內容</h3>
        <b class="oc-jump-rule-title">請勿張貼含性暗示或猥褻圖片的貼文</b>
        <span class="oc-jump-rule-description">
          LINE 不允許使用者張貼猥褻文字或圖片，亦不允許向他人索取此類內容。不被允許的內容如下：
          <br>
          <br>
          ・描繪性行為的圖片或影片
          <br>
          ・猥褻言詞、與性行為相關的言論、張貼色情網站連結
          <br>
          ・未經修飾的裸體圖片
          <br>
          ・含有兒童少年性表現的圖片
          <br>
          ・兒童色情圖片
          <br>
          ・其他 LINE 認為不適當的行為
          <br>
          <br>
          若 LINE 透過檢舉等方式發現上述行為，將採取隱藏貼文或停用帳號（暫時及永久）等適當處置。
        </span>
        <hr class="hr-bottom">
        <?php ad() ?>
        <h3 class="oc-jump-section-title">濫用行為</h3>
        <b class="oc-jump-rule-title">請勿以各種技術手法造成他人困擾</b>
        <span class="oc-jump-rule-description">
          LINE 提供多種管道讓您與認識的人聯繫，不允許對不特定的陌生人使用這些聯繫方式來加好友或加入群組，亦禁止任何 LINE 視為濫發訊息（spam）、或以技術手段造成困擾的行為。
          <br>
          <br>
          ・使用可自動執行的指令群組或其他工具，連續張貼貼圖或自動留言
          <br>
          ・在外部網站張貼 LINE ID、連結或行動條碼，向不特定對象招攬
          <br>
          ・向不特定使用者招攬至約會交友軟體、交友網站或色情聊天室
          <br>
          ・向不特定使用者招攬至隱性行銷部落格（偽裝成一般文章的廣告）
          <br>
          ・其他 LINE 認為不適當的行為
          <br>
          <br>
          若 LINE 透過檢舉等方式發現上述行為，將採取隱藏貼文或停用帳號（暫時及永久）等適當處置。
        </span>
        <hr class="hr-bottom">
        <?php ad() ?>
        <h3 class="oc-jump-section-title">以不當商業手法使用 LINE 及 LINE 官方帳號</h3>
        <b class="oc-jump-rule-title">請勿使用未經 LINE 許可的商業手法</b>
        <span class="oc-jump-rule-description">
          禁止販售商品、招攬人員或徵才（經 LINE 許可者除外）。請慎防招攬購買仿冒名牌商品、性服務及營利投資資訊的店家。
          <br>
          <br>
          ・提供或疑似提供性服務的店家貼文
          <br>
          ・含色情內容的貼文、網路直銷（金字塔式銷售）等貼文
          <br>
          ・販售資訊的貼文（營利投資、賭博、自我成長等）
          <br>
          ・提供或疑似提供仿冒名牌商品的店家貼文（販售、廣告、招攬）
          <br>
          ・其他 LINE 認為不適當的商業及行為
          <br>
          <br>
          若 LINE 透過檢舉等方式發現上述行為，將採取隱藏貼文或停用帳號（暫時及永久）等適當處置。
        </span>
        <hr class="hr-bottom">
        <?php ad() ?>
        <h3 class="oc-jump-section-title">危害 LINE 的行為</h3>
        <b class="oc-jump-rule-title">請勿假冒他人或散播不實謠言</b>
        <span class="oc-jump-rule-description">
          不允許假冒 LINE Corporation 的官方帳號名稱，或散播與 LINE 服務相關的不實謠言，以及對 LINE 使用者造成困擾的行為。
          <br>
          <br>
          ・「LINE 通知您，只要您○○即可免費獲得 LINE 貼圖！」（假冒 LINE Corporation 官方帳號）
          <br>
          ・「我想出售我的 LINE 帳號」（違反 LINE 政策）
          <br>
          ・張貼不實謠言
          <br>
          ・使用、廣告、販售可自動執行的指令群組或非官方工具，以從事下列行為：危害 LINE 集團企業之行為、故意對其他使用者的 LINE 造成困擾之行為、使 LINE 無法正常使用之行為
          <br>
          ・「加我好友就送你免費 LINE 貼圖！」（向一般使用者宣告贈禮）
          <br>
          ・「加這個人好友，你就能獲得禮物」（由第三方宣告贈禮）
          <br>
          ・其他 LINE 認為不適當的行為
          <br>
          <br>
          若 LINE 透過檢舉等方式發現上述行為，將採取隱藏貼文或停用帳號（暫時及永久）等適當處置。
        </span>
        <hr class="hr-bottom">
        <?php ad() ?>
        <h3 class="oc-jump-section-title">請勿讓他人感覺不舒服</h3>
        <b class="oc-jump-rule-title">請勿使用令人不舒服的表現手法或從事騷擾行為</b>
        <span class="oc-jump-rule-description">
          請勿分享令他人感覺不舒服的訊息或圖片。即使未違法，LINE 仍禁止張貼下列內容。
          <br>
          <br>
          ・對特定對象造成困擾的言詞或圖片（或屬於霸凌者）
          <br>
          ・鼓吹自殺的言論
          <br>
          ・讓觀看者感到不適的圖片
          <br>
          ・以詐騙為目的的連結
          <br>
          ・其他 LINE 認為不適當的行為
          <br>
          <br>
          若 LINE 透過檢舉等方式發現上述行為，將採取隱藏貼文或停用帳號（暫時及永久）等適當處置。
        </span>
        <hr class="hr-bottom">
        <?php ad() ?>
        <h3 class="oc-jump-section-title">違法行為及助長違法的行為</h3>
        <b class="oc-jump-rule-title">禁止違法行為（如犯罪、使用毒品）及助長此類行為</b>
        <span class="oc-jump-rule-description">
          請勿脅迫或要求他人從事違法行為，亦不得散播您本人或他人的違法行為。
          <br>
          <br>
          ・構成犯罪的行為
          <br>
          ・販售及購買毒品或為規避法律而改造的毒品（Designer drug）
          <br>
          ・以明顯過高的價格販售及購買商品
          <br>
          ・將線上帳號、虛擬貨幣及頭像兌換成金錢
          <br>
          ・非重大犯罪（如未成年人飲酒吸菸、順手牽羊）及助長此類行為
          <br>
          ・其他 LINE 認為不適當的行為
          <br>
          <br>
          若 LINE 透過檢舉等方式發現上述行為，將採取隱藏貼文或停用帳號（暫時及永久）等適當處置。
        </span>
        <hr class="hr-bottom">
        <?php ad() ?>
        <h3 class="oc-jump-section-title">其他禁止事項</h3>
        <b class="oc-jump-rule-title">請勿做出任何危害 LINE 使用安全的行為</b>
        <span class="oc-jump-rule-description">
          不允許從事任何 LINE 認為不適當、且影響全體使用者安全信賴的行為。
          <br>
          <br>
          ・分享個人資訊，如 LINE ID、行動條碼、電話號碼、地址等
          <br>
          ・將 LINE 商標及角色圖片變造為猥褻或暴力的內容
          <br>
          ・其他 LINE 認為不適當的行為
          <br>
          <br>
          若 LINE 透過檢舉等方式發現上述行為，將採取隱藏貼文或停用帳號（暫時及永久）等適當處置。
        </span>
        <hr class="hr-bottom">
        <span class="oc-jump-source">資料來源：<a href="https://guide.line.me/tw/safety/contributionStandard" target="_blank" rel="noopener nofollow">LINE 貼文準則</a></span>
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
