<?php

declare(strict_types=1);

namespace App\Services\Narrative;

use App\Models\Repositories\OcNarrativeRepositoryInterface;

/**
 * /oc/{id} ページ用の narrative (要約 + 詳細 + meta description) 生成サービス。
 *
 * - locale / category label 解決は呼び出し側 (Controller) の責務。Service は与えられた数値・文字列を平に消費する
 * - 7 パターン分岐 (active_growth / rapid_growth / decline / stable / new / stagnant / 異常)
 * - 全体を try/catch で包み、異常データ / 取得失敗時は必ず null を返す
 *   → View 側で <?php if ($narrative) ?> でガードすれば、既存ページの動作が完全保持される
 *
 * 戻り値は平文 (HTML エスケープなし)。View / Metadata 側で h() を 1 度だけ通す前提。
 */
class OcNarrativeService
{
    /** sample_n がこの値未満なら「新規」扱い (% 計算回避) */
    private const NEW_THRESHOLD_SAMPLE_N = 30;

    /** sample_n がこの値未満かつ pct30 が高ければ「急成長」 */
    private const RAPID_GROWTH_SAMPLE_N = 60;
    private const RAPID_GROWTH_PCT = 50.0;

    /** 「活発成長」判定の最低メンバー数と pct30 閾値 */
    private const ACTIVE_GROWTH_MIN_MEMBER = 50;
    private const ACTIVE_GROWTH_PCT = 5.0;

    /** 「減少」判定の pct30 閾値 */
    private const DECLINE_PCT = -5.0;

    /** 「安定」判定の |pct30| 閾値 */
    private const STABLE_PCT_ABS = 2.0;

    /** 「長期停滞」判定 (curr_date がこれより古ければ stagnant) */
    private const STAGNANT_DAYS = 365;

    /** ピークが現在の何倍以上ならピーク言及するか */
    private const PEAK_MENTION_RATIO = 1.1;

    /** 単日最大伸び言及する閾値 (絶対値 or 現在比) */
    private const SINGLE_DAY_GROWTH_ABS = 20;
    private const SINGLE_DAY_GROWTH_PCT = 5.0;

    /** カテゴリ内順位の差をいくつから言及するか */
    private const POSITION_DELTA_MENTION = 10;

    public function __construct(
        private OcNarrativeRepositoryInterface $repository,
    ) {
    }

    /**
     * @param int $openchatId
     * @param array $oc ルーム属性 (id, name, description, category, member, created_at など)
     * @param ?string $categoryLabel Controller 側で解決済みのカテゴリ表示名 (locale-aware)。null なら narrative にカテゴリ言及を出さない
     * @return ?array{summary: string, detail: string, meta_description: string, pattern: string}
     */
    public function generate(int $openchatId, array $oc, ?string $categoryLabel = null): ?array
    {
        try {
            $metrics = $this->repository->getMemberMetrics($openchatId);

            // 最低条件: 現在値が取れること
            if ($metrics['curr'] === null || $metrics['sample_n'] <= 0) {
                return null;
            }

            $curr = (int)$metrics['curr'];
            if ($curr <= 0) {
                return null;
            }

            // ロケール / カテゴリ未指定でも narrative 自体は出す (カテゴリ部分だけ省略)
            // $categoryLabel は Controller から渡される (locale-aware resolution は呼び出し側の責務)

            $pattern = $this->detectPattern($metrics);

            $pct30 = $this->safePct($metrics['m30'] ?? null, $curr);
            $diff30 = ($metrics['m30'] !== null) ? ($curr - (int)$metrics['m30']) : null;

            $pct90Cached = $this->safePct($metrics['m90'] ?? null, $curr);
            $summary = $this->buildSummary($pattern, $curr, $diff30, $pct30, $pct90Cached, $categoryLabel, $oc, $openchatId);
            $detail = $this->buildDetail($pattern, $metrics, $oc, $categoryLabel, $openchatId);
            $metaDescription = $this->buildMetaDescription($oc, $curr, $diff30, $pct30, $categoryLabel);

            // 文字数チェック (異常に空 / 長すぎる場合は安全側で null)
            if ($summary === '' || $detail === '' || $metaDescription === '') {
                return null;
            }

            return [
                'summary' => $summary,
                'detail' => $detail,
                'meta_description' => $metaDescription,
                'pattern' => $pattern,
            ];
        } catch (\Throwable $e) {
            // どんな例外でも null。ページは既存ロジックで完全動作する。
            return null;
        }
    }

