<ol class="openchat-item-list unset">
  <?php /** @var \App\Services\Recommend\Dto\RecommendListDto $recommend */

  use App\Config\AppConfig;
  

  if (!isset($listArray)) {
    $listArray = $recommend->getList($shuffle ?? false, ($limit ?? null) ? AppConfig::$listLimitTopRanking : null, $id ?? 0);
  }

  $listLen = count($listArray);
  $showListMedal = $showListMedal ?? false;
  $currentCount = $currentCount ?? false;
  $showApiCreatedAt = $showApiCreatedAt ?? false;
  $hideIncrease = $hideIncrease ?? false;   // おすすめ系wrapper(recommend_list2/similar_size_rooms)は true で24h増加を隠す。/recommendタグページのみ既定falseで表示

  foreach ($listArray as $key => $oc) : ?>
    <?php $roomHref = \App\Services\Seo\OpenChatUrlNormalizer::roomUrl(
      (int)$oc['id'],
      (isset($oc['table_name']) && ($oc['table_name'] === AppConfig::RANKING_HOUR_TABLE_NAME || $oc['table_name'] === AppConfig::RANKING_DAY_TABLE_NAME)) ? ['limit' => 'hour'] : []
    ) ?>
    <li class="unset">
      <div class="openchat-item <?php if ($showListMedal && $key === 0) echo 'goldmedal';
                                elseif ($showListMedal && $key === 1) echo 'silvermedal';
                                elseif ($showListMedal && $key === 2) echo 'blonzemedal';
                                elseif ($currentCount && $currentCount + $key + 1 >= 100) echo 'hundred' ?>">
        <a class="link-overlay unset" href="<?php echo $roomHref ?>" tabindex="-1" aria-hidden="true">
          <span class="visually-hidden"><?php echo $oc['name'] ?></span>
        </a>
        <img alt="<?php echo $oc['name'] ?>" class="openchat-item-img" loading="lazy" src="<?php echo imgPreviewUrl($oc['img_url']) ?>">
        <h3 class="unset">
          <a class="openchat-item-title unset" href="<?php echo $roomHref ?>"><?php if (($oc['emblem'] ?? 0) === 1) : ?><span class="super-icon sp"></span><?php elseif (($oc['emblem'] ?? 0) === 2) : ?><span class="super-icon official"></span><?php endif ?><?php if (($oc['join_method_type'] ?? 0) === 2) : ?><span class="lock-icon"></span><?php endif ?><?php echo $oc['name'] ?></a>
        </h3>
        <p class="openchat-item-desc unset"><?php
                                            // 説明文は行の生成時に collapse + 40字 truncate 済み (RecommendRowFormat)。
                                            // decode は自動エスケープ経由(タグページ)と生値経由(/oc)の両方を原文へ正規化する。
                                            echo h(htmlspecialchars_decode($oc['desc40'] ?? '')) ?></p>
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
          <?php if ($diff24h > 0 && !$hideIncrease) : ?>
            <div class="positive" style="font-size: 13px; margin-top: 1px;"><span aria-hidden="true" style="font-size: 11px; user-select: none;">🚀</span> <span class="openchat-item-stats"><?php echo sprintfT('%s人増加', formatMember($diff24h)) ?></span><span style="font-size: 11px; color: var(--c-cool-text-weak); font-weight: normal; margin-left: 4px;">（<?php echo t('24時間') ?>）</span></div>
          <?php endif ?>
        </footer>
      </div>
    </li>
  <?php endforeach ?>
</ol>
