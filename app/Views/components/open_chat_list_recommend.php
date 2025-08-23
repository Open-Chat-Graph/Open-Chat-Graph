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
        <a class="link-overlay unset" href="<?php echo url('/oc/' . $oc['id']) . ($oc['table_name'] === AppConfig::RANKING_HOUR_TABLE_NAME || $oc['table_name'] === AppConfig::RANKING_DAY_TABLE_NAME ? '?limit=hour' : '') ?>" tabindex="-1" aria-hidden="true">
          <span class="visually-hidden"><?php echo $oc['name'] ?></span>
        </a>
        <img alt="<?php echo $oc['name'] ?>" class="openchat-item-img" loading="lazy" src="<?php echo imgPreviewUrl($oc['id'], $oc['img_url']) ?>">
        <h3 class="unset">
          <a class="openchat-item-title unset" href="<?php echo url('/oc/' . $oc['id']) . ($oc['table_name'] === AppConfig::RANKING_HOUR_TABLE_NAME || $oc['table_name'] === AppConfig::RANKING_DAY_TABLE_NAME ? '?limit=hour' : '') ?>"><?php if (($oc['emblem'] ?? 0) === 1) : ?><span class="super-icon sp"></span><?php elseif (($oc['emblem'] ?? 0) === 2) : ?><span class="super-icon official"></span><?php endif ?><?php if (($oc['join_method_type'] ?? 0) === 2) : ?><span class="lock-icon"></span><?php endif ?><?php echo $oc['name'] ?></a>
        </h3>
        <p class="openchat-item-desc unset"><?php echo h(CollapseKeywordEnumerations::collapse(htmlspecialchars_decode($oc['description']), extraText: htmlspecialchars_decode($oc['name']))) ?></p>
        <footer class="openchat-item-lower-outer">
          <div class="openchat-item-lower unset" style="font-size: 13px; margin-top: 0;">
            <?php if (isset($oc['member'])) : ?>
              <span>
                <?php if ($oc['member'] === $recommend->maxMemberCount) : ?>
                  <span aria-hidden="true" style="font-size: 9px; user-select: none;">🏆</span>
                  <span style="font-weight: bold;"><?php echo sprintfT('メンバー %s人', formatMember($oc['member'])) ?></span>
                <?php else : ?>
                  <span><?php echo sprintfT('メンバー %s人', formatMember($oc['member'])) ?></span>
                <?php endif ?>
                <?php if ($oc['table_name'] === AppConfig::RANKING_HOUR_TABLE_NAME) : ?>
                  <span aria-hidden="true" style="font-size: 9px; user-select: none;">🔥</span>
                <?php endif ?>
                <?php if ($oc['table_name'] === AppConfig::RANKING_DAY_TABLE_NAME) : ?>
                  <span aria-hidden="true" style="font-size: 9px; user-select: none;">🚀</span>
                <?php endif ?>
                <?php if ($oc['table_name'] === AppConfig::RANKING_WEEK_TABLE_NAME) : ?>
                  <svg class="MuiSvgIcon-root MuiSvgIcon-fontSizeMedium show-north css-162gv95" focusable="false" aria-hidden="true" viewBox="0 0 24 24" data-testid="NorthIcon">
                    <path d="m5 9 1.41 1.41L11 5.83V22h2V5.83l4.59 4.59L19 9l-7-7-7 7z"></path>
                  </svg>
                <?php endif ?>
              </span>
            <?php endif ?>
            <?php if (isset($oc['api_created_at']) && $showApiCreatedAt) : ?>
              <span class="registration-date"><?php echo t('ルーム開設') . ' ' . convertDatetime($oc['api_created_at'], false) ?></span>
            <?php endif ?>
          </div>
        </footer>
      </div>
    </li>
  <?php endforeach ?>
</ol>