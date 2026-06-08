<?php

declare(strict_types=1);

namespace App\Services\Alpha;

use App\Models\ApiRepositories\Alpha\AlphaInsightsRepository;
use App\Models\Repositories\OcNarrativeRepositoryInterface;

/**
 * α 個別ルーム詳細の「高次の考察」生成サービス。
 *
 * ## 設計方針 (本家 OcNarrativeService との違い)
 * - 本家 narrative は SEO / 初心者向けの「現在値・期間増減の言い換え」を含む。
 *   α 詳細ページは既に メンバー数・1h/24h/1w 増減・推移グラフ・掲載履歴 を数値で持っているので、
 *   それらを見れば一目で分かる事実は **出さない** (ユーザー却下済)。
 * - ここで出すのは「一目では分からない高次の洞察」を意味のある 3 軸に集約したもの:
 *     1. momentum          : 勢い — 直近7日の純増を主役に、平常ペース比＋成長ランキング順位を統合
 *     2. rank_position     : 公式ランキングでの位置 — 現在順位を主役に、30日推移＋自己最高位を統合
 *     3. category_position : カテゴリ内での位置 — カテゴリ内順位を主役に、シェアを統合
 *
 * - 各軸は最大 1 枚。データが無ければ黙ってスキップ (結果は 1〜3 枚)。
 * - 優先度 (配列順): momentum → rank_position → category_position。
 *
 * ## 嘘を出さない安全策
 * - すべて try/catch。データ不足は「黙る」(その洞察を配列に入れない)。
 * - sample_n / 観測日数が閾値未満なら順位・ペース系は出さない (ノイズ防止)。
 * - 非掲載 (ランキング外) で順位データが無いものは順位系を出さない。
 * - 出力は構造化 (type + 数値フィールド + 補助 text)。text は ja 前提の素朴な日本語。
 *   多言語は将来 t() 化する余地を残すが、α は ja 専用なので素の文字列で返す。
 *
 * ## 戻り値
 *  array{ insights: InsightItem[], generatedAt: string }
 *  InsightItem = array{ type: string, text: string, ...数値フィールド }
 */
class AlphaInsightsService
{
    /** 順位系を出す最低観測日数 (これ未満はノイズ) */
    private const MIN_POSITION_SAMPLE_N = 5;

    /** 全体順位の「変化した」とみなす最小順位差 */
    private const POSITION_MOVE_MIN_DELTA = 3;

    /**
     * 総合ランキング順位を語る上限（これより下位＝数千位の上下はノイズなので黙る）。
     * 全オープンチャット中で“上位”と言える範囲のときだけ総合順位の推移/最高位を出す。
     */
    private const MEANINGFUL_POSITION_MAX = 300;

    /** カテゴリ順位を「上位」として語る上限パーセンタイル */
    private const CATEGORY_TOP_PCT = 25.0;

    /** カテゴリシェアを言及する下限 (%) */
    private const CATEGORY_SHARE_MIN_PCT = 1.0;

    /** カテゴリ文脈を出す最低カテゴリ規模 (小さすぎる母集団は順位に意味がない) */
    private const MIN_CATEGORY_TOTAL = 20;

    /** 勢いを出す最低観測日数 (これ未満はノイズ) */
    private const MOMENTUM_MIN_SAMPLE_N = 7;

    /** 勢いを出す: 直近 7 日の最低実増加の下限 (curr*割合 と比較して大きい方を採用) */
    private const MOMENTUM_MIN_DIFF7_FLOOR = 15;

    /** 勢いを出す: 直近 7 日の純増が現在人数のこの割合以上 (微増を弾く) */
    private const MOMENTUM_MIN_DIFF7_RATE = 0.005;

    /** 勢いの「加速」文を出す: 直近ペースが過去平均ペースの何倍以上か */
    private const MOMENTUM_ACCEL_RATIO = 2.0;

    /** 成長ランキングを添える上限順位 (これ以内なら 2 文目に添える) */
    private const GROWTH_RANK_MENTION_MAX = 50;

    public function __construct(
        private OcNarrativeRepositoryInterface $narrativeRepository,
        private AlphaInsightsRepository $insightsRepository,
    ) {
    }

