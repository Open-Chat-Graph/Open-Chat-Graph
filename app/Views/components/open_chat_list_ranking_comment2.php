<?php $count1 = 0;
$count2 = 0;
$count3 = 0;
$len = $listLen ?? 5;
$ads = $showAds ?? false;
$omit = $omitDeleted ?? true;
$listCount = count($openChatList);

use App\Views\Ads\GoogleAdsense as GAd;

/**
 * @var array{ id:int,user:string,name:string,img_url:string,description:string,member:int,emblem:int,category:int,time:string }[] $openChatList
 */
?>

<ol class="openchat-item-list unset" style="margin-bottom: -0.5rem;">
  <?php foreach ($openChatList as $key => $oc) : ?>
    <?php if ($count1 >= $len) break; ?>
    <?php if (mb_strlen($oc['description']) > 0 || !$omit) : ?>
      <?php $count1++ ?>

      <li class="openchat-item unset">
        <a class="link-overlay unset" href="<?php echo  $oc['id'] ? url('/oc/' . $oc['id']) : url('policy#comments') ?>" tabindex="-1" aria-hidden="true">
          <span class="visually-hidden"><?php echo $oc['name'] ?></span>
        </a>
        <img alt="<?php echo $oc['name'] ?>" class="openchat-item-img" loading="lazy" src="<?php echo imgPreviewUrl($oc['img_url']) ?>">
        <h3 class="unset">
          <a class="openchat-item-title unset" href="<?php echo  $oc['id'] ? url('/oc/' . $oc['id']) : url('policy#comments') ?>">
            <div class="comment-name">
              <?php if (($oc['emblem'] ?? 0) === 1) : ?><span class="super-icon sp"></span><?php elseif (($oc['emblem'] ?? 0) === 2) : ?><span class="super-icon official"></span><?php endif ?><span><?php echo $oc['name'] ?></span>
            </div>

            <?php if ($oc['member']) : ?>
              <div class="comment-member-count" style="margin-left: 3px;">
                <span>(<?php echo  formatMember($oc['member']) ?>)</span>
              </div>
            <?php endif ?>

            <?php if (mb_strlen($oc['description']) > 0) : ?>
              <div class="comment-user" style="margin-left: 3px;">
                <span>@<?php echo $oc['user'] ?></span>
              </div>
            <?php endif ?>
          </a>
        </h3>
        <?php if (mb_strlen($oc['description']) > 0) : ?>
          <p class="openchat-item-desc unset"><?php echo truncateDescription($oc['description'], 80) ?></p>
        <?php else : ?>
          <div class="comment-user" style="font-size: 12px;">
            <span>削除されたコメント</span>
          </div>
        <?php endif ?>

        <footer class="comment-footer">
          <div class="comment-time"><span><?php echo $oc['time'] ?></span></div>
          <!-- <?php if ($oc['category']) : ?>
            <div class="openchat-item-mui-chip-outer">
              <span class="openchat-item-mui-chip-inner" aria-label="カテゴリ: <?php echo getCategoryName($oc['category']) ?>"><?php echo getCategoryName($oc['category']) ?></span>
            </div>
          <?php endif ?> -->
        </footer>
        <div class="link-overlay hover"></div>
      </li>

      <?php if ($ads && $count1 % 10 !== 0) : ?>
        <hr class="hr-bottom" style="margin: 4px -1rem; padding: 0; width: calc(100% + 2rem);">
      <?php endif ?>

      <?php if ($ads && $count1 < $listCount && $count1 % 10 === 0) : ?>
        <li class="unset" style="margin: 0 -1rem; display: block;">
          <hr class="hr-bottom" style="margin: 4px 0 8px 0; padding: 0; width: 100%;">
          <?php GAd::output('recentCommentSeparatorResponsive') ?>
          <hr class="hr-top" style="margin: 8px 0 4px 0; padding: 0; width: 100%;">
        </li>
      <?php endif ?>

    <?php else: ?>
      <?php $count2++ ?>
    <?php endif ?>
  <?php endforeach ?>

  <?php if ($count2) : ?>
    <?php foreach ($openChatList as $oc) : ?>
      <?php if ($count2 === 0 || $count3 >= 3) break; ?>
      <?php if (mb_strlen($oc['description']) > 0) : ?>
        <?php continue; ?>
      <?php else : ?>
        <?php $count2--;
        $count3++; ?>
        <li class="unset" style="position: relative; display:block; padding: 8px 0;">
          <a class="link-overlay unset" href="<?php echo  $oc['id'] ? url('/oc/' . $oc['id']) : url('policy#comments') ?>" tabindex="-1" aria-hidden="true">
            <span class="visually-hidden"><?php echo $oc['name'] ?></span>
          </a>
          <h3 class="unset">
            <a style="font-size: 12px;" class="openchat-item-title unset" href="<?php echo  $oc['id'] ? url('/oc/' . $oc['id']) : url('policy#comments') ?>">
              <div class="comment-name">
                <span aria-hidden="true"></span><?php if (($oc['emblem'] ?? 0) === 1) : ?><span class="super-icon sp"></span><?php elseif (($oc['emblem'] ?? 0) === 2) : ?><span class="super-icon official"></span><?php endif ?><span><?php echo $oc['name'] ?></span>
              </div>
              <div class="comment-user" style="font-size: 12px; margin-left: 4px;">
                <span>削除されたコメント</span>
              </div>
              <div class="comment-time" style="font-size: 12px; margin-left: 4px;">
                <span><?php echo $oc['time'] ?></span>
              </div>
            </a>
          </h3>
        </li>
      <?php endif ?>
    <?php endforeach ?>
  <?php endif ?>
</ol>