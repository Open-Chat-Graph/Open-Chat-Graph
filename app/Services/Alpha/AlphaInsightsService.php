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
 * - ここで出すのは「一目では分からない高次の洞察」だけ:
 *     1. category_rank      : 同カテゴリ内で現在 N 位 / M 件中 (上位 X%)
 *     2. category_share     : 同カテゴリ総メンバーに占めるシェア (代表的ルームか)
 *     3. category_scale     : カテゴリ平均/中央値に対する規模倍率
 *     4. position_trend     : 全体ランキング順位が N 日で X→Y に変化 (上昇/下降)
 *     5. best_rank          : 直近の最高順位 (過去最高位の更新文脈)
 *     6. record_single_day  : 単日最大の増加は ◯月◯日の +N 人 (今がそれを更新中か)
 *     7. pace_anomaly       : 直近の伸びが過去平均ペースの N 倍 (普段と違う急変)
 *     8. growth_rank        : オプチャグラフ独自 1h/24h/1w 成長ランキングでの全体順位
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

    /** カテゴリ規模倍率を言及する下限 (平均の何倍以上か) */
    private const CATEGORY_SCALE_MIN_RATIO = 3.0;

    /** 単日最大の伸びを「記録級」として出す最小絶対値 */
    private const RECORD_SINGLE_DAY_MIN = 30;

    /** ペース異常を出す: 直近ペースが過去平均ペースの何倍以上か */
    private const PACE_ANOMALY_RATIO = 2.0;

    /** ペース異常を出す: 直近 7 日の最低実増加 (小さい揺れを誤検知しない) */
    private const PACE_ANOMALY_MIN_DIFF7 = 15;

    /** カテゴリ文脈を出す最低カテゴリ規模 (小さすぎる母集団は順位に意味がない) */
    private const MIN_CATEGORY_TOTAL = 20;

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

        // ---- 成長ランキング (1h/24h/1w) ----
        $this->appendGrowthRank($insights, $openChatId);

        // ---- 全体順位の推移 / 最高順位 ----
        $this->appendPositionTrend($insights, $openChatId);

        // ---- カテゴリ文脈 (順位 / シェア / 規模) ----
        if ($category !== null && $category > 0 && $curr !== null) {
            $this->appendCategoryContext($insights, $openChatId, $category, $curr);
        }

        // ---- 単日最大の伸び (記録更新文脈) ----
        if ($metrics !== null && $curr !== null) {
            $this->appendRecordSingleDay($insights, $metrics, $curr);
            $this->appendPaceAnomaly($insights, $metrics, $curr);
        }

        return [
            'insights' => array_values($insights),
            'generatedAt' => (new \DateTime('now'))->format('c'),
        ];
    }

    /**
     * オプチャグラフ独自の成長ランキング全体順位。
     * 圏内 (上位) のときだけ出す。順位が低すぎる (= ほぼ全件) は黙る。
     */
    private function appendGrowthRank(array &$insights, int $openChatId): void
    {
        try {
            $pos = $this->narrativeRepository->getGrowthRankingPositions($openChatId);
        } catch (\Throwable $e) {
            return;
        }

        // 週 > 日 > 時 の優先で 1 つだけ (最も意味のある期間)
        foreach ([['week', '1週間', 50], ['day', '24時間', 50], ['hour', '1時間', 50]] as [$key, $label, $limit]) {
            $p = $pos[$key] ?? null;
            if ($p !== null && $p >= 1 && $p <= $limit) {
                $insights[] = [
                    'type' => 'growth_rank',
                    'period' => $key,
                    'position' => $p,
                    'text' => "過去{$label}で人数の伸びが全体{$p}位",
                ];
                return;
            }
        }
    }

    /**
     * 全体ランキング (category=0, type=ranking) の順位推移と最高順位。
     * 推移グラフには出ているが「N日でX→Yに動いた」「直近の最高位」は一目で読み取れない高次情報。
     */
    private function appendPositionTrend(array &$insights, int $openChatId): void
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

        // 順位の変化 (close_position は小さいほど上位)。
        // 数千位の上下はノイズなので、上位(=MEANINGFUL_POSITION_MAX以内)に居た/居る時だけ語る。
        $meaningful = ($oldest !== null && $oldest <= self::MEANINGFUL_POSITION_MAX)
            || ($latest !== null && $latest <= self::MEANINGFUL_POSITION_MAX);
        if ($oldest !== null && $latest !== null && $meaningful) {
            $delta = $oldest - $latest; // 正 = 上昇 (順位が小さくなった)
            if (abs($delta) >= self::POSITION_MOVE_MIN_DELTA) {
                $dir = $delta > 0 ? '上昇' : '下降';
                $insights[] = [
                    'type' => 'position_trend',
                    'category' => 0,
                    'from' => (int)$oldest,
                    'to' => (int)$latest,
                    'delta' => (int)$delta,
                    'fromDate' => $mv['oldest_date'] ?? null,
                    'toDate' => $mv['latest_date'] ?? null,
                    'text' => "公式ランキング（全体）が直近30日で{$oldest}→{$latest}位に{$dir}",
                ];
            }
        }

        // 直近 30 日の最高順位 (現在より明確に上 かつ 上位のときだけ = 過去にもっと上だった文脈)
        if ($best !== null && $latest !== null && $best < $latest && $best <= self::MEANINGFUL_POSITION_MAX) {
            $insights[] = [
                'type' => 'best_rank',
                'category' => 0,
                'bestPosition' => (int)$best,
                'currentPosition' => (int)$latest,
                'text' => "公式ランキング（全体）での直近30日の最高は{$best}位",
            ];
        }
    }

    /**
     * 同カテゴリ内での現在順位 / シェア / 規模倍率。
     */
    private function appendCategoryContext(array &$insights, int $openChatId, int $category, int $curr): void
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

        // 1) カテゴリ内順位 (上位 X% のときのみ強調。下位は黙る)
        $pct = ($rank / $total) * 100;
        if ($pct <= self::CATEGORY_TOP_PCT) {
            $insights[] = [
                'type' => 'category_rank',
                'category' => $category,
                'rank' => $rank,
                'total' => $total,
                'percentile' => $this->roundPct($pct),
                'text' => "同カテゴリのメンバー数で{$rank}位／" . number_format($total) . "件中（上位" . $this->fmtPct($pct) . "）",
            ];
        }

        // 2) カテゴリシェア (総メンバーに占める割合が無視できないときのみ)
        $sum = $ctx['sumMember'] ?? null;
        if ($sum !== null && $sum > 0) {
            $share = ($curr / $sum) * 100;
            if ($share >= self::CATEGORY_SHARE_MIN_PCT) {
                $shareR = $this->roundPct($share);
                $insights[] = [
                    'type' => 'category_share',
                    'category' => $category,
                    'sharePercent' => $shareR,
                    'text' => "同カテゴリの人数の約{$shareR}%を占める",
                ];
            }
        }

        // 3) カテゴリ平均に対する規模倍率 (平均より桁違いに大きいときのみ)
        $avg = $ctx['avgMember'] ?? null;
        if ($avg !== null && $avg > 0) {
            $ratio = $curr / $avg;
            if ($ratio >= self::CATEGORY_SCALE_MIN_RATIO) {
                $insights[] = [
                    'type' => 'category_scale',
                    'category' => $category,
                    'ratio' => round($ratio, 1),
                    'avgMember' => (int)round($avg),
                    'text' => "カテゴリ平均の" . round($ratio, 1) . "倍の規模",
                ];
            }
        }
    }

    /**
     * 単日最大の伸びの記録。今まさに更新中なら強調。
     */
    private function appendRecordSingleDay(array &$insights, array $m, int $curr): void
    {
        $growth = $m['max_single_day_growth'] ?? null;
        $date = $m['max_growth_date'] ?? null;
        if ($growth === null || $date === null || (int)$growth < self::RECORD_SINGLE_DAY_MIN) {
            return;
        }
        $growth = (int)$growth;

        // 直近の伸び (m1=24h前) が過去単日最大に匹敵 / 超過しているか
        $diff1 = ($m['m1'] !== null) ? $curr - (int)$m['m1'] : null;
        $updating = ($diff1 !== null && $diff1 >= $growth);

        $insights[] = [
            'type' => 'record_single_day',
            'maxGrowth' => $growth,
            'maxGrowthDate' => (string)$date,
            'updating' => $updating,
            'text' => $updating
                ? "単日最大の増加を更新中（{$this->fmtDate((string)$date)} +" . number_format($growth) . "人）"
                : "単日最大の増加は{$this->fmtDate((string)$date)}の+" . number_format($growth) . "人",
        ];
    }

    /**
     * ペース異常検知: 直近 7 日の 1 日あたり増加が、過去 90 日平均ペースの N 倍以上。
     * グラフを凝視しないと気づけない「普段と違う急変」を拾う。
     */
    private function appendPaceAnomaly(array &$insights, array $m, int $curr): void
    {
        $m7 = $m['m7'] ?? null;
        $m90 = $m['m90'] ?? null;
        if ($m7 === null || $m90 === null) {
            return;
        }
        $diff7 = $curr - (int)$m7;
        $diff90 = $curr - (int)$m90;
        if ($diff7 < self::PACE_ANOMALY_MIN_DIFF7 || $diff90 <= 0) {
            return; // 直近が伸びていない / 長期も伸びていないなら異常検知しない
        }

        $recentPace = $diff7 / 7.0;       // 1 日あたり (直近)
        $basePace = $diff90 / 90.0;       // 1 日あたり (長期平均)
        if ($basePace <= 0) {
            return;
        }
        $ratio = $recentPace / $basePace;
        if ($ratio >= self::PACE_ANOMALY_RATIO) {
            $insights[] = [
                'type' => 'pace_anomaly',
                'ratio' => round($ratio, 1),
                'recentPerDay' => round($recentPace, 1),
                'basePerDay' => round($basePace, 1),
                'text' => "増加ペースが平常の約" . round($ratio, 1) . "倍に加速",
            ];
        }
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
        return $pct < 1 ? round($pct, 1) : round($pct, 1);
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

    private function fmtDate(string $date): string
    {
        try {
            return (new \DateTime($date))->format('Y年n月j日');
        } catch (\Throwable $e) {
            return $date;
        }
    }
}