    /**
     * @param int   $openChatId
     * @param ?int  $category open_chat.category (null/0 ならカテゴリ文脈は出さない)
     * @return array{insights: array<int, array<string, mixed>>, generatedAt: string}
     */
    public function generate(int $openChatId, ?int $category): array
    {
        $insights = [];

        try {
            $metrics = $this->narrativeRepository->getMemberMetrics($openChatId);
        } catch (\Throwable $e) {
            $metrics = null;
        }

        $curr = ($metrics && $metrics['curr'] !== null) ? (int)$metrics['curr'] : null;

        // ---- 軸1: 勢い (直近7日の純増 + 平常ペース比 + 成長ランキング順位) ----
        if ($metrics !== null && $curr !== null) {
            $this->buildMomentum($insights, $openChatId, $metrics, $curr);
        }

        // ---- 軸2: 公式ランキングでの位置 (現在順位 + 30日推移 + 自己最高位) ----
        $this->buildRankPosition($insights, $openChatId);

        // ---- 軸3: カテゴリ内での位置 (カテゴリ内順位 + シェア) ----
        if ($category !== null && $category > 0 && $curr !== null) {
            $this->buildCategoryPosition($insights, $openChatId, $category, $curr);
        }

        return [
            'insights' => array_values($insights),
            'generatedAt' => (new \DateTime('now'))->format('c'),
        ];
    }

    /**
     * 軸1: 勢い。
     * 直近7日の純増 diff7 を主役に、平常ペース比 (直近7日/日 ÷ 過去90日/日) と
     * 成長ランキング全体順位を 1 枚に統合する。
     * 微増・横ばい・減少は黙る。
     *
     * @param array<string, mixed> $m getMemberMetrics の戻り
     */
    private function buildMomentum(array &$insights, int $openChatId, array $m, int $curr): void
    {
        $sampleN = (int)($m['sample_n'] ?? 0);
        $m7 = $m['m7'] ?? null;
        if ($sampleN < self::MOMENTUM_MIN_SAMPLE_N || $m7 === null) {
            return;
        }

        $diff7 = $curr - (int)$m7;
        // 「意味のある増加」だけ語る。微増・横ばい・減少は黙る。
        $minDiff7 = max(self::MOMENTUM_MIN_DIFF7_FLOOR, (int)ceil($curr * self::MOMENTUM_MIN_DIFF7_RATE));
        if ($diff7 < $minDiff7) {
            return;
        }

        // 平常ペース比 (既存 pace_anomaly と同式)。m90 が無い/長期が伸びていないなら ratio は出さない。
        $ratio = null;
        $m90 = $m['m90'] ?? null;
        if ($m90 !== null) {
            $diff90 = $curr - (int)$m90;
            if ($diff90 > 0) {
                $recentPace = $diff7 / 7.0;
                $basePace = $diff90 / 90.0;
                if ($basePace > 0) {
                    $ratio = $recentPace / $basePace;
                }
            }
        }

        $item = [
            'type' => 'momentum',
            'kind' => 'momentum',
            'diff7' => $diff7,
        ];

        // 1文目: 純増を主役に。加速していれば平常比を添える。
        if ($ratio !== null && $ratio >= self::MOMENTUM_ACCEL_RATIO) {
            $ratioR = round($ratio, 1);
            $item['ratio'] = $ratioR;
            $text = '過去7日で+' . number_format($diff7) . '人。普段の約' . $this->fmtRatio($ratioR) . '倍のペースで伸びています';
        } else {
            if ($ratio !== null) {
                $item['ratio'] = round($ratio, 1);
            }
            $text = '過去7日で+' . number_format($diff7) . '人が参加しています';
        }

        // 2文目: 成長ランキング上位 (week>day>hour, 50位以内) なら添える。
        $growth = $this->growthRankMention($openChatId);
        if ($growth !== null) {
            $item['growthRankPeriod'] = $growth['period'];
            $item['growthRankPosition'] = $growth['position'];
            if ($growth['percentile'] !== null) {
                $item['growthRankPercentile'] = $growth['percentile'];
            }
            $text .= '。直近' . $growth['label'] . 'の伸びは全体' . $growth['phrase'];
        }

        $item['text'] = $text;
        $insights[] = $item;
    }