    /**
     * 7 パターンの判定。優先順位: stagnant > new > rapid_growth > decline > active_growth > stable > new (fallback)
     */
    private function detectPattern(array $m): string
    {
        $curr = (int)$m['curr'];
        $sampleN = (int)$m['sample_n'];
        $pct30 = $this->safePct($m['m30'] ?? null, $curr);

        // 1) 長期停滞: 最新記録が古いか sparse
        if ($m['curr_date'] !== null) {
            $daysOld = $this->daysSince((string)$m['curr_date']);
            if ($daysOld !== null && $daysOld > self::STAGNANT_DAYS) {
                return 'stagnant';
            }
        }

        // 2) 新規 (sparse < 30 day)
        if ($sampleN < self::NEW_THRESHOLD_SAMPLE_N) {
            return 'new';
        }

        // 3) 急成長 (新興だが急拡大)
        if ($pct30 !== null && $sampleN < self::RAPID_GROWTH_SAMPLE_N && $pct30 > self::RAPID_GROWTH_PCT) {
            return 'rapid_growth';
        }

        // 4) 減少
        if ($pct30 !== null && $pct30 < self::DECLINE_PCT) {
            return 'decline';
        }

        // 5) 活発成長
        if ($pct30 !== null && $curr > self::ACTIVE_GROWTH_MIN_MEMBER && $pct30 > self::ACTIVE_GROWTH_PCT) {
            return 'active_growth';
        }

        // 6) 安定
        if ($pct30 !== null && abs($pct30) < self::STABLE_PCT_ABS) {
            return 'stable';
        }

        // 7) フォールバック (微増減・データやや薄い)
        return 'stable';
    }

    /**
     * オプチャグラフ独自の 1h / 24h / 1w 成長ランキング位置から
     * 「今まさに伸びている」ラベルを返す。最重要シグナル。
     */
    private function buildHotTrendingLabel(int $openchatId): ?string
    {
        try {
            $pos = $this->repository->getGrowthRankingPositions($openchatId);
        } catch (\Throwable $e) {
            return null;
        }
        $h = $pos['hour'];
        $d = $pos['day'];
        $w = $pos['week'];

        // 3 期間すべてで 1 位独占 = 最高峰
        if ($h !== null && $h <= 1 && $d !== null && $d <= 1 && $w !== null && $w <= 1) {
            return '直近 1 時間・24 時間・1 週間すべての成長ランキングで全体 1 位を独占、いま間違いなく全オープンチャット中もっとも勢いのあるルーム';
        }

        // 1 位 (期間別)
        if ($w !== null && $w <= 1) {
            return '過去 1 週間で全オープンチャット中もっとも人数を伸ばしているルーム (週間成長ランキング 1 位)';
        }
        if ($d !== null && $d <= 1) {
            return '直近 24 時間で全オープンチャット中もっとも人数を伸ばしているルーム (24 時間成長ランキング 1 位)';
        }
        if ($h !== null && $h <= 1) {
            return '今この瞬間にもっとも人数が増えているルーム (直近 1 時間成長ランキング 1 位)';
        }

        // トップ 10 入り
        if ($w !== null && $w <= 10) {
            return sprintf('過去 1 週間の成長ランキングで全体 %d 位、急成長中の注目ルーム', $w);
        }
        if ($d !== null && $d <= 10) {
            return sprintf('直近 24 時間の成長ランキングで全体 %d 位、いまよく伸びているルーム', $d);
        }
        if ($h !== null && $h <= 10) {
            return sprintf('直近 1 時間の成長ランキングで全体 %d 位、今動きが活発', $h);
        }

        // トップ 50 入り
        if ($w !== null && $w <= 50) {
            return sprintf('過去 1 週間の成長ランキングで全体 %d 位の注目ルーム', $w);
        }
        if ($d !== null && $d <= 50) {
            return sprintf('直近 24 時間の成長ランキングで全体 %d 位の注目ルーム', $d);
        }

        return null;
    }

