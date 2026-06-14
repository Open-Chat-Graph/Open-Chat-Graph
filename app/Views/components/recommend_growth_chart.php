<?php

use App\Services\Recommend\TagDefinition\Ja\RecommendUtility;
use App\Services\Recommend\ThemeGrowthChartSvg;

/**
 * テーマの勢い(最小構成): LINE公式「ランキング(活発なルーム)」での掲載部屋の最高順位の推移を、
 * ブランドロックアップ + 説明ラベル + スパークライン + 「推移を言語化した1行」だけで簡潔に示す。
 *
 * 順位は小さいほど良いので、スパークラインは値を反転して「順位が上がる=上に伸びる」向きに描く。
 * 改善(順位UP)=緑 / 悪化(順位DOWN)=赤橙 / 横ばい=灰。動きは平滑化せず誠実に表示する。
 * ランキング掲載が無いテーマはカード自体を出さない(member フォールバックは廃止)。
 *
 * @var array  $growth     spanDays / rank{points:{date,value}[],current,first,leaderId}
 * @var string $extractTag テーマ名(親 view でエスケープ済み)
 *
 * 制約: サーバー側でインライン SVG を生成。JS/Webフォント無し・高さ固定/img は width&height 明示で CLS ゼロ。
 *       preserveAspectRatio="none" の SVG は引き伸ばされるため <text> は使わない。
 */

$growth = isset($growth) && is_array($growth) ? $growth : [];
if (!$growth || empty($growth['rank'])) {
  return;
}

// タグ表示は略称優先(例: 「カラフルピーチ（からぴち）」→「からぴち」)。SEO用の正式名はページ側で使用。
$displayTag = RecommendUtility::extractTag($extractTag);

$rank   = $growth['rank']   ?? null;
$spanDays    = (int)($growth['spanDays'] ?? 0);
$rankCurrent = $rank !== null ? (int)($rank['current'] ?? 0) : 0;
$rankFirst   = $rank !== null ? (int)($rank['first'] ?? 0) : 0;
$rankPoints  = ($rank !== null && !empty($rank['points'])) ? $rank['points'] : [];

// 掲載が無い(順位が取れない)テーマはカードを出さない(フォールバックは廃止)。
if ($rankCurrent <= 0) {
  return;
}

/** 日数を人に伝わる丸めた期間表現に(例: 29→「1ヶ月」)。窓の長さに対応。 */
$periodLabel = static function (int $d): string {
  if ($d >= 75) return sprintfT('%sヶ月', (int)round($d / 30));
  if ($d >= 45) return t('2ヶ月');
  if ($d >= 24) return t('1ヶ月');
  if ($d >= 10) return t('2週間');
  if ($d >= 5)  return t('1週間');
  return sprintfT('%s日', max(1, $d));
};
$period = $periodLabel($spanDays);

$label = t('公式ランキングでの順位（活発なルーム）');
$improve = $rankFirst - $rankCurrent; // 正 = 順位が小さくなった = 改善
$chartPoints = $rankPoints;

// 順位を反転(値を負)して描く → 順位が良く(小さく)なるほど上に伸びる。
$chart = ThemeGrowthChartSvg::build(array_map(
  static fn($p) => ['date' => $p['date'], 'value' => -(int)$p['value']],
  $rankPoints
));

// 活発さティア(全体ランキング最高順位の絶対値で。/oc ナラティブ準拠)。
// 色は「下降の赤」「横ばいのくすんだ灰」をやめ、安心できる緑系で統一(上位すぎて張り付く=最高クラス
// なので肯定的に)。低位のときだけ落ち着いた灰。方向は文章で誠実に伝える。
// 全体ランキングに入っている時点で「活発なルーム」なので、基本は安心できる緑。順位帯で文言だけ強める。
$b = $rankCurrent;
if ($b <= 10)      $tierWord = t('オープンチャット全体を代表する規模の');
elseif ($b <= 50)  $tierWord = t('全体でも上位クラスの大規模な');
elseif ($b <= 300) $tierWord = t('全体ランキング上位の活発な');
else               $tierWord = t('全体ランキングに入る活発な');
$color = 'var(--c-brand)';

// 多言語対応: 語順が言語で異なるため位置指定子(%1$s..)のテンプレートにして翻訳側で並べ替え可能にする。
$trend = $improve > 0
  ? sprintfT('この%1$sで全体%2$s位→%3$s位に上昇', $period, number_format($rankFirst), number_format($rankCurrent))
  : ($improve < 0
    ? sprintfT('この%1$sで全体%2$s位→%3$s位', $period, number_format($rankFirst), number_format($rankCurrent))
    : sprintfT('この%1$sは全体%2$s位前後で安定', $period, number_format($rankCurrent)));

$line = $tierWord !== ''
  ? sprintfT('「%1$s」には%2$sルームがあります（%3$s）。', $displayTag, $tierWord, $trend)
  : sprintfT('「%1$s」の掲載部屋の最高順位は全体%2$s位（%3$s）。', $displayTag, number_format($rankCurrent), $trend);