    /**
     * 成長ランキング (週>日>時) の最も意味のある期間で 50 位以内なら、添える句を返す。
     * 取れない / 圏外なら null。
     *
     * @return array{period: string, label: string, position: int, percentile: ?float, phrase: string}|null
     */
    private function growthRankMention(int $openChatId): ?array
    {
        try {
            $pos = $this->narrativeRepository->getGrowthRankingPositions($openChatId);
        } catch (\Throwable $e) {
            return null;
        }

        try {
            $totals = $this->insightsRepository->getGrowthRankingTotalCounts();
        } catch (\Throwable $e) {
            $totals = ['hour' => null, 'day' => null, 'week' => null];
        }

        foreach ([['week', '1週間'], ['day', '24時間'], ['hour', '1時間']] as [$key, $label]) {
            $p = $pos[$key] ?? null;
            if ($p !== null && $p >= 1 && $p <= self::GROWTH_RANK_MENTION_MAX) {
                $total = $totals[$key] ?? null;
                $fields = $this->rankFields((int)$p, $total);
                return [
                    'period' => $key,
                    'label' => $label,
                    'position' => (int)$p,
                    'percentile' => $fields['percentile'] ?? null,
                    'phrase' => $this->rankPhrase((int)$p, $total),
                ];
            }
        }

        return null;
    }

    /**
     * 軸2: 公式ランキング (category=0, type=ranking) での位置。
     * 現在順位を主役に、30 日推移と自己最高位を 1 枚に統合する。
     * 下位 (MEANINGFUL_POSITION_MAX より下) / データ薄は黙る。
     */
    private function buildRankPosition(array &$insights, int $openChatId): void
    {
        try {
            $mv = $this->narrativeRepository->getPositionMovement($openChatId, 0, 30);
        } catch (\Throwable $e) {
            return;
        }

        if ((int)($mv['sample_n'] ?? 0) < self::MIN_POSITION_SAMPLE_N) {
            return; // 観測が少なすぎる = ノイズ。黙る
        }

        $oldest = $mv['oldest_close'] ?? null;
        $latest = $mv['latest_close'] ?? null;
        $best = $mv['best_high'] ?? null;

        // 現在順位が意味のある範囲 (上位) でなければ黙る。
        if ($latest === null || (int)$latest > self::MEANINGFUL_POSITION_MAX) {
            return;
        }
        $latest = (int)$latest;

        // 公式ランキング（全体）の最新総件数。順位に「／M件中（上位X%）」を付ける。
        try {
            $total = $this->insightsRepository->getOfficialRankingTotalCount();
        } catch (\Throwable $e) {
            $total = null;
        }

        $item = [
            'type' => 'rank_position',
            'kind' => 'rank_position',
            'current' => $latest,
        ];
        $item += $this->rankFields($latest, $total);

        // 1文目: 現在位置を主役に。
        $text = '公式ランキング全体で現在' . $this->rankPhrase($latest, $total);

        // 2文目: 30 日で動いていれば推移を、最高位が現在と違えば自己最高を添える。
        $sentence2 = [];
        if ($oldest !== null) {
            $oldest = (int)$oldest;
            $delta = $oldest - $latest; // 正 = 上昇 (順位が小さくなった)
            if (abs($delta) >= self::POSITION_MOVE_MIN_DELTA) {
                $dir = $delta > 0 ? '上昇' : '下降';
                $item['from'] = $oldest;
                $item['to'] = $latest;
                $sentence2[] = "30日で{$oldest}→{$latest}位に{$dir}";
            }
        }
        if ($best !== null && (int)$best < $latest && (int)$best <= self::MEANINGFUL_POSITION_MAX) {
            $item['best'] = (int)$best;
            $sentence2[] = '自己最高' . (int)$best . '位';
        }
        if ($sentence2 !== []) {
            $text .= '。' . implode('、', $sentence2);
        }

        $item['text'] = $text;
        $insights[] = $item;
    }