    /**
     * Summary 用の評価ラベル選択。最も impressive な単一ラベルを返す。
     * 優先順位:
     *  1. オプチャグラフの 1h / 24h / 1w 成長ランキング上位 (= いま伸びている = 最重要)
     *  2. 全体 rising AVG (= 継続的に動きあり)
     *  3. 全体 ranking AVG (= 規模)
     *  4. カテゴリ内 rising AVG (= カテゴリ内で活発)
     */
    private function buildSummaryEvalLabel(int $openchatId, array $oc): ?string
    {
        $hot = $this->buildHotTrendingLabel($openchatId);
        if ($hot !== null) {
            return $hot;
        }

        try {
            $r = $this->repository->getAveragePosition($openchatId, 0, 'rising', 30);
            if ($r['sample_n'] >= 5 && $r['avg_position'] !== null) {
                $avg = (float)$r['avg_position'];
                if ($avg <= 10) return '総合急上昇でも常時トップ 10 入り、オープンチャット中でも最高クラスの活発さ';
                if ($avg <= 50) return '総合急上昇で継続して上位 50 位以内、非常に活発な運営';
                if ($avg <= 100) return '総合急上昇で上位 100 位前後を維持する活発なルーム';
                if ($avg <= 200) return '総合急上昇ランキングに継続的に登場している活発なルーム';
            }
        } catch (\Throwable $e) {}

        try {
            $r = $this->repository->getAveragePosition($openchatId, 0, 'ranking', 30);
            if ($r['sample_n'] >= 5 && $r['avg_position'] !== null) {
                $avg = (float)$r['avg_position'];
                if ($avg <= 10) return '全体ランキングでも常時トップ 10 入り、オープンチャット全体を代表する規模';
                if ($avg <= 50) return '全体ランキングで継続して上位 50 位以内に入る大規模な代表的ルーム';
                if ($avg <= 100) return '全体ランキングで上位 100 位以内に常在する大規模ルーム';
                if ($avg <= 200) return '全体ランキング上位に継続的に登場する規模のルーム';
            }
        } catch (\Throwable $e) {}

        $catId = $this->extractCategoryId($oc);
        if ($catId !== null && $catId > 0) {
            try {
                $r = $this->repository->getAveragePosition($openchatId, $catId, 'rising', 30);
                if ($r['sample_n'] >= 5 && $r['avg_position'] !== null) {
                    $avg = (float)$r['avg_position'];
                    if ($avg <= 10) return 'カテゴリ内の急上昇ランキングで継続して上位 10 位以内、いま活発に動いているルーム';
                    if ($avg <= 30) return 'カテゴリ内の急上昇ランキングで上位 30 位以内を維持する活発なルーム';
                }
            } catch (\Throwable $e) {}
        }

        return null;
    }

