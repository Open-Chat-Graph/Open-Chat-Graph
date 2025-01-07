<!-- @param array $openChatList -->
<!-- @param bool $isHourly -->
<ol class="openchat-item-list unset">
  <?php foreach ($openChatList as $oc) : ?>
    <li class="openchat-item unset">
      <a class="link-overlay unset" href="<?php echo url('/oc/' . $oc['id'] . (($isHourly ?? false) && ($oc['diff_member'] ?? null) !== null ? '?limit=hour' : '')) ?>" tabindex="-1" aria-hidden="true">
        <span class="visually-hidden"><?php echo $oc['name'] ?></span>
      </a>
      <img alt="<?php echo $oc['name'] ?>" class="openchat-item-img" loading="lazy" src="<?php echo imgPreviewUrl($oc['id'], $oc['img_url']) ?>">
      <h3 class="unset">
        <a class="openchat-item-title unset" href="<?php echo url('/oc/' . $oc['id'] . (($isHourly ?? false) && ($oc['diff_member'] ?? null) !== null ? '?limit=hour' : '')) ?>"><?php if (($oc['emblem'] ?? 0) === 1) : ?><span class="super-icon sp"></span><?php elseif (($oc['emblem'] ?? 0) === 2) : ?><span class="super-icon official"></span><?php endif ?><?php if (($oc['join_method_type'] ?? 0) === 2) : ?><span class="lock-icon"></span><?php endif ?><span><?php echo $oc['name'] ?></span></a>
      </h3>
      <p class="openchat-item-desc unset"><?php echo $oc['description'] ?></p>
      <footer class="openchat-item-lower-outer">
        <div class="openchat-item-lower unset">
          <span><?php echo sprintfT('メンバー %s人', formatMember($oc['member'])) ?></span>
          <?php if (isset($oc['category']) && $oc['category']) : ?>
            <div class="openchat-item-mui-chip-outer">
              <span class="openchat-item-mui-chip-inner" aria-label="<?php echo t('カテゴリー') ?>: <?php echo getCategoryName($oc['category']) ?>"><?php echo getCategoryName($oc['category']) ?></span>
            </div>
          <?php endif ?>
        </div>
        <div class="openchat-item-lower unset <?php echo ($oc['diff_member'] ?? 1) > 0 ? 'positive' : 'negative' ?>">
          <?php if (isset($oc['member'])) : ?>
          <?php endif ?>
          <?php if (($oc['diff_member'] ?? 0) > 0) : ?>
            <span>
              <span class="openchat-item-stats">↑ <?php echo sprintfT('%s人増加', $oc['diff_member']) ?></span>
            </span>
          <?php elseif (($oc['diff_member'] ?? 1) < 0) : ?>
            <span>
              <span class="openchat-item-stats">↓ <?php echo abs($oc['diff_member']) ?></span>
            </span>
          <?php elseif (($oc['diff_member'] ?? 1) === 0) : ?>
            <span>±0</span>
          <?php endif ?>
          <?php if (isset($oc['time'])) : ?>
            <span class="registration-date blue"><?php echo timeElapsedString($oc['time']) ?></span>
          <?php endif ?>
        </div>
      </footer>
    </li>
  <?php endforeach ?>
</ol>