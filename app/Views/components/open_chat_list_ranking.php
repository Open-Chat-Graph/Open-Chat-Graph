<?php
/* @param array $openChatList */
/* @param bool $isHourly */

use App\Views\Classes\CollapseKeywordEnumerations;

$showReverseListMedal = $showReverseListMedal ?? false;
$listLen = count($openChatList);
?>
<ol class="openchat-item-list unset"
  style="counter-reset: openchat-counter <?php echo isset($noReverse) && $noReverse ? 0 : count($openChatList) + 1 ?>;">
  <?php foreach (isset($noReverse) && $noReverse ? $openChatList : array_reverse($openChatList) as $key => $oc) : ?>
    <li class="openchat-item unset <?php echo isset($noReverse) && $noReverse ? '' : 'reverse' ?> <?php if ($showReverseListMedal && ($noReverse ? $key === 0 : $listLen === $key + 1)) echo 'goldmedal';
                                                                                                  elseif ($showReverseListMedal && ($noReverse ? $key === 1 : $listLen - 1 === $key + 1)) echo 'silvermedal';
                                                                                                  elseif ($showReverseListMedal && ($noReverse ? $key === 2 : $listLen - 2 === $key + 1)) echo 'blonzemedal'; ?>">
      <a class="link-overlay unset" href="<?php echo url('/oc/' . $oc['id'] . (($isHourly ?? false) && ($oc['diff_member'] ?? null) !== null ? '?limit=hour' : '')) ?>" tabindex="-1" aria-hidden="true">
        <span class="visually-hidden"><?php echo $oc['name'] ?></span>
      </a>
      <img alt="<?php echo $oc['name'] ?>" class="openchat-item-img" loading="lazy" src="<?php echo imgPreviewUrl($oc['img_url']) ?>">
      <h3 class="unset">
        <a class="openchat-item-title unset" href="<?php echo url('/oc/' . $oc['id'] . (($isHourly ?? false) && ($oc['diff_member'] ?? null) !== null ? '?limit=hour' : '')) ?>"><?php if (($oc['emblem'] ?? 0) === 1) : ?><span class="super-icon sp"></span><?php elseif (($oc['emblem'] ?? 0) === 2) : ?><span class="super-icon official"></span><?php endif ?><?php if (($oc['join_method_type'] ?? 0) === 2) : ?><span class="lock-icon"></span><?php endif ?><span><?php echo $oc['name'] ?></span></a>
      </h3>
      <p class="openchat-item-desc unset"><?php echo h(CollapseKeywordEnumerations::collapse(htmlspecialchars_decode($oc['description']), extraText: htmlspecialchars_decode($oc['name']))) ?></p>
      <footer class="openchat-item-lower-outer">
        <div class="openchat-item-lower unset">
          <span><?php echo sprintfT('メンバー %s人', formatMember($oc['member'])) ?></span>

          <div class="openchat-item-lower unset <?php echo ($oc['diff_member'] ?? 1) > 0 ? 'positive' : 'negative' ?>">
            <?php if (isset($oc['member'])) : ?>
            <?php endif ?>
            <?php if (($oc['diff_member'] ?? 0) > 0) : ?>
              <span>
                <span class="openchat-item-stats">・ <?php echo sprintfT('%s人増加', $oc['diff_member']) ?></span>
              </span>
            <?php elseif (($oc['diff_member'] ?? 1) < 0) : ?>
              <span>
                <span class="openchat-item-stats">・ <?php echo abs($oc['diff_member']) ?></span>
              </span>
            <?php elseif (($oc['diff_member'] ?? 1) === 0) : ?>
              <span>±0</span>
            <?php endif ?>
            <?php if (isset($oc['time'])) : ?>
              <span class="registration-date blue"><?php echo timeElapsedString($oc['time']) ?></span>
            <?php endif ?>
          </div>

        </div>
      </footer>
    </li>
  <?php endforeach ?>
</ol>