    private function buildSummary(string $pattern, int $curr, ?int $diff30, ?float $pct30, ?float $pct90, ?string $category, array $oc, int $openchatId): string
    {
        $currFmt = number_format($curr);
        // 評価ラベル (ランキング / 急上昇) を最優先で出す。該当無しなら空。
        $evalLabel = $this->buildSummaryEvalLabel($openchatId, $oc);
        $evalPart = $evalLabel !== null ? $evalLabel . '。' : '';

        // stable パターンは pct90 で「ほぼ横ばい / じわじわ増加 / じわじわ減少」に細分する。
        // 30 日でほぼ動いていなくても、90 日で +2% 以上なら「じわじわ増加中」が実態に合う。
        $stableLabel = '安定運営中';
        if ($pattern === 'stable' && $pct90 !== null) {
            if ($pct90 >= 2.0) {
                $stableLabel = 'じわじわ増加中';
            } elseif ($pct90 <= -2.0) {
                $stableLabel = 'じわじわ減少中';
            }
        }

        // pattern 部分: 「拡大中」「安定運営中」等の解釈ラベル + 現在人数のみ
        // (% と人数の duplicate、category の表示は detail 側に集約)
        $patternPart = match ($pattern) {
            'rapid_growth'  => sprintf('急速に拡大中。現在 %s 人。', $currFmt),
            'active_growth' => sprintf('拡大中。現在 %s 人。', $currFmt),
            'decline'       => sprintf('縮小傾向。現在 %s 人。', $currFmt),
            'stable'        => sprintf('%s。現在 %s 人。', $stableLabel, $currFmt),
            'new'           => sprintf('新しく開設されたルーム。現在 %s 人。', $currFmt),
            'stagnant'      => sprintf('現在 %s 人。長期更新が止まっています。', $currFmt),
            default         => sprintf('現在 %s 人。', $currFmt),
        };

        return $evalPart . $patternPart;
    }

