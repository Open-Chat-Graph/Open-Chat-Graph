<?php

declare(strict_types=1);

namespace App\Services\Narrative;

use App\Models\Repositories\OcNarrativeRepositoryInterface;
use Shared\MimimalCmsConfig;

/**
 * /oc/{id} ページ用の narrative (要約 + 詳細 + meta description) 生成サービス。
 *
 * - Phase 1: JP ロケール (`urlRoot === ''`) のみ。TW/TH は null を返す
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
     * @return ?array{summary: string, detail: string, meta_description: string, pattern: string}
     */
    public function generate(int $openchatId, array $oc): ?array
    {
        try {
            // V1: JP ロケールのみ
            if (MimimalCmsConfig::$urlRoot !== '') {
                return null;
            }

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
            $categoryLabel = $this->resolveCategoryLabel($oc);

            $pattern = $this->detectPattern($metrics);

            $pct30 = $this->safePct($metrics['m30'] ?? null, $curr);
            $diff30 = ($metrics['m30'] !== null) ? ($curr - (int)$metrics['m30']) : null;

            $summary = $this->buildSummary($pattern, $curr, $diff30, $pct30, $categoryLabel, $oc);
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

    private function buildSummary(string $pattern, int $curr, ?int $diff30, ?float $pct30, ?string $category, array $oc): string
    {
        $currFmt = number_format($curr);
        $catPart = $category !== null && $category !== ''
            ? sprintf('%s カテゴリのオープンチャット。', $category)
            : 'オープンチャット。';

        switch ($pattern) {
            case 'rapid_growth':
                if ($diff30 !== null && $pct30 !== null) {
                    return sprintf('急速に拡大中。現在 %s 人、過去 30 日で +%s 人 (+%.1f%%)。%s',
                        $currFmt, number_format($diff30), $pct30, $catPart);
                }
                return sprintf('急速に拡大中の新興ルーム。現在 %s 人。%s', $currFmt, $catPart);

            case 'active_growth':
                return sprintf('拡大中。現在 %s 人、過去 30 日で %s 人 (%s%%)。%s',
                    $currFmt,
                    $this->signedNumberFormat($diff30 ?? 0),
                    $this->signedFloatFormat($pct30 ?? 0.0),
                    $catPart);

            case 'decline':
                return sprintf('縮小傾向。現在 %s 人、過去 30 日で %s 人 (%s%%)。%s',
                    $currFmt,
                    $this->signedNumberFormat($diff30 ?? 0),
                    $this->signedFloatFormat($pct30 ?? 0.0),
                    $catPart);

            case 'stable':
                if ($pct30 !== null) {
                    return sprintf('安定運営中。現在 %s 人、過去 30 日で %s%% と横ばい。%s',
                        $currFmt, $this->signedFloatFormat($pct30), $catPart);
                }
                return sprintf('安定運営中。現在 %s 人。%s', $currFmt, $catPart);

            case 'new':
                return sprintf('新しく開設されたルーム。現在 %s 人。%s', $currFmt, $catPart);

            case 'stagnant':
                return sprintf('現在 %s 人。長期更新が止まっています。%s', $currFmt, $catPart);

            default:
                return sprintf('現在 %s 人。%s', $currFmt, $catPart);
        }
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

        // パターン別: 期間比較
        if ($pattern === 'new' || $pattern === 'stagnant') {
            // % 出さず絶対値で
            if ($m['m7'] !== null) {
                $diff7 = $curr - (int)$m['m7'];
                if ($diff7 !== 0) {
                    $sentences[] = sprintf('過去 7 日で %s 人。', $this->signedNumberFormat($diff7));
                }
            }
        } else {
            // 通常パターン: pct付きで出す
            $pct7 = $this->safePct($m['m7'] ?? null, $curr);
            $pct30 = $this->safePct($m['m30'] ?? null, $curr);
            $pct90 = $this->safePct($m['m90'] ?? null, $curr);

            $parts = [];
            if ($pct7 !== null && $m['m7'] !== null) {
                $parts[] = sprintf('7 日 %s%%', $this->signedFloatFormat($pct7));
            }
            if ($pct30 !== null && $m['m30'] !== null) {
                $parts[] = sprintf('30 日 %s%%', $this->signedFloatFormat($pct30));
            }
            if ($pct90 !== null && $m['m90'] !== null) {
                $parts[] = sprintf('90 日 %s%%', $this->signedFloatFormat($pct90));
            }

            if (!empty($parts)) {
                $sentences[] = sprintf('過去の推移は %s。', implode('、', $parts));
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

        // カテゴリ内順位推移
        $catId = $this->extractCategoryId($oc);
        if ($catId !== null && $catId >= 0 && $pattern !== 'stagnant' && $pattern !== 'new') {
            try {
                $pos = $this->repository->getPositionMovement($openchatId, $catId, 30);
                if ($pos['sample_n'] >= 2
                    && $pos['oldest_close'] !== null
                    && $pos['latest_close'] !== null
                ) {
                    $delta = (int)$pos['oldest_close'] - (int)$pos['latest_close'];
                    if (abs($delta) >= self::POSITION_DELTA_MENTION) {
                        $arrow = $delta > 0 ? '→' : '→';
                        $sentences[] = sprintf('カテゴリ内順位は 30 日前 %d 位 %s 現在 %d 位。',
                            (int)$pos['oldest_close'],
                            $arrow,
                            (int)$pos['latest_close']);
                    }
                }
            } catch (\Throwable $e) {
                // 順位データの取得失敗は narrative 全体を壊さない
            }
        }

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

    private function resolveCategoryLabel(array $oc): ?string
    {
        // controller は $oc['category'] (int id) のみ持つ。view で展開される label を service が知らない場合は
        // ここでは数値カテゴリ ID から AppConfig::OPEN_CHAT_CATEGORY 経由で逆引きを試みる
        $catId = $this->extractCategoryId($oc);
        if ($catId === null || $catId === 0) {
            return null;
        }
        try {
            $map = \App\Config\AppConfig::OPEN_CHAT_CATEGORY[\Shared\MimimalCmsConfig::$urlRoot] ?? [];
            $label = array_search($catId, $map, true);
            return $label !== false ? (string)$label : null;
        } catch (\Throwable $e) {
            return null;
        }
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
