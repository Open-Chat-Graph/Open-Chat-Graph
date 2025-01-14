<!DOCTYPE html>
<html lang="<?php echo t('ja_JP') ?>">
<?php

use App\Config\AppConfig;
use App\Views\Ads\GoogleAdsence as GAd;

/** @var \App\Services\StaticData\Dto\StaticRecommendPageDto $_dto */

$_tagIndex = htmlspecialchars_decode($tag);
if (isset($_dto->tagRecordCounts[$_tagIndex])) {
  $countTitle = ((int)$_dto->tagRecordCounts[$_tagIndex]) > $count ? 'TOP' . $count : '全' . $count . '件';
} else {
  $countTitle = '';
}

viewComponent('head', compact('_css', '_schema', 'canonical') + ['_meta' => $_meta->generateTags(true), 'titleP' => true]) ?>

<body>
  <!-- 固定ヘッダー -->
  <?php viewComponent('site_header') ?>
  <article class="ranking-page-main pad-side-top-ranking body" style="overflow: hidden; padding-top: 0;">

    <section class="recommend-header-wrapper">

      <div class="recommend-header-bottom">
        <div class="recommend-data-desc">統計に基づくランキング</div>
        <?php if (isset($hourlyUpdatedAt)) : ?>
          <div class="recommend-header-time">
            <time datetime="<?php echo $hourlyUpdatedAt->format(\DateTime::ATOM) ?>"><?php echo $hourlyUpdatedAt->format('Y年n月j日 G:i') ?></time>
          </div>
        <?php endif ?>
      </div>
      <hr class="hr-top">

      <div class="recommend-header-desc-wrapper">
        <p class="recommend-header-desc-text">
          「<?php echo $tag ?>」のおすすめオープンチャットランキングを発表！<?php if (isset($hourlyUpdatedAt)) echo '（' . $hourlyUpdatedAt->format('n/j G:i') . '時点）' ?>
        </p>
      </div>

      <?php if (isset($recommend)) : ?>
        <figure class="talkroom_banner_img_figure">
          <?php $oc = $recommend->getPreviewList(1)[0] ?>
          <div class="talkroom_banner_img_area">
            <img class="talkroom_banner_img" aria-hidden="true" alt="<?php echo $oc['name'] ?>" src="<?php echo imgUrl($oc['id'], $oc['img_url']) ?>">
          </div>
          <figcaption>「<?php echo $oc['name'] ?>」のメイン画像</figcaption>
        </figure>
      <?php endif ?>

    </section>

    <p class="recommend-header-desc desc-bottom">
      ランキングの順位は、参加人数がどれぐらい上昇しているかによって決まります。
    </p>

    <section class="recommend-ranking-section">
      <?php if (isset($recommend)) : ?>
        <ol class="openchat-item-list parent unset">
          <?php
          $chunkLen = 10;
          $lists = array_chunk($recommend->getList(false, null), $chunkLen);
          $listsLastKey = count($lists) - 1;
          ?>
          <?php foreach ($lists as $key => $listArray) : ?>
            <li class="top-ranking" style="padding-top: 8px; <?php if (!$key) echo 'gap: 0;' ?>">
              <?php if ($key === 0) : ?>
                <header class="recommend-ranking-section-header">
                  <h2 style="all: unset; font-size: 18px; font-weight: bold; color: #111; display: flex; flex-direction:row; flex-wrap:wrap; line-height: 1.7;">
                    <div>「<?php echo $recommend->listName ?>」</div>
                    <div>おすすめランキング</div>
                    <div><?php echo $countTitle ?? '' ?></div>
                    <div>（<?php echo $hourlyUpdatedAt->format('n/j G:i') ?>時点）</div>
                  </h2>
                </header>
                <header class="recommend-ranking-section-header" style="padding: 5px 0 16px 0;">
                  <aside class="list-aside">
                    <details class="icon-desc">
                      <summary style="font-size: 13px; font-weight: normal;">人数増加アイコンの説明</summary>
                      <div class="list-aside-details">
                        <small class="list-aside-desc">🔥：過去1時間で<?php echo AppConfig::RECOMMEND_MIN_MEMBER_DIFF_HOUR ?>人以上増加<?php if (count($recommend->hour) >= AppConfig::LIST_LIMIT_RECOMMEND) : ?> (<?php echo AppConfig::LIST_LIMIT_RECOMMEND ?>件まで)<?php endif ?></small>
                        <small class="list-aside-desc">🚀：過去24時間で<?php echo AppConfig::RECOMMEND_MIN_MEMBER_DIFF_H24 ?>人以上増加<?php if (count($recommend->day) >= AppConfig::LIST_LIMIT_RECOMMEND) : ?> (<?php echo AppConfig::LIST_LIMIT_RECOMMEND ?>件まで)<?php endif ?></small>
                        <small class="list-aside-desc">
                          <span style="margin: 0 4px;">
                            <svg class="MuiSvgIcon-root MuiSvgIcon-fontSizeMedium show-north css-162gv95" focusable="false" aria-hidden="true" viewBox="0 0 24 24" data-testid="NorthIcon">
                              <path d="m5 9 1.41 1.41L11 5.83V22h2V5.83l4.59 4.59L19 9l-7-7-7 7z"></path>
                            </svg>
                          </span>：過去1週間で<?php echo AppConfig::RECOMMEND_MIN_MEMBER_DIFF_WEEK ?>人以上増加<?php if (count($recommend->week) >= AppConfig::LIST_LIMIT_RECOMMEND) : ?> (上位<?php echo AppConfig::LIST_LIMIT_RECOMMEND ?>件まで)<?php endif ?>
                        </small>
                        <small class="list-aside-desc">🏆：リスト内で最も人数が多いトークルーム</small>
                      </div>
                    </details>
                  </aside>
                </header>
              <?php else : ?>
                <header class="recommend-ranking-section-header">
                  <h2 style="all: unset; font-size: 18px; font-weight: bold; color: #111; display: flex; flex-direction:row; flex-wrap:wrap; line-height: 1.7;">
                    <div>「<?php echo $recommend->listName ?>」</div>
                    <div>おすすめランキング</div>
                    <div><?php echo $countTitle ?? '' ?></div>
                    <div>（<?php echo $hourlyUpdatedAt->format('n/j G:i') ?>時点）<?php echo $key * $chunkLen + 1 ?>位〜</div>
                  </h2>
                </header>
              <?php endif ?>
              <?php viewComponent('open_chat_list_recommend', compact('recommend', 'listArray')) ?>
              <?php if ($listsLastKey === $key && isset($_dto->tagRecordCounts[$_tagIndex]) && ((int)$_dto->tagRecordCounts[$_tagIndex]) > $count) : ?>
                <a class="top-ranking-readMore unset ranking-url white-btn" href="<?php echo url('ranking?keyword=' . urlencode('tag:' . $_tagIndex)) ?>">
                  <span class="ranking-readMore" style="font-size: 11.5px;">「<?php echo $tag ?>」をすべて見る<span class="small" style="font-size: 11.5px;"><?php echo $_dto->tagRecordCounts[$_tagIndex] ?>件</span></span>
                </a>
                <hr class="hr-bottom" style="width: 100%;">
              <?php endif ?>
            </li>
            <?php if ($listsLastKey !== $key) : ?>
              <li>

                <?php GAd::output(GAd::AD_SLOTS['recommendSeparatorRectangle']) ?>

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
          <hr class="hr-bottom" style="width: 100%;">
        <?php endif ?>
      </aside>

    </section>

    <aside class="top-ranking-list-aside">
      <?php viewComponent('topic_tag', compact('topPageDto')) ?>
    </aside>

    <aside class="top-ranking-list-aside">
      <?php viewComponent('top_ranking_comment_list_hour', ['dto' => $topPageDto]) ?>
    </aside>

    <aside class="top-ranking-list-aside">
      <?php viewComponent('top_ranking_comment_list_hour24', ['dto' => $topPageDto]) ?>
    </aside>

    <aside class="top-ranking-list-aside">
      <?php viewComponent('top_ranking_comment_list_week', ['dto' => $topPageDto]) ?>
    </aside>

    <?php GAd::output(GAd::AD_SLOTS['recommendSeparatorRectangle']) ?>

    <footer class="footer-elem-outer">
      <?php viewComponent('footer_share_nav', ['title' => $_meta->title]) ?>
      <?php viewComponent('footer_inner') ?>
    </footer>

  </article>

  <?php \App\Views\Ads\GoogleAdsence::loadAdsTag() ?>

  <script defer src="<?php echo fileurl("/js/site_header_footer.js") ?>"></script>

  <?php echo $_breadcrumbsShema ?>
</body>

</html>