    private function buildDetail(string $pattern, array $m, array $oc, ?string $category, int $openchatId): string
    {
        $sentences = [];

        $curr = (int)$m['curr'];

        // 開設情報
        $openInfo = $this->openingInfoSentence($oc, $m);
        if ($openInfo !== null) {
            $sentences[] = $openInfo;
        }

        // カテゴリ言及 (summary から移動)。カテゴリ無いルームは出さない。
        if ($category !== null && $category !== '') {
            $sentences[] = sprintf('%s カテゴリのオープンチャット。', $category);
        }

        // 評価ラベル (ランキング / 急上昇) は summary 側に移動済み。detail からは削除。

        // 期間比較数字を組み立てる (まだ sentences には積まない。文章は最後に置く)
        $periodSentence = null;
        $pct7 = $pct30 = $pct90 = null;
        $diff7 = $diff30 = $diff90 = null;
        if ($pattern === 'new' || $pattern === 'stagnant') {
            // % 出さず絶対値で
            if (($m['m7'] ?? null) !== null) {
                $diff7Val = $curr - (int)$m['m7'];
                if ($diff7Val !== 0) {
                    $periodSentence = sprintf('過去 7 日で %s 人。', $this->signedNumberFormat($diff7Val));
                }
            }
        } else {
            // 通常パターン: 実数 + % 併記、最後に解釈ラベル
            $pct7 = $this->safePct($m['m7'] ?? null, $curr);
            $pct30 = $this->safePct($m['m30'] ?? null, $curr);
            $pct90 = $this->safePct($m['m90'] ?? null, $curr);
            $diff7 = ($m['m7'] ?? null) !== null ? $curr - (int)$m['m7'] : null;
            $diff30 = ($m['m30'] ?? null) !== null ? $curr - (int)$m['m30'] : null;
            $diff90 = ($m['m90'] ?? null) !== null ? $curr - (int)$m['m90'] : null;

            $parts = [];
            if ($pct90 !== null && $diff90 !== null) {
                $parts[] = sprintf('過去 3 ヶ月で %s 人 (%s%%)', $this->signedNumberFormat($diff90), $this->signedFloatFormat($pct90));
            }
            if ($pct30 !== null && $diff30 !== null) {
                $parts[] = sprintf('1 ヶ月で %s 人 (%s%%)', $this->signedNumberFormat($diff30), $this->signedFloatFormat($pct30));
            }
            if ($pct7 !== null && $diff7 !== null) {
                $parts[] = sprintf('1 週間で %s 人 (%s%%)', $this->signedNumberFormat($diff7), $this->signedFloatFormat($pct7));
            }
            if (!empty($parts)) {
                $labelPct = $pct30 ?? $pct90;
                $labelDiff = $pct30 !== null ? $diff30 : $diff90;
                $label = $labelPct !== null ? $this->describeChange($labelDiff, $labelPct) : '';
                $tail = $label !== '' ? 'と' . $label : '';
                $periodSentence = sprintf('%s%s。', implode('、', $parts), $tail);
            }
        }

        // トレンド解釈 (評価ラベルの次、数字より前に置く)
        if ($pct7 !== null || $pct30 !== null || $pct90 !== null) {
            $trend = $this->describeTrend($diff7, $diff30, $diff90, $pct7, $pct30, $pct90);
            if ($trend !== null) {
                $sentences[] = $trend;
            }
        }

        // ピーク言及
        if ($m['peak_high'] !== null && $m['peak_date'] !== null) {
            $peakHigh = (int)$m['peak_high'];
            if ($peakHigh > $curr * self::PEAK_MENTION_RATIO) {
                $sentences[] = sprintf('ピークは %s の %s 人。',
                    $this->formatJapaneseDate((string)$m['peak_date']),
                    number_format($peakHigh));
            }
        }

        // 単日急変動 (decline / stagnant / new では出さない、誤解を招くため)
        if ($pattern !== 'decline' && $pattern !== 'stagnant') {
            if ($m['max_single_day_growth'] !== null && $m['max_growth_date'] !== null) {
                $growth = (int)$m['max_single_day_growth'];
                $shouldMention = $growth >= self::SINGLE_DAY_GROWTH_ABS
                    || ($curr > 0 && $growth >= ($curr * self::SINGLE_DAY_GROWTH_PCT / 100));
                if ($shouldMention && $growth > 0) {
                    $sentences[] = sprintf('単日最大の伸びは %s の +%s 人。',
                        $this->formatJapaneseDate((string)$m['max_growth_date']),
                        number_format($growth));
                }
            }
        }

        // 期間比較の細かい数字は最後に置く (一般読者には機微が分かりにくいため、
        // 評価ラベル → トレンド解釈 → ピーク / 単日 の後に補足する位置)
        if ($periodSentence !== null) {
            $sentences[] = $periodSentence;
        }

        // カテゴリ内 ranking 位置の delta は単独では「人がいるだけの無駄チャット」の場合も
        // 拾ってしまうため出さない。代わりに上部で「全体 rising / カテゴリ内 rising」の
        // 活発度ラベルを出している (= 実発言を伴う movement のシグナル)。

        // 停滞情報
        if ($pattern === 'stagnant' && $m['curr_date'] !== null) {
            $days = $this->daysSince((string)$m['curr_date']);
            if ($days !== null && $days > 0) {
                $months = (int)floor($days / 30);
                if ($months >= 1) {
                    $sentences[] = sprintf('最終更新は約 %d ヶ月前です。', $months);
                }
            }
        }

        // 最低 1 文以上、最大 5 文に調整
        if (empty($sentences)) {
            return '';
        }
        $sentences = array_slice($sentences, 0, 5);

        return implode("\n", $sentences);
    }

    private function buildMetaDescription(array $oc, int $curr, ?int $diff30, ?float $pct30, ?string $category): string
    {
        $name = trim((string)($oc['name'] ?? ''));
        if ($name === '') {
            $name = 'オープンチャット';
        }

        $currFmt = number_format($curr);

        $head = sprintf('%s の統計・メンバー数推移。', $this->truncate($name, 30));

        $stat = sprintf('現在 %s 人', $currFmt);
        if ($pct30 !== null && $diff30 !== null) {
            $stat .= sprintf('、過去 30 日 %s%%', $this->signedFloatFormat($pct30));
        }
        $stat .= '。';

        $cat = ($category !== null && $category !== '') ? sprintf('%s カテゴリ。', $category) : '';

        // LINE description 冒頭を足す (短縮版)。meta 属性に改行が混入しないよう連続空白を 1 個に潰す
        $desc = '';
        $rawDesc = trim(preg_replace('/\s+/u', ' ', (string)($oc['description'] ?? '')) ?? '');
        if ($rawDesc !== '') {
            $desc = $this->truncate($rawDesc, 50);
        }

        $combined = $head . $stat . $cat . $desc;
        // meta description は 120-160 字目安
        return $this->truncate($combined, 160);
    }

