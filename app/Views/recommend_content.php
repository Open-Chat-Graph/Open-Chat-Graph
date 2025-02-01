<!DOCTYPE html>
<html lang="<?php echo t('ja') ?>">
<?php

use App\Config\AppConfig;
use App\Views\Ads\GoogleAdsence as GAd;

/** @var \App\Services\StaticData\Dto\StaticRecommendPageDto $_dto */

$_tagIndex = htmlspecialchars_decode($tag);
if (isset($_dto->tagRecordCounts[$_tagIndex])) {
  $countTitle = sprintfT('TOP%s', $count);
} else {
  $countTitle = '';
}

viewComponent('head', compact('_css', '_schema', 'canonical') + ['_meta' => $_meta->generateTags(true), 'titleP' => true]) ?>

<body>
  <!-- 固定ヘッダー -->
  <?php viewComponent('site_header') ?>
  <article class="ranking-page-main pad-side-top-ranking body" style="overflow: hidden; padding-top: 0;">
    <?php GAd::output(GAd::AD_SLOTS['recommendTopHorizontal']) ?>

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
          <?php echo t('【最新】') . sprintfT("「%s」おすすめオープンチャットランキング", $tag) ?><?php echo $countTitle ?? '' ?>
        </h1>
      </div>

      <?php if (isset($recommend)) : ?>
        <figure class="talkroom_banner_img_figure">
          <?php $oc = $recommend->getPreviewList(1)[0] ?>
          <figcaption><?php echo sprintfT('「%s」のメイン画像', $oc['name']) ?></figcaption>
          <div class="talkroom_banner_img_area">
            <img class="talkroom_banner_img" aria-hidden="true" alt="<?php echo $oc['name'] ?>" src="<?php echo imgUrl($oc['id'], $oc['img_url']) ?>">
          </div>
        </figure>
      <?php endif ?>

    </section>

    <p class="recommend-header-desc desc-bottom">
      <?php echo sprintfT('「%s」に関する人気のオープンチャットをピックアップ！🙌', $extractTag) ?><br>
      <span class="desc-aside"><?php echo t('ランキングは、直近の人数増加を反映して決定されています。') ?></span>
    </p>

    <?php if (isset($recommend)) : ?>
      <header class="recommend-ranking-section-header" style="padding: 0 0 10px 16px;">
        <aside class="list-aside">
          <details class="icon-desc">
            <summary style="font-size: 13px; font-weight: normal; color: #b7b7b7"><?php echo t('人数増加アイコンの説明') ?></summary>
            <div class="list-aside-details">
              <small class="list-aside-desc">🔥：<?php echo sprintfT('過去1時間で%s人以上増加', AppConfig::RECOMMEND_MIN_MEMBER_DIFF_HOUR) ?></small>
              <small class="list-aside-desc">🚀：<?php echo sprintfT('過去24時間で%s人以上増加', AppConfig::RECOMMEND_MIN_MEMBER_DIFF_H24) ?></small>
              <small class="list-aside-desc">
                <span style="margin: 0 4px;">
                  <svg class="MuiSvgIcon-root MuiSvgIcon-fontSizeMedium show-north css-162gv95" focusable="false" aria-hidden="true" viewBox="0 0 24 24" data-testid="NorthIcon">
                    <path d="m5 9 1.41 1.41L11 5.83V22h2V5.83l4.59 4.59L19 9l-7-7-7 7z"></path>
                  </svg>
                </span>：<?php echo sprintfT('過去1週間で%s人以上増加', AppConfig::RECOMMEND_MIN_MEMBER_DIFF_WEEK) ?>
              </small>
              <small class="list-aside-desc">🏆：<?php echo t('リスト内で最も人数が多いトークルーム') ?></small>
            </div>
          </details>
        </aside>
      </header>
    <?php endif ?>
    <section class="recommend-ranking-section">
      <?php if (isset($recommend)) : ?>
        <ol class="openchat-item-list parent unset" style="counter-reset: openchat-counter2 <?php echo $count + 1 ?>;">
          <?php
          $chunkLen = 5;
          $reverseList = array_reverse($recommend->getList(false, null));
          $firstLists = array_slice($reverseList, 0, $chunkLen);
          $secondLists = array_chunk(array_slice($reverseList, $chunkLen), $chunkLen);
          $lists = [$firstLists, ...$secondLists];
          $listsLastKey = count($lists) - 1;
          $currentCount = 0;
          ?>
          <?php foreach ($lists as $key => $listArray) : ?>
            <li class="top-ranking" style="padding-top: 8px; <?php echo $key ? 'gap: 8px;' : 'gap: 8px;' ?>">
              <header class="recommend-ranking-section-header">
                <h2 style="all: unset; font-size: 15px; font-weight: bold; color: #111; display: flex; flex-direction:row; flex-wrap:wrap; line-height: 1.3;">
                  <div><?php echo sprintfT("「%s」おすすめオープンチャットランキング", $extractTag) ?></div>
                  <div>&nbsp;<?php echo sprintfT('%s位', $count - $currentCount) ?>〜 (<?php echo $hourlyUpdatedAt->format('G:i') ?>)</div>
                </h2>
              </header>
              <?php if ($key === 0) : ?>
              <?php else : ?>
              <?php endif ?>
              <?php $currentCount += count($listArray) ?>

              <?php viewComponent('open_chat_list_recommend', compact('recommend', 'listArray') + ['showReverseListMedal' => ($count - $currentCount) === 0, 'hundred' => ($count - $currentCount + $chunkLen) === 100]) ?>
              <?php if ($listsLastKey === $key && isset($_dto->tagRecordCounts[$_tagIndex]) && ((int)$_dto->tagRecordCounts[$_tagIndex]) > $count) : ?>
                <a class="top-ranking-readMore unset ranking-url white-btn" href="<?php echo url('ranking?keyword=' . urlencode('tag:' . $_tagIndex)) ?>">
                  <span class="ranking-readMore" style="font-size: 11.5px;"><?php echo sprintfT('「%s」をすべて見る', $tag) ?><span class="small" style="font-size: 11.5px;"><?php echo sprintfT('%s件', $_dto->tagRecordCounts[$_tagIndex]) ?></span></span>
                </a>
              <?php endif ?>
            </li>
            <?php if ($listsLastKey !== $key) : ?>
              <li>

                <?php GAd::output(GAd::AD_SLOTS[$key
                  ? (
                    ($listsLastKey - 1 === $key) ? 'recommendSeparatorRectangle' : 'recommendSeparatorWide'
                  )
                  : 'recommendThirdRectangle']) ?>

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

      <aside class="list-aside recommend-ranking-bottom" style="padding-top: 0; margin-bottom: 0;">
        <?php if (isset($recommend)) : ?>
          <?php viewComponent('recommend_content_tags', ['tags' => $recommend->getFilterdTags(false, null), 'tag' => $tag]) ?>
        <?php endif ?>
      </aside>
      <?php GAd::output(GAd::AD_SLOTS['recommendSeparatorRectangle'])
      ?>

    </section>

    <?php //GAd::output(GAd::AD_SLOTS['recommendListBottomWide']) 
    ?>

    <aside class="top-ranking-list-aside">
      <?php viewComponent('topic_tag', compact('topPageDto')) ?>
    </aside>

    <aside class="top-ranking-list-aside">
      <?php viewComponent('top_ranking_comment_list_hour', ['dto' => $topPageDto]) ?>
    </aside>

    <?php viewComponent('footer_inner',  ['adSlot' => 'recommendBottomWide']) ?>

  </article>

  <?php \App\Views\Ads\GoogleAdsence::loadAdsTag() ?>

  <script defer src="<?php echo fileurl("/js/site_header_footer.js", urlRoot: '') ?>"></script>

  <?php echo $_breadcrumbsShema ?>
</body>

</html>