// 「どの部屋の話か」を具体化: 最高順位を持つ部屋をリストから引き、サムネ+名前+順位+人数の
// 小さなリンクを出す。$recommend は view() の sanitizeObject で既にエスケープ済み。
// open_chat_list_recommend と同じく getList の値はエスケープ済みをそのまま echo する(ここで追加エスケープしないこと)。
$leader = null;
$leaderHref = '';
if (isset($recommend) && $recommend !== null) {
  $leaderId = (int)($rank['leaderId'] ?? 0);
  if ($leaderId > 0) {
    foreach ($recommend->getList(false, null) as $row) {
      if ((int)($row['id'] ?? 0) === $leaderId) {
        $leader = $row;
        $t = $row['table_name'] ?? '';
        $leaderHref = url('/oc/' . (int)$row['id'])
          . ($t === \App\Config\AppConfig::RANKING_HOUR_TABLE_NAME || $t === \App\Config\AppConfig::RANKING_DAY_TABLE_NAME ? '?limit=hour' : '');
        break;
      }
    }
  }
}

// x軸(時間)ラベル: チャート元データの開始日・終了日(M/D)。時間軸が無いと意味不明な絵になるため。
$axisStart = '';
$axisEnd = '';
if (!empty($chartPoints)) {
  $axisStart = (new DateTime((string)$chartPoints[0]['date']))->format('n/j');
  $axisEnd   = (new DateTime((string)$chartPoints[count($chartPoints) - 1]['date']))->format('n/j');
}

// SVG の defs id は1ページ内ユニークに。
$gid = 'ocg-grad-' . substr(md5($extractTag . '|rank|' . $spanDays), 0, 8);

// ブランドアイコン(96x96・web最適化済み)。width/height 明示で CLS ゼロ。
$lineIcon = fileUrl('assets/line_app_icon.png', urlRoot: '');
$ocIcon   = fileUrl('assets/openchat_icon.png', urlRoot: '');
?>
<section class="recommend-growth" style="--rg-color: <?php echo $color ?>;" aria-label="<?php echo $line ?>">
  <div class="recommend-growth__head">
    <span class="recommend-growth__brand">
      <span class="recommend-growth__brandmarks">
        <img class="recommend-growth__brandimg recommend-growth__brandimg--line" src="<?php echo $lineIcon ?>" width="20" height="20" alt="LINE" decoding="async" />
        <img class="recommend-growth__brandimg recommend-growth__brandimg--oc" src="<?php echo $ocIcon ?>" width="20" height="20" alt="<?php echo t('オープンチャット') ?>" decoding="async" />
      </span>
      <span class="recommend-growth__brandtext"><?php echo t('LINE オープンチャット') ?></span>
    </span>
  </div>

  <p class="recommend-growth__title"><?php echo t('テーマの勢い') ?></p>
  <p class="recommend-growth__metric"><?php echo $label ?></p>

  <?php if ($chart !== null) : ?>
    <div class="recommend-growth__heroplot">
      <div class="recommend-growth__chart">
        <svg class="recommend-growth__svg" viewBox="0 0 <?php echo $chart['width'] ?> <?php echo $chart['height'] ?>" width="100%" height="<?php echo $chart['height'] ?>" preserveAspectRatio="none" role="img" aria-hidden="true" focusable="false">
          <defs>
            <linearGradient id="<?php echo $gid ?>" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" style="stop-color: <?php echo $color ?>;" stop-opacity="0.28" />
              <stop offset="100%" style="stop-color: <?php echo $color ?>;" stop-opacity="0.02" />
            </linearGradient>
          </defs>
          <path d="<?php echo $chart['areaPath'] ?>" fill="url(#<?php echo $gid ?>)" />
          <path d="<?php echo $chart['linePath'] ?>" fill="none" style="stroke: <?php echo $color ?>;" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />
        </svg>
        <span class="recommend-growth__dot" aria-hidden="true" style="left: <?php echo round($chart['lastX'] / $chart['width'] * 100, 2) ?>%; top: <?php echo round($chart['lastY'] / $chart['height'] * 100, 2) ?>%;"></span>
      </div>
      <?php if ($axisStart !== '') : ?>
        <div class="recommend-growth__axis" aria-hidden="true">
          <span class="recommend-growth__axis-from"><?php echo $axisStart ?></span>
          <span class="recommend-growth__axis-to"><?php echo sprintfT('今（%s）', $axisEnd) ?></span>
        </div>
      <?php endif ?>
    </div>
  <?php endif ?>

  <p class="recommend-growth__line"><?php echo $line ?></p>

  <?php if ($leader !== null) : ?>
    <a class="recommend-growth__leader" href="<?php echo $leaderHref ?>">
      <img class="recommend-growth__leader-img" src="<?php echo imgPreviewUrl($leader['img_url']) ?>" width="36" height="36" alt="" loading="lazy" decoding="async" />
      <span class="recommend-growth__leader-body">
        <span class="recommend-growth__leader-name"><?php echo $leader['name'] ?></span>
        <span class="recommend-growth__leader-meta"><?php
          echo isset($leader['member'])
            ? sprintfT('全体%1$s位・メンバー%2$s人', number_format($rankCurrent), formatMember((int)$leader['member']))
            : sprintfT('全体%s位', number_format($rankCurrent));
        ?></span>
      </span>
      <span class="recommend-growth__leader-arrow" aria-hidden="true">›</span>
    </a>
  <?php endif ?>
</section>