    /**
     * 軸3: 同カテゴリ内での位置。
     * カテゴリ内順位を主役に、シェアを 1 枚に統合する。
     * 上位 25% 以内のときだけ。下位・小母集団は黙る。
     */
    private function buildCategoryPosition(array &$insights, int $openChatId, int $category, int $curr): void
    {
        try {
            $ctx = $this->insightsRepository->getCategoryContext($openChatId, $category);
        } catch (\Throwable $e) {
            return;
        }

        $total = (int)($ctx['total'] ?? 0);
        $rank = $ctx['rank'] ?? null;
        if ($total < self::MIN_CATEGORY_TOTAL || $rank === null) {
            return;
        }
        $rank = (int)$rank;

        // 上位 X% のときだけ。下位は黙る。
        $pct = ($rank / $total) * 100;
        if ($pct > self::CATEGORY_TOP_PCT) {
            return;
        }

        $catName = $this->categoryName($category); // 「ゲーム」等。取れなければ空
        $label = $catName !== '' ? $catName : '同カテゴリ';

        $item = [
            'type' => 'category_position',
            'kind' => 'category_position',
            'category' => $category,
            'categoryName' => $catName !== '' ? $catName : null,
            'rank' => $rank,
            'total' => $total,
            'percentile' => $this->roundPct($pct),
        ];

        $text = "{$label}で人数{$rank}位／" . number_format($total) . '件中（上位' . $this->fmtPct($pct) . '）';

        // シェア (総メンバーに占める割合が 1% 以上のときだけ添える)。
        $sum = $ctx['sumMember'] ?? null;
        if ($sum !== null && $sum > 0) {
            $share = ($curr / $sum) * 100;
            if ($share >= self::CATEGORY_SHARE_MIN_PCT) {
                $shareR = $this->roundPct($share);
                $item['sharePercent'] = $shareR;
                $text .= '。カテゴリ全体の約' . $shareR . '%を占めます';
            }
        }

        $item['text'] = $text;
        $insights[] = $item;
    }

    /**
     * カテゴリ ID から表示名を引く。グローバルヘルパ getCategoryName() があればそれを使い、
     * 無ければ AppConfig の定義を直接逆引きする。取れなければ空文字。
     */
    private function categoryName(int $category): string
    {
        try {
            if (function_exists('getCategoryName')) {
                return (string)getCategoryName($category);
            }
        } catch (\Throwable $e) {
            // フォールバックへ
        }

        try {
            $map = \App\Config\AppConfig::OPEN_CHAT_CATEGORY[''] ?? [];
            $name = array_search($category, $map, true);
            return $name !== false ? (string)$name : '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * 順位の構造化フィールド（total / percentile）を組み立てる。
     * 総数が取れない場合は total/percentile を入れない（呼び出し側は順位だけ出す）。
     *
     * @return array{total?: int, percentile?: float}
     */
    private function rankFields(int $rank, ?int $total): array
    {
        if ($total === null || $total <= 0 || $rank < 1) {
            return [];
        }
        return [
            'total' => $total,
            'percentile' => $this->roundPct(($rank / $total) * 100),
        ];
    }

    /**
     * 順位の表示句を組み立てる。
     * - 総数あり: 「{順位}位／{総数}件中（上位{X}%）」
     * - 総数なし: 「{順位}位」（百分位は順位/総数なので総数が無いと出せない）
     */
    private function rankPhrase(int $rank, ?int $total): string
    {
        if ($total === null || $total <= 0 || $rank < 1) {
            return $rank . '位';
        }
        $pct = ($rank / $total) * 100;
        return $rank . '位／' . number_format($total) . '件中（上位' . $this->fmtPct($pct) . '）';
    }

    /**
     * % の構造化フィールド用の丸め。0.1% 未満は 0 にせず最小可視値 0.1 に張り付ける
     * (rank 1/14920 等で「上位0%」と嘘にならないように)。
     */
    private function roundPct(float $pct): float
    {
        if ($pct > 0 && $pct < 0.1) {
            return 0.1;
        }
        return round($pct, 1);
    }

    private function fmtPct(float $pct): string
    {
        // 0.1% 未満は「0.1%未満」と表現 (整数丸めで 0% にしない)
        if ($pct > 0 && $pct < 0.1) {
            return '0.1%未満';
        }
        $p = $this->roundPct($pct);
        // 整数なら小数点を落とす (上位3% / 上位0.5% のように読みやすく)
        return rtrim(rtrim(number_format($p, 1), '0'), '.') . '%';
    }

    /**
     * 倍率の表示。整数なら小数点を落とす (3倍 / 2.5倍)。
     */
    private function fmtRatio(float $ratio): string
    {
        return rtrim(rtrim(number_format($ratio, 1), '0'), '.');
    }
}