    private function openingInfoSentence(array $oc, array $m): ?string
    {
        // ルーム開設日は api_created_at (LINE API 由来の実開設日) のみ使う。
        // created_at は我々のサイトへの登録日 = 「ルーム開設」ではないので使わない。
        // 一部のルームは api_created_at が null。その場合は開設情報の文を省略する。
        $apiCreatedAt = $oc['api_created_at'] ?? null;
        if ($apiCreatedAt === null || $apiCreatedAt === '' || $apiCreatedAt === 0 || $apiCreatedAt === '0000-00-00 00:00:00') {
            return null;
        }

        try {
            $dt = is_int($apiCreatedAt) || ctype_digit((string)$apiCreatedAt)
                ? (new \DateTime())->setTimestamp((int)$apiCreatedAt)
                : new \DateTime((string)$apiCreatedAt);
        } catch (\Throwable $e) {
            return null;
        }
        $now = new \DateTime('now');
        $diff = $now->diff($dt);

        $ym = $dt->format('Y年n月');
        $yearsRun = $diff->y;

        if ($yearsRun >= 1) {
            return sprintf('%s 開設、運営 %d 年。', $ym, $yearsRun);
        }
        if ($diff->m >= 1) {
            return sprintf('%s 開設、運営 %d ヶ月。', $ym, $diff->m);
        }
        return sprintf('%s 開設。', $ym);
    }

    private function extractCategoryId(array $oc): ?int
    {
        if (!array_key_exists('category', $oc)) {
            return null;
        }
        $v = $oc['category'];
        if ($v === null || $v === '') {
            return null;
        }
        if (is_int($v)) {
            return $v;
        }
        if (is_numeric($v)) {
            return (int)$v;
        }
        return null;
    }

    /**
     * % 変化を計算。past が null / 0 / 負なら null (ゼロ除算保護)
     */
    private function safePct(?int $past, int $curr): ?float
    {
        if ($past === null || $past <= 0) {
            return null;
        }
        return (($curr - $past) / $past) * 100;
    }

    private function daysSince(string $date): ?int
    {
        try {
            $dt = new \DateTime($date);
            $now = new \DateTime('now');
            $diff = $dt->diff($now);
            return (int)$diff->days;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function signedNumberFormat(int $n): string
    {
        if ($n > 0) return '+' . number_format($n);
        if ($n < 0) return '-' . number_format(abs($n));
        return '0';
    }

    /**
     * 30 日 (or 90 日 fallback) の pct から短い解釈ラベルを返す。
     * 数字列の末尾に「と〜」で接続する用途。
     */
    private function describeChange(?int $diff, float $pct): string
    {
        // オプチャの世界感では「横ばい」は実数 ±3 程度。+65 や +20 でも「増加」と認識すべき。
        // → |diff| <= 3 または極めて微小な % (< 0.1%) のときだけ「ほぼ横ばい」とする。
        $absDiff = $diff !== null ? abs($diff) : 0;
        if ($absDiff <= 3 || abs($pct) < 0.1) return 'ほぼ横ばい';
        if ($pct >= 50.0) return '急成長';
        if ($pct >= 15.0) return '安定して拡大';
        if ($pct >= 5.0) return '成長基調';
        if ($pct >= 0.5) return '緩やかな増加';
        if ($pct > -5.0) return '緩やかな縮小';
        if ($pct > -15.0) return '目立った減少';
        return '大きく縮小';
    }

    /**
     * 「ほぼ横ばい」判定のヘルパ。describeChange と同じ閾値で trend 文も判定する。
     */
    private function isFlat(?int $diff, ?float $pct): bool
    {
        $absDiff = $diff !== null ? abs($diff) : 0;
        $absPct = $pct !== null ? abs($pct) : 0;
        return $absDiff <= 3 || $absPct < 0.1;
    }

    /**
     * 全体ランキング / 全体急上昇の平均位置から「規模」「活発度」を 1 文に集約。
     * - 全体 ranking AVG ≤ X → 大規模代表
     * - 全体 rising AVG ≤ Y → 非常に活発 / 最高クラス
     * 両方該当すれば 1 文に組み合わせる。どちらも無ければ null。
     */
    private function buildScaleAndActivitySentence(int $openchatId, array $oc): ?string
    {
        $scaleLabel = null;     // 全体 ranking 由来 (規模)
        $activityLabel = null;  // 全体 rising or カテゴリ内 rising 由来 (活発度)

        // 全体 ranking AVG → 大規模代表度
        try {
            $r = $this->repository->getAveragePosition($openchatId, 0, 'ranking', 30);
            if ($r['sample_n'] >= 5 && $r['avg_position'] !== null) {
                $avg = (float)$r['avg_position'];
                if ($avg <= 10) {
                    $scaleLabel = '全体ランキングでも常時トップ 10 入り、オープンチャット全体を代表する規模';
                } elseif ($avg <= 50) {
                    $scaleLabel = '全体ランキングで継続して上位 50 位以内に入る大規模な代表的ルーム';
                } elseif ($avg <= 100) {
                    $scaleLabel = '全体ランキングで上位 100 位以内に常在する大規模ルーム';
                } elseif ($avg <= 200) {
                    $scaleLabel = '全体ランキング上位に継続的に登場する規模のルーム';
                }
            }
        } catch (\Throwable $e) {
            // 取得失敗は narrative 全体を壊さない
        }

        // 全体 rising AVG → 最高クラス活発度 (優先)
        try {
            $r = $this->repository->getAveragePosition($openchatId, 0, 'rising', 30);
            if ($r['sample_n'] >= 5 && $r['avg_position'] !== null) {
                $avg = (float)$r['avg_position'];
                if ($avg <= 10) {
                    $activityLabel = '総合急上昇でも常時トップ 10 入り、オープンチャットでも最高クラスの活発さ';
                } elseif ($avg <= 50) {
                    $activityLabel = '総合急上昇で継続して上位 50 位以内、非常に活発な運営';
                } elseif ($avg <= 100) {
                    $activityLabel = '総合急上昇で上位 100 位前後を維持する活発なルーム';
                } elseif ($avg <= 200) {
                    $activityLabel = '総合急上昇ランキングに継続的に登場している活発なルーム';
                }
            }
        } catch (\Throwable $e) {
            // 同上
        }

        // 全体 rising に該当しなければカテゴリ内 rising AVG をフォールバック
        // (カテゴリ内の活発度判定 — 全体での「最高クラス」言及が無いときだけ出して冗長回避)
        if ($activityLabel === null) {
            $catId = $this->extractCategoryId($oc);
            if ($catId !== null && $catId > 0) {
                try {
                    $r = $this->repository->getAveragePosition($openchatId, $catId, 'rising', 30);
                    if ($r['sample_n'] >= 5 && $r['avg_position'] !== null) {
                        $avg = (float)$r['avg_position'];
                        if ($avg <= 10) {
                            $activityLabel = 'カテゴリ内の急上昇ランキングで継続して上位 10 位以内、いま活発に動いているルーム';
                        } elseif ($avg <= 30) {
                            $activityLabel = 'カテゴリ内の急上昇ランキングで上位 30 位以内を維持する活発なルーム';
                        }
                    }
                } catch (\Throwable $e) {
                    // 取得失敗は無視
                }
            }
        }

        if ($scaleLabel !== null && $activityLabel !== null) {
            return $scaleLabel . '。' . $activityLabel . '。';
        }
        if ($scaleLabel !== null) {
            return $scaleLabel . '。';
        }
        if ($activityLabel !== null) {
            return $activityLabel . '。';
        }
        return null;
    }

    /**
     * 短期 (30 日) と長期 (90 日)、直近 (7 日) を組み合わせて
     * 動き方の特徴を 1 文に要約する。長短ねじれや勢い変化を捕捉して、
     * 単なる数字羅列を「長期では伸びているが直近は落ち着き」のような
     * 言葉に翻訳する役割。データ不足や両期間ともほぼ横ばいなら null。
     */
    private function describeTrend(?int $diff7, ?int $diff30, ?int $diff90, ?float $pct7, ?float $pct30, ?float $pct90): ?string
    {
        if ($pct30 === null && $pct90 === null) {
            return null;
        }
        // 30 日が無いケースは 90 日だけで簡潔に
        if ($pct30 === null) {
            if ($this->isFlat($diff90, $pct90)) return null;
            return ($pct90 ?? 0) > 0 ? '長期的には拡大傾向。' : '長期的には縮小傾向。';
        }
        if ($pct90 === null) {
            return null;
        }

        $p30 = (float)$pct30;
        $p90 = (float)$pct90;
        $flat30 = $this->isFlat($diff30, $pct30);
        $flat90 = $this->isFlat($diff90, $pct90);

        // 両期間とも実数 / % どちらでもほぼ動いていない
        if ($flat30 && $flat90) {
            return '長期的にも大きな変動はなく、安定した運営が続いている。';
        }

        // 30 日は横ばいだが 90 日では明確に変動 (じわじわ系)
        if ($flat30 && !$flat90) {
            return $p90 > 0
                ? '直近 1 ヶ月はほぼ横ばいだが、3 ヶ月単位では着実に人数が増えている。'
                : '直近 1 ヶ月はほぼ横ばいだが、3 ヶ月単位では緩やかに人数が減っている。';
        }

        // 90 日は横ばいだが 30 日で動いている (直近の変化)
        if (!$flat30 && $flat90) {
            return $p30 > 0
                ? '長期では変動がなかったが、直近 1 ヶ月で人数が増え始めている。'
                : '長期では変動がなかったが、直近 1 ヶ月で人数が減り始めている。';
        }

        // 長短ねじれ (符号違い)
        if ($p30 > 0 && $p90 < 0) {
            return '中期では下落していたが、直近 1 ヶ月で反転して拡大基調に。';
        }
        if ($p30 < 0 && $p90 > 0) {
            return '長期では成長してきたものの、直近 1 ヶ月はやや落ち着き気味。';
        }

        // 両方プラス
        if ($p30 > 0 && $p90 > 0) {
            if ($pct7 !== null && (float)$pct7 < 0 && abs($p30) >= 5.0) {
                return '直近 1 週間は一旦落ち着いているが、中期では拡大が続いている。';
            }
            // 1 日あたりのペース比較で加速 / 鈍化を判定
            $rate30 = $p30 / 30;
            $rate90 = $p90 / 90;
            if ($rate30 > $rate90 * 1.3) {
                return '長期に渡る成長が直近で加速している。';
            }
            if ($rate30 < $rate90 * 0.7) {
                return '長期的な成長は継続しているが、直近では伸びが落ち着いてきている。';
            }
            return '成長基調が継続しており、勢いは衰えていない。';
        }

        // 両方マイナス
        if ($p30 < 0 && $p90 < 0) {
            if ($p30 > $p90) {
                return '長期的な縮小傾向にあるが、直近では下げ止まりが見られる。';
            }
            return '長期にわたって縮小傾向が続いている。';
        }

        return null;
    }

    private function signedFloatFormat(float $f): string
    {
        if ($f > 0) return '+' . number_format($f, 1);
        if ($f < 0) return '-' . number_format(abs($f), 1);
        return '0.0';
    }

    private function formatJapaneseDate(string $date): string
    {
        try {
            $dt = new \DateTime($date);
            return $dt->format('Y年n月j日');
        } catch (\Throwable $e) {
            return $date;
        }
    }

    private function truncate(string $s, int $maxLen): string
    {
        if (mb_strlen($s) <= $maxLen) {
            return $s;
        }
        return mb_substr($s, 0, $maxLen) . '…';
    }

}
