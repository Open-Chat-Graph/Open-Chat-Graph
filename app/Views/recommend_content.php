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

      <?php // シェア導線（oc ページと共通のコンポーネント）。共有リンクの og:image は
            // テーマ専用の動的カード(/recommend/{tag}/card)で展開される。
            // 余白は実測で上下とも28pxに揃える: 上=勢いグラフの margin-bottom 28px（自前の上マージンは0）、
            // 下=見出しまで素で8pxしか無いので margin-bottom 20px を足して 28px にする ?>
      <?php viewComponent('share_buttons', [
        '_shareUrl' => $canonical,
        '_shareGa' => ['content_type' => 'recommend', 'item_id' => htmlspecialchars_decode($tag)],
        '_shareStyle' => 'margin: 0 1rem 20px',
      ]) ?>

    </section>
    <section class="recommend-ranking-section">
      <?php if (isset($recommend)) : ?>
        <?php if ($enableAdsense): ?>
          <?php GAd::gTag() ?>
        <?php endif ?>
        <?php // 手動広告(recommendSeparatorResponsive)は撤去済み: impRPM¥20=自動広告(¥220)の1/11なのに
              // リストを分断していた(2026-06実測)。広告挿入のためだけだったチャンク分割も廃止し30件を一本のリストで表示 ?>
        <ol class="openchat-item-list parent unset">
          <li class="top-ranking" style="padding-top: 8px; gap: 8px;">
            <header class="recommend-ranking-section-header">
              <h2 style="all: unset; font-size: 15px; font-weight: bold; color: var(--c-text-1); display: flex; flex-direction:row; flex-wrap:wrap; line-height: 1.3;">
                <div><?php echo sprintfT("「%s」でいま人数が伸びているルーム", $extractTag) ?>&nbsp;</div>
                <div>(<?php echo $hourlyUpdatedAt->format('G:i') ?>)</div>
              </h2>
            </header>
            <?php $listArray = $recommend->getList(false, AppConfig::LIST_LIMIT_RECOMMEND) ?>

            <?php // top5 の下（高視認位置）に広告を1枠。自動広告オフに伴い、以前ここにあった枠を復活させる。
                  // リストを top5 と残りに分割し間に挿入する（未使用スロット recommendSeparatorResponsive を流用）。
                  // currentCount で残りチャンクの連番を継続。広告オフ/5件以下では従来どおり一本のリスト。 ?>
            <?php if ($enableAdsense && count($listArray) > 5) : ?>
              <?php viewComponent('open_chat_list_recommend', ['recommend' => $recommend, 'listArray' => array_slice($listArray, 0, 5), 'showListMedal' => true, 'currentCount' => 0, 'showApiCreatedAt' => true]) ?>
              <?php // 広告だけはルーム行の左右1remインデントの外へ出し、コンテナ幅いっぱいで表示する
                    // （CSS .top-ranking > .responsive-google-parent の負マージンで相殺）。full-width-responsive=false のまま。 ?>
              <?php GAd::output('recommendSeparatorResponsive', false) ?>
              <?php viewComponent('open_chat_list_recommend', ['recommend' => $recommend, 'listArray' => array_slice($listArray, 5), 'showListMedal' => false, 'currentCount' => 5, 'showApiCreatedAt' => true]) ?>
            <?php else : ?>
              <?php viewComponent('open_chat_list_recommend', compact('recommend', 'listArray') + ['showListMedal' => true, 'currentCount' => 0, 'showApiCreatedAt' => true]) ?>
            <?php endif ?>

            <?php if (isset($_dto->tagRecordCounts[$_tagIndex]) && ((int)$_dto->tagRecordCounts[$_tagIndex]) > $count) : ?>
              <a class="top-ranking-readMore unset ranking-url white-btn" style="margin-top: 1rem;" href="<?php echo url('ranking?keyword=' . urlencode('tag:' . $_tagIndex)) ?>">
                <span class="ranking-readMore" style="font-size: 11.5px;"><?php echo sprintfT('「%s」をすべて見る', $tag) ?><span class="small" style="font-size: 11.5px;"><?php echo sprintfT('%s件', $_dto->tagRecordCounts[$_tagIndex]) ?></span></span>
              </a>
            <?php endif ?>

          </li>
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

    <?php // フッター前の横長枠は撤去: 視認率15%/CTR0.03%（AdSense実測）＝90%到達0.7%で「見られない死に枠」。
          // 収益は top5 直下の枠（本題直後・到達7.1%）に集約する。 ?>

    <?php viewComponent('footer_inner') ?>

  </article>

  <?php \App\Views\Ads\GoogleAdsense::loadAdsTag() ?>

  <script defer src="<?php echo fileurl("/js/site_header_footer.js", urlRoot: '') ?>"></script>

  <?php if ($enableAdsense): ?>
    <?php viewComponent('ad_guard') ?>
  <?php endif ?>

  <?php echo $_breadcrumbsShema ?>
</body>

</html>