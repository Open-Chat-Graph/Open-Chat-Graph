<!DOCTYPE html>
<html lang="<?php echo t('ja') ?>">
<?php

use App\Config\AppConfig;
use App\Views\Ads\GoogleAdsense as GAd;
use Shared\MimimalCmsConfig;

/** @var \App\Services\StaticData\Dto\StaticRecommendPageDto $_dto */
/** @var \App\Services\Recommend\Dto\RecommendListDto $recommend */

$_tagIndex = htmlspecialchars_decode($tag);
if (isset($_dto->tagRecordCounts[$_tagIndex])) {
  $countTitle = sprintfT('TOP%s', $count);
} else {
  $countTitle = '';
}
$hourlyUpdatedAt = $hourlyUpdatedAt ?? new DateTime();
$hourlyUpdatedAt->setTimezone(new DateTimeZone(AppConfig::DATE_TIME_ZONE[MimimalCmsConfig::$urlRoot]));

$enableAdsense = true;

viewComponent('head', compact('_css', '_schema', 'canonical') + ['_meta' => $_meta->generateTags(true), 'titleP' => true, 'dataOverlays' => 'bottom']) ?>

<body>
  <?php viewComponent('site_header') ?>
  <article class="ranking-page-main pad-side-top-ranking body" style="overflow: hidden; padding-top: 0;">

    <section class="recommend-header-wrapper">

      <div class="recommend-header-bottom" style="padding-top: 8px;">
        <div class="recommend-data-desc"><?php echo t('統計に基づくランキング') ?></div>
        <?php if (isset($hourlyUpdatedAt)) : ?>
          <div class="recommend-header-time">
            <time datetime="<?php echo $hourlyUpdatedAt->format(\DateTime::ATOM) ?>"><?php echo $hourlyUpdatedAt->format(t('Y年n月j日 G:i')) ?></time>
          </div>
        <?php endif ?>
      </div>

      <hr class="hr-top recommend">

      <div class="recommend-header-desc-wrapper">
        <h1 class="recommend-header-desc-text">
          <?php echo sprintfT('いま伸びている「%s」のオープンチャット', $tag) ?><?php echo $countTitle ? ' ' . $countTitle : '' ?>
        </h1>
      </div>

      <section class="recommend-lead">
        <?php if (!empty($tagDescription)) : ?>
          <p class="recommend-lead__theme"><?php echo $tagDescription // View が自動でhtmlエスケープ済み ?></p>
        <?php else : ?>
          <p class="recommend-lead__main"><?php echo sprintfT('「%s」のLINEオープンチャットの一覧です。メンバー数が今いちばん伸びている部屋を、毎時更新で上位に表示します。', $tag) ?></p>
        <?php endif ?>
      </section>

      <?php viewComponent('recommend_growth_chart', [
        'growth' => $growth ?? [],
        'extractTag' => $extractTag,
        'recommend' => $recommend ?? null,
      ]) ?>

    </section>
    <section class="recommend-ranking-section">
      <?php if (isset($recommend)) : ?>
        <?php if ($enableAdsense): ?>
          <?php \App\Views\Ads\GoogleAdsense::gTag() ?>
        <?php endif ?>
        <ol class="openchat-item-list parent unset">
          <?php
          $chunkLen = 5;
          $recommendList = $recommend->getList(false, AppConfig::LIST_LIMIT_RECOMMEND);
          $firstLists = array_slice($recommendList, 0, $chunkLen);
          $secondLists = array_chunk(array_slice($recommendList, $chunkLen), $chunkLen * 2);
          $lists = [$firstLists, ...$secondLists];
          $listsLastKey = count($lists) - 1;
          $currentCount = 0;
          ?>
          <?php foreach ($lists as $key => $listArray) : ?>
            <li class="top-ranking" style="padding-top: 8px; <?php echo $key ? 'gap: 8px;' : 'gap: 8px;' ?>">
              <header class="recommend-ranking-section-header">
                <?php if ($key > 0) : ?>
                  <?php // 同一H2の反復(量産シグナル)を避け、継続チャンクは見出しではなく軽い順位区切りにする ?>
                  <div class="recommend-ranking-continue"><?php echo sprintfT('%s位', $currentCount + 1) ?>〜</div>
                <?php else : ?>
                  <h2 style="all: unset; font-size: 15px; font-weight: bold; color: #111; display: flex; flex-direction:row; flex-wrap:wrap; line-height: 1.3;">
                    <div><?php echo sprintfT("「%s」でいま人数が伸びているルーム", $extractTag) ?>&nbsp;</div>
                    <div>(<?php echo $hourlyUpdatedAt->format('G:i') ?>)</div>
                  </h2>
                <?php endif ?>
              </header>
              <?php $currentListCount = count($listArray) ?>
              <?php $currentCount += $currentListCount ?>

              <?php viewComponent('open_chat_list_recommend', compact('recommend', 'listArray') + ['showListMedal' => $currentCount - $currentListCount === 0, 'currentCount' => $currentCount - $currentListCount, 'showApiCreatedAt' => true]) ?>

              <?php if ($listsLastKey === $key && isset($_dto->tagRecordCounts[$_tagIndex]) && ((int)$_dto->tagRecordCounts[$_tagIndex]) > $count) : ?>
                <hr class="hr-top" style="margin: 1rem auto;">
                <a class="top-ranking-readMore unset ranking-url white-btn" href="<?php echo url('ranking?keyword=' . urlencode('tag:' . $_tagIndex)) ?>">
                  <span class="ranking-readMore" style="font-size: 11.5px;"><?php echo sprintfT('「%s」をすべて見る', $tag) ?><span class="small" style="font-size: 11.5px;"><?php echo sprintfT('%s件', $_dto->tagRecordCounts[$_tagIndex]) ?></span></span>
                </a>
              <?php endif ?>

            </li>
            <?php if ($key === 0) : ?>
              <li>
                <?php GAd::output('recommendSeparatorResponsive', true) ?>
              </li>
            <?php elseif ($listsLastKey !== $key) : ?>
              <li>
                <?php GAd::output('recommendSeparatorResponsive', true) ?>
              </li>
            <?php endif ?>
          <?php endforeach ?>
        </ol>
      <?php else : ?>
        <section class="top-ranking recommend-ranking-section">
          <header class="recommend-ranking-section-header">
            <h2 class="list-title oc-list">只今サーバー内でリスト更新中です…</h2>
          </header>
        </section>
      <?php endif ?>

    </section>

    <?php if (isset($_discovery) && !$_discovery->isEmpty()) : ?>
      <?php viewComponent('theme_discovery', ['discovery' => $_discovery]) ?>
    <?php endif ?>

    <?php viewComponent('footer_inner') ?>

  </article>

  <?php \App\Views\Ads\GoogleAdsense::loadAdsTag() ?>

  <script defer src="<?php echo fileurl("/js/site_header_footer.js", urlRoot: '') ?>"></script>

  <?php if ($enableAdsense): ?>
    <script defer src="<?php echo fileurl("/js/security.js", urlRoot: '') ?>"></script>
  <?php endif ?>

  <?php echo $_breadcrumbsShema ?>
</body>

</html>