<!-- @param array $openChatList -->
<!-- @param string $_now -->
<ol class="openchat-item-list unset">
  <?php foreach ($openChatList as $key => $oc) : ?>
    <?php $timeFrame = $oc['end_datetime'] ? calculateTimeFrame($_now, $oc['end_datetime']) : calculateTimeFrame($_now, $oc['old_datetime']) ?>
    <li style="all: unset; display: block;">

      <div class="openchat-item unset" style="margin-right: 0;">
        <a class="link-overlay unset" href="<?php echo url('/oc/' . $oc['id'] . "?bar=ranking&limit={$timeFrame}") ?>" tabindex="-1" aria-hidden="true">
          <span class="visually-hidden"><?php echo $oc['name'] ?></span>
        </a>
        <img alt="<?php echo $oc['name'] ?>" class="openchat-item-img" loading="lazy" src="<?php echo imgPreviewUrl($oc['img_url']) ?>">
        <h3 class="unset">
          <a class="openchat-item-title unset" href="<?php echo url('/oc/' . $oc['id'] . "?bar=ranking&limit={$timeFrame}") ?>"><?php if (($oc['emblem'] ?? 0) === 1) : ?><span class="super-icon sp"></span><?php elseif (($oc['emblem'] ?? 0) === 2) : ?><span class="super-icon official"></span><?php endif ?><?php if (($oc['join_method_type'] ?? 0) === 2) : ?><span class="lock-icon"></span><?php endif ?><span><?php echo $oc['name'] ?></span></a>
        </h3>
        <p class="openchat-item-desc unset" style="color: #777;"><?php echo $oc['description'] ?></p>
        <footer class="openchat-item-lower-outer rb-card-footer" style="gap: 0;">

          <?php // 1. ステータスバッジ（最優先情報） ?>
          <div class="rb-badge-row">
            <?php if (!isset($oc['end_datetime'])) : ?>
              <span class="rb-badge rb-badge--gone"><?php echo $_now === $oc['old_datetime'] ? 'たった今消えた' : calculateTimeDifference($_now, $oc['old_datetime']) . '前に消えた' ?></span>
            <?php else : ?>
              <span class="rb-badge rb-badge--back"><?php echo calculateTimeDifference($oc['end_datetime'], $oc['old_datetime']) ?>で復活</span>
            <?php endif ?>
          </div>

          <?php // 2. メタ行（メンバー数・順位・カテゴリ） ?>
          <div class="rb-meta">
            <span>メンバー <?php echo formatMember($oc['old_member']) ?> <span class="rb-meta-diff">(<?php echo signedNumF($oc['member'] - $oc['old_member']) ?: '±0' ?>)</span></span>
            <span class="rb-meta-sep" aria-hidden="true">・</span>
            <span>順位 <?php echo calculatePositionPercentage($oc['percentage']) ?></span>
            <?php if (isset($oc['category']) && $oc['category']) : ?>
              <span class="openchat-item-mui-chip-inner" aria-label="カテゴリ: <?php echo getCategoryName($oc['category']) ?>"><?php echo getCategoryName($oc['category']) ?></span>
            <?php endif ?>
          </div>

          <?php // 3. 変更行（消える直前のルーム内容変更） ?>
          <div class="rb-change-row">
            <?php if ($oc['update_items']) : ?>
              <?php if ($oc['updated_at']) : ?>
                <span class="rb-change-label rb-change-label--caused">変更により未掲載:</span>
              <?php else : ?>
                <span class="rb-change-label">変更あり:</span>
              <?php endif ?>
              <span class="rb-chips">
                <?php foreach ($oc['update_items'] as $item) : ?>
                  <?php if ($item === 'name') : ?>
                    <span class="rb-chip">ルーム名</span>
                  <?php elseif ($item === 'description') : ?>
                    <span class="rb-chip">説明文</span>
                  <?php elseif ($item === 'img_url') : ?>
                    <span class="rb-chip">画像</span>
                  <?php elseif ($item === 'join_method_type') : ?>
                    <span class="rb-chip">公開設定</span>
                  <?php elseif ($item === 'category') : ?>
                    <span class="rb-chip">カテゴリー</span>
                  <?php elseif ($item === 'emblem') : ?>
                    <span class="rb-chip">バッジ</span>
                  <?php else : ?>
                    <span class="rb-chip"><?php echo $item ?></span>
                  <?php endif ?>
                <?php endforeach ?>
              </span>
            <?php else : ?>
              <?php if (strtotime($oc['old_datetime']) > strtotime('2025-08-10 23:59:59')) : ?>
                <span class="rb-change-none">ルーム内容の変更なし</span>
              <?php endif ?>
            <?php endif ?>
          </div>

          <?php // 4. タイムライン行（いつ消えた・いつ復活した） ?>
          <div class="rb-timeline">
            <?php if (!isset($oc['end_datetime'])) : ?>
              <span><?php echo formatDateTimeHourly2($oc['old_datetime']) ?> に消えた → 現在</span>
            <?php else : ?>
              <span><?php echo formatDateTimeHourly2($oc['old_datetime']) ?> に消えた → <?php echo formatDateTimeHourly2($oc['end_datetime']) ?> に復活</span>
            <?php endif ?>
          </div>

        </footer>
      </div>
    </li>
  <?php endforeach ?>
</ol>
