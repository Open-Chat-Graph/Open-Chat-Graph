<!-- @param array $openChatList -->
<?php $count1 = 0;
$count2 = 0;
$count3 = 0;
?>

<ol class="openchat-item-list unset" style="margin-bottom: -0.5rem;">
  <?php foreach ($openChatList as $oc) : ?>
    <?php if ($count1 >= 5) break; ?>
    <?php if (mb_strlen($oc['description']) > 10) : ?>
      <?php $count1++ ?>
      <li class="openchat-item unset">
        <a class="link-overlay unset" href="<?php echo  $oc['id'] ? url('/oc/' . $oc['id']) : url('policy#comments') ?>" tabindex="-1" aria-hidden="true">
          <span class="visually-hidden"><?php echo $oc['name'] ?></span>
        </a>
        <img alt="<?php echo $oc['name'] ?>" class="openchat-item-img" loading="lazy" src="<?php echo imgPreviewUrl($oc['img_url']) ?>">
        <h3 class="unset">
          <a class="openchat-item-title unset" href="<?php echo  $oc['id'] ? url('/oc/' . $oc['id']) : url('policy#comments') ?>">
            <div class="comment-user">
              <span><?php echo $oc['user'] ?></span>
            </div>
            <div class="comment-name">
              <span aria-hidden="true">@</span><?php if (($oc['emblem'] ?? 0) === 1) : ?><span class="super-icon sp"></span><?php elseif (($oc['emblem'] ?? 0) === 2) : ?><span class="super-icon official"></span><?php endif ?><span><?php echo $oc['name'] ?></span>
            </div>
            <div class="comment-time">
              <span><?php echo $oc['time'] ?></span>
            </div>
          </a>
        </h3>
        <p class="openchat-item-desc unset"><?php echo truncateDescription($oc['description'], 120) ?></p>
      </li>
    <?php else : ?>
      <?php $count2++ ?>
    <?php endif ?>
  <?php endforeach ?>
  <?php if ($count2) : ?>
    <?php foreach ($openChatList as $oc) : ?>
      <?php if ($count2 === 0 || $count3 >= 3) break; ?>
      <?php if (mb_strlen($oc['description']) > 10) : ?>
        <?php continue; ?>
      <?php elseif ((mb_strlen($oc['description']) > 0)) : ?>
        <?php $count2--;
        $count3++; ?>
        <li class="unset" style="position: relative; display:block; padding: 8px 0;">
          <a class="link-overlay unset" href="<?php echo  $oc['id'] ? url('/oc/' . $oc['id']) : url('policy#comments') ?>" tabindex="-1" aria-hidden="true">
            <span class="visually-hidden"><?php echo $oc['name'] ?></span>
          </a>
          <h3 class="unset">
            <a style="font-size: 11px;" class="openchat-item-title unset" href="<?php echo  $oc['id'] ? url('/oc/' . $oc['id']) : url('policy#comments') ?>">
              <div class="comment-user">
                <span><?php echo mb_strlen($oc['description']) ?>文字のコメント</span>
              </div>
              <div class="comment-name">
                <span aria-hidden="true">@</span><?php if (($oc['emblem'] ?? 0) === 1) : ?><span class="super-icon sp"></span><?php elseif (($oc['emblem'] ?? 0) === 2) : ?><span class="super-icon official"></span><?php endif ?><span><?php echo $oc['name'] ?></span>
              </div>
              <div class="comment-time">
                <span><?php echo $oc['time'] ?></span>
              </div>
            </a>
          </h3>
        </li>
      <?php else : ?>
        <?php $count2--;
        $count3++; ?>
        <li class="unset" style="position: relative; display:block; padding: 8px 0;">
          <a class="link-overlay unset" href="<?php echo  $oc['id'] ? url('/oc/' . $oc['id']) : url('policy#comments') ?>" tabindex="-1" aria-hidden="true">
            <span class="visually-hidden"><?php echo $oc['name'] ?></span>
          </a>
          <h3 class="unset">
            <a style="font-size: 11px;" class="openchat-item-title unset" href="<?php echo  $oc['id'] ? url('/oc/' . $oc['id']) : url('policy#comments') ?>">
              <div class="comment-user">
                <span>削除されたコメント</span>
              </div>
              <div class="comment-name">
                <span aria-hidden="true">@</span><?php if (($oc['emblem'] ?? 0) === 1) : ?><span class="super-icon sp"></span><?php elseif (($oc['emblem'] ?? 0) === 2) : ?><span class="super-icon official"></span><?php endif ?><span><?php echo $oc['name'] ?></span>
              </div>
              <div class="comment-time">
                <span><?php echo $oc['time'] ?></span>
              </div>
            </a>
          </h3>
        </li>
      <?php endif ?>
    <?php endforeach ?>
  <?php endif ?>
</ol>