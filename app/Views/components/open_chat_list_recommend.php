<ol class="openchat-item-list unset">
  <?php /** @var \App\Services\Recommend\Dto\RecommendListDto $recommend */

  use App\Config\AppConfig;
  use App\Views\Classes\CollapseKeywordEnumerations;

  if (!isset($listArray)) {
    $listArray = $recommend->getList($shuffle ?? false, ($limit ?? null) ? AppConfig::$listLimitTopRanking : null, $id ?? 0);
  }

  $listLen = count($listArray);
  $showListMedal = $showListMedal ?? false;
  $currentCount = $currentCount ?? false;
  $showApiCreatedAt = $showApiCreatedAt ?? false;

  foreach ($listArray as $key => $oc) : ?>
    <li class="unset">
      <div class="openchat-item <?php if ($showListMedal && $key === 0) echo 'goldmedal';
                                elseif ($showListMedal && $key === 1) echo 'silvermedal';
                                elseif ($showListMedal && $key === 2) echo 'blonzemedal';
                                elseif ($currentCount && $currentCount + $key + 1 >= 100) echo 'hundred' ?>">
        <a class="link-overlay unset" href="<?php echo url('/oc/' . $oc['id']) . ((isset($oc['table_name']) && ($oc['table_name'] === AppConfig::RANKING_HOUR_TABLE_NAME || $oc['table_name'] === AppConfig::RANKING_DAY_TABLE_NAME)) ? '?limit=hour' : '') ?>" tabindex="-1" aria-hidden="true">
          <span class="visually-hidden"><?php echo $oc['name'] ?></span>
        </a>
        <img alt="<?php echo $oc['name'] ?>" class="openchat-item-img" loading="lazy" src="<?php echo imgPreviewUrl($oc['img_url']) ?>">
        <h3 class="unset">
          <a class="openchat-item-title unset" href="<?php echo url('/oc/' . $oc['id']) . ((isset($oc['table_name']) && ($oc['table_name'] === AppConfig::RANKING_HOUR_TABLE_NAME || $oc['table_name'] === AppConfig::RANKING_DAY_TABLE_NAME)) ? '?limit=hour' : '') ?>"><?php if (($oc['emblem'] ?? 0) === 1) : ?><span class="super-icon sp"></span><?php elseif (($oc['emblem'] ?? 0) === 2) : ?><span class="super-icon official"></span><?php endif ?><?php if (($oc['join_method_type'] ?? 0) === 2) : ?><span class="lock-icon"></span><?php endif ?><?php echo $oc['name'] ?></a>
        </h3>
        <p class="openchat-item-desc unset"><?php
                                            // SEO: LINE 公式 description のコピー量を削減するため 40 字で truncate
                                            $collapsedDesc = CollapseKeywordEnumerations::collapse(htmlspecialchars_decode($oc['description']), extraText: htmlspecialchars_decode($oc['name']));
                                            echo h(mb_strlen($collapsedDesc) > 40 ? mb_substr($collapsedDesc, 0, 40) . '…' : $collapsedDesc);
                                            ?></p>
        <footer class="openchat-item-lower-outer">
          <div class="openchat-item-lower unset" style="font-size: 13px; margin-top: 0;">
            <?php if (isset($oc['member'])) : ?>
              <span>
                <?php if (isset($recommend) && $oc['member'] === $recommend->maxMemberCount) : ?>
                  <span aria-hidden="true" style="font-size: 9px; user-select: none;">🏆</span>
                  <span style="font-weight: bold;"><?php echo sprintfT('メンバー %s人', formatMember($oc['member'])) ?></span>
                <?php else : ?>
                  <span><?php echo sprintfT('メンバー %s人', formatMember($oc['member'])) ?></span>
                <?php endif ?>
              </span>
            <?php endif ?>
            <?php if (isset($oc['api_created_at']) && $showApiCreatedAt) : ?>
              <span class="registration-date"><?php echo t('ルーム開設') . ' ' . convertDatetime($oc['api_created_at'], false) ?></span>
            <?php endif ?>
          </div>
          <?php // 24時間の人数増加は独立行に（メンバー行に入れると溢れるため）。伸び部屋のみ表示。 ?>
          <?php $diff24h = (int)($oc['diff_member_24h'] ?? 0); ?>
          <?php if ($diff24h > 0) : ?>
            <div class="positive" style="font-size: 13px; margin-top: 1px;"><span aria-hidden="true" style="font-size: 11px; user-select: none;">🚀</span> <span class="openchat-item-stats"><?php echo sprintfT('%s人増加', formatMember($diff24h)) ?></span></div>
          <?php endif ?>
        </footer>
      </div>
    </li>
  <?php endforeach ?>
</ol>