<?php

declare(strict_types=1);

namespace App\Services\Narrative;

use App\Models\Repositories\OcNarrativeRepositoryInterface;
use Shared\MimimalCmsConfig;

/**
 * /oc/{id} ページ用の narrative (要約 + 詳細 + meta description) 生成サービス。
 *
 * - 多言語対応: 出力文字列は t() / sprintfT() を通す。日本語をそのまま翻訳キーに使い、
 *   ja は translation.json のフォールスルー (キー == 日本語) で従来出力を完全維持する。
 *   tw / th は translation.json のエントリで翻訳。日付・句読点は locale 別ヘルパで吸収
 * - category label 解決は呼び出し側 (Controller) の責務。Service は与えられた数値・文字列を平に消費する
 * - メンバー数推移は「決定的な状態分類カスケード」で 1 つの状態に落とす (classify())
 *   → summary 用の短い状態ラベルと detail 用のトレンド文を同じ判定から導き、矛盾を作らない
 * - 全体を try/catch で包み、異常データ / 取得失敗時は必ず null を返す
 *   → View 側で <?php if ($narrative) ?> でガードすれば、既存ページの動作が完全保持される
 *
 * 戻り値は平文 (HTML エスケープなし)。View / Metadata 側で h() を 1 度だけ通す前提。
 *
 * ## 状態分類カスケード (classify() / 優先度順)
 *  0. stagnant   : 最終更新が STAGNANT_DAYS 以上前 (データが死んでいる)
 *  1. new        : sample_n < NEW_THRESHOLD_SAMPLE_N (% はノイズ、実数で語る)
 *  2. tiny       : curr < TINY_MEMBER (小規模ルーム。% を信用せず実数・縮小文脈で語る)
 *  3. surge_up   : 直近 24h / 1 週間の急増 (diff1/diff7 が実数・% とも大)
 *  4. surge_down : 直近 24h / 1 週間の急減
 *  5. strong_growth : 直近 1 ヶ月が規模として強い (diff30>=100 or pct30>=3%)。加速 / 通常で表現
 *  6. recovering : 全期間ピークから大きく縮小 (curr<peak*PEAK_DECLINE_RATIO) かつ直近 1 ヶ月プラス
 *  7. shrinking_from_peak : 全期間ピークから大きく縮小 かつ直近も縮小 / 横ばい
 *  8. growing    : 30 日プラスでペース判定 (加速 / 鈍化 / 継続)
 *  9. gradual_*  : 30 日横ばいだが 90 日で明確に変動
 * 10. declining  : 30 日で減少傾向 (急減ほどではない)
 * 11. stable     : 上記どれにも当たらない (ほぼ横ばい)
 */
class OcNarrativeService
{
    /** sample_n がこの値未満なら「データ点が少ない」扱い (% 計算回避) */
    private const NEW_THRESHOLD_SAMPLE_N = 30;

    /**
     * 「新しく開設されたルーム」と表現してよい実開設日 (api_created_at) の上限日数。
     * sample_n が少なくても、実開設が古い / 不明なルームを「新規」と誤表示しないためのガード。
     */
    private const NEW_ROOM_MAX_AGE_DAYS = 90;

    /** curr がこの値未満は「小規模ルーム」: % はノイズなので実数 / 縮小文脈で語る */
    private const TINY_MEMBER = 50;

    /** 「横ばい」の定義: 実数 ±FLAT_DIFF_ABS 以内、または |pct| < FLAT_PCT_ABS% */
    private const FLAT_DIFF_ABS = 3;
    private const FLAT_PCT_ABS = 0.1;

    /** 短期サージ (急増 / 急減): 実数閾値 と % 閾値 を両方満たす */
    private const SURGE_DIFF_ABS = 10;
    private const SURGE_PCT_ABS = 10.0;

    /** 「直近 1 ヶ月が規模として強い」: 実数 or % のどちらかで判定 (規模重視) */
    private const STRONG_GROWTH_DIFF30 = 100;
    private const STRONG_GROWTH_PCT30 = 3.0;

    /** 全期間ピークから「大きく縮小」とみなす比率 (curr < all_time_peak * ratio) */
    private const PEAK_DECLINE_RATIO = 0.7;

    /** 30 日で「減少傾向」とみなす pct 閾値 */
    private const DECLINE_PCT = -1.5;

    /** 長期停滞: curr_date がこれより古ければ stagnant */
    private const STAGNANT_DAYS = 365;

    /** 直近 200 日ピークを言及する閾値 (現在の何倍以上か) */
    private const PEAK_MENTION_RATIO = 1.1;

    /** 単日最大伸び言及する閾値 (絶対値 or 現在比 %) */
    private const SINGLE_DAY_GROWTH_ABS = 20;
    private const SINGLE_DAY_GROWTH_PCT = 5.0;

    public function __construct(
        private OcNarrativeRepositoryInterface $repository,
    ) {
    }

    /**
     * @param int $openchatId
     * @param array $oc ルーム属性 (id, name, description, category, member, created_at など)
     * @param ?string $categoryLabel Controller 側で解決済みのカテゴリ表示名 (locale-aware)。null ならカテゴリ言及を出さない
     * @return ?array{summary: string, detail: string, meta_description: string, pattern: string}
     */
    public function generate(int $openchatId, array $oc, ?string $categoryLabel = null): ?array
    {
        try {
            $metrics = $this->repository->getMemberMetrics($openchatId);

            // 最低条件: 現在値が取れること
            if (($metrics['curr'] ?? null) === null || (int)($metrics['sample_n'] ?? 0) <= 0) {
                return null;
            }

            $curr = (int)$metrics['curr'];
            if ($curr <= 0) {
                return null;
            }

            // 実開設日 (api_created_at) からの経過日数。null = 開設日が不明。
            // 「新規」ラベルは sample_n ではなく実開設日で判定する (疎クロールの古い小規模ルーム対策)。
            $created = $this->parseApiCreatedAt($oc);
            $roomAgeDays = $created !== null ? (int)$created->diff(new \DateTime('now'))->days : null;

            // 決定的な状態分類 (summary ラベル / trend 文 / pattern を一貫して導く)
            $state = $this->classify($metrics, $roomAgeDays);

            $diff30 = $this->diff($curr, $metrics['m30'] ?? null);
            $pct30 = $this->safePct($metrics['m30'] ?? null, $curr);

            $summary = $this->buildSummary($state, $openchatId, $oc);
            $detail = $this->buildDetail($state, $metrics);
            $metaDescription = $this->buildMetaDescription($oc, $curr, $diff30, $pct30, $categoryLabel);

            // detail はデータが薄いと空になり得るが、summary (状態ラベル) があれば narrative は出す
            if ($summary === '' || $metaDescription === '') {
                return null;
            }

            return [
                'summary' => $summary,
                'detail' => $detail,
                'meta_description' => $metaDescription,
                'pattern' => $state['pattern'],
            ];
        } catch (\Throwable $e) {
            // どんな例外でも null。ページは既存ロジックで完全動作する。
            return null;
        }
    }

    /**
     * メンバー数推移を 1 つの状態に決定的に分類する。
     *
     * 戻り値:
     *  - pattern    : 機械可読の状態名 (テスト / View 用)
     *  - label      : summary 用の短い状態ラベル (「拡大中」等)。現在人数は呼び出し側で付ける
     *  - trend      : detail 用の 1 文トレンド解釈 (null なら出さない)
     *
     * 「直近を主役、長期を文脈」の原則:
     *   24h → 1 週間 → 1 ヶ月 → 3 ヶ月 → 全期間 の順で最も新しい意味のある動きを見出しにする。
     *
     * @param ?int $roomAgeDays api_created_at からの経過日数 (null = 開設日不明)
     * @return array{pattern: string, label: string, trend: ?string}
     */
    private function classify(array $m, ?int $roomAgeDays = null): array
    {
        $curr = (int)$m['curr'];
        $sampleN = (int)($m['sample_n'] ?? 0);

        // 直近を主役にするための差分・割合 (curr 基準。past が null/<=0 のものは null)
        $diff1 = $this->diff($curr, $m['m1'] ?? null);
        $diff7 = $this->diff($curr, $m['m7'] ?? null);
        $diff30 = $this->diff($curr, $m['m30'] ?? null);
        $diff90 = $this->diff($curr, $m['m90'] ?? null);
        $pct1 = $this->safePct($m['m1'] ?? null, $curr);
        $pct7 = $this->safePct($m['m7'] ?? null, $curr);
        $pct30 = $this->safePct($m['m30'] ?? null, $curr);
        $pct90 = $this->safePct($m['m90'] ?? null, $curr);

        $allTimePeak = ($m['all_time_peak'] ?? null) !== null ? (int)$m['all_time_peak'] : null;

        // ---- 0) 長期停滞 -------------------------------------------------
        if (($m['curr_date'] ?? null) !== null) {
            $daysOld = $this->daysSince((string)$m['curr_date']);
            if ($daysOld !== null && $daysOld > self::STAGNANT_DAYS) {
                return ['pattern' => 'stagnant', 'label' => '長期更新が止まっています', 'trend' => null];
            }
        }

        // ---- 3) / 4) 短期サージ (新規より前: 急増は新規でも主役にする) ----
        // 24 時間の急増 / 急減 (最優先)
        if ($this->isSurge($diff1, $pct1)) {
            return $diff1 > 0
                ? ['pattern' => 'surge_up', 'label' => '急成長中', 'trend' => '直近 24 時間で急激に人数が増えている。']
                : ['pattern' => 'surge_down', 'label' => '急減中', 'trend' => '直近 24 時間で急激に人数が減っている。'];
        }
        // 1 週間の急増 / 急減
        if ($this->isSurge($diff7, $pct7)) {
            return $diff7 > 0
                ? ['pattern' => 'surge_up', 'label' => '急成長中', 'trend' => '直近 1 週間で急激に人数が増えている。']
                : ['pattern' => 'surge_down', 'label' => '急減中', 'trend' => '直近 1 週間で急激に人数が減っている。'];
        }

        // ---- 1) 新規 (サージでなければ % はノイズ。実数で語る) -------------
        // sample_n が少ないだけでは「新規」と断定しない。実開設日 (api_created_at) が
        // 実際に新しいときのみ「新しく開設されたルーム」と表現する。
        // (開設が古い / 不明なまま疎にクロールされる小規模ルームを誤表示しないため)
        if ($sampleN < self::NEW_THRESHOLD_SAMPLE_N
            && $roomAgeDays !== null
            && $roomAgeDays <= self::NEW_ROOM_MAX_AGE_DAYS
        ) {
            return ['pattern' => 'new', 'label' => '新しく開設されたルーム', 'trend' => null];
        }

        // ---- 2) 小規模ルーム (% を信用しない。実数 / 縮小文脈で語る) ------
        if ($curr < self::TINY_MEMBER) {
            // 全期間ピークから大きく縮小しているか (文脈として後段で添える)
            $shrunk = $allTimePeak !== null && $curr < $allTimePeak * self::PEAK_DECLINE_RATIO;
            if ($shrunk) {
                return ['pattern' => 'tiny', 'label' => '小規模なルーム', 'trend' => null];
            }
            // 実数で明確に増えている小規模ルーム (横ばい閾値超え)
            if ($diff30 !== null && $diff30 > self::FLAT_DIFF_ABS) {
                return ['pattern' => 'tiny', 'label' => '小規模ながら増加中', 'trend' => null];
            }
            return ['pattern' => 'tiny', 'label' => '小規模なルーム', 'trend' => null];
        }

        // 全期間ピークから大きく縮小しているか (recovering / shrinking_from_peak 判定用)
        $deepBelowPeak = $allTimePeak !== null && $curr < $allTimePeak * self::PEAK_DECLINE_RATIO;

        // ---- 5) 直近 1 ヶ月が規模として強い (減速していても規模で前向き) -
        // 「成長と言うには最低 1 ヶ月で見る」: m30 比較を主役にする
        $recentStrong = ($diff30 !== null && $diff30 >= self::STRONG_GROWTH_DIFF30)
            || ($pct30 !== null && $pct30 >= self::STRONG_GROWTH_PCT30);
        if ($recentStrong) {
            // 「急拡大中」は % でも勢いがあるとき限定 (大規模 × 低 % を過大評価しない)。
            // 実数規模だけ強いルーム (例 +230/月 だが +1.1%) は「拡大中」+ 規模で前向きに。
            $pctStrong = $pct30 !== null && $pct30 >= self::STRONG_GROWTH_PCT30;
            if ($pctStrong && $this->isAccelerating($pct30, $pct90)) {
                return ['pattern' => 'strong_growth', 'label' => '急拡大中', 'trend' => '直近 1 ヶ月で大きく人数を伸ばしており、さらにペースを上げている。'];
            }
            return ['pattern' => 'strong_growth', 'label' => '拡大中', 'trend' => '直近 1 ヶ月で大きく人数を伸ばしている。'];
        }

        // ---- 6) 全期間ピークから縮小 + 直近 1 ヶ月は回復 -----------------
        if ($deepBelowPeak && $pct30 !== null && !$this->isFlat($diff30, $pct30) && $pct30 > 0) {
            return ['pattern' => 'recovering', 'label' => '直近は増加に転じている', 'trend' => 'ピーク時より縮小したものの、直近 1 ヶ月は増加に転じている。'];
        }

        // ---- 7) 全期間ピークから縮小 + 直近も縮小 / 横ばい --------------
        if ($deepBelowPeak) {
            return ['pattern' => 'shrinking_from_peak', 'label' => '縮小傾向', 'trend' => null];
            // ピーク時人数→現在人数の具体文は detail のピーク文で補完する
        }

        // ---- 8) 30 日プラスでペース判定 ---------------------------------
        $flat30 = $this->isFlat($diff30, $pct30);
        $flat90 = $this->isFlat($diff90, $pct90);

        if ($pct30 !== null && !$flat30 && $pct30 > 0) {
            // 90 日も見て加速 / 鈍化 / 継続を判定
            if ($pct90 !== null && $pct90 > 0) {
                if ($this->isAccelerating($pct30, $pct90)) {
                    return ['pattern' => 'growing', 'label' => '増加中', 'trend' => '着実に成長を続けており、直近 1 ヶ月で伸びが加速している。'];
                }
                if ($this->isDecelerating($pct30, $pct90)) {
                    // 直近 1 週間が一服しているかどうかで表現を分ける
                    if ($pct7 !== null && $this->isFlat($diff7, $pct7)) {
                        return ['pattern' => 'growing', 'label' => '増加中', 'trend' => '中期では成長が続いているが、直近 1 週間は伸びが一服している。'];
                    }
                    return ['pattern' => 'growing', 'label' => '増加中', 'trend' => '成長は続いているが、直近では伸びがやや緩やかになっている。'];
                }
            }
            // 直近 1 週間が横ばいなら明示
            if ($pct7 !== null && $this->isFlat($diff7, $pct7)) {
                return ['pattern' => 'growing', 'label' => '増加中', 'trend' => '直近 1 ヶ月で着実に人数を伸ばしているが、直近 1 週間は一服している。'];
            }
            return ['pattern' => 'growing', 'label' => '増加中', 'trend' => '直近 1 ヶ月で着実に人数を伸ばしている。'];
        }

        // ---- 9) 30 日横ばいだが 90 日で明確に変動 (じわじわ系) ----------
        if ($flat30 && !$flat90 && $pct90 !== null) {
            return $pct90 > 0
                ? ['pattern' => 'gradual_up', 'label' => 'じわじわ増加中', 'trend' => '直近 1 ヶ月はほぼ横ばいだが、3 ヶ月単位では着実に人数が増えている。']
                : ['pattern' => 'gradual_down', 'label' => 'じわじわ減少中', 'trend' => '直近 1 ヶ月はほぼ横ばいだが、3 ヶ月単位では緩やかに人数が減っている。'];
        }

        // ---- 10) 30 日で減少傾向 ---------------------------------------
        if ($pct30 !== null && !$flat30 && $pct30 <= self::DECLINE_PCT) {
            // 90 日と比べて下げ止まりが見えるか
            if ($pct90 !== null && $pct90 < 0 && $pct30 > $pct90) {
                return ['pattern' => 'declining', 'label' => '縮小傾向', 'trend' => '縮小傾向にあるが、直近では下げ止まりが見られる。'];
            }
            return ['pattern' => 'declining', 'label' => '縮小傾向', 'trend' => '直近 1 ヶ月で人数が減少している。'];
        }

        // ---- 11) 安定 (ほぼ横ばい) -------------------------------------
        if ($flat30 && $flat90) {
            return ['pattern' => 'stable', 'label' => '安定運営中', 'trend' => '長期的にも大きな変動はなく、安定した運営が続いている。'];
        }
        return ['pattern' => 'stable', 'label' => '安定運営中', 'trend' => null];
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
            return t('直近 1 時間・24 時間・1 週間すべての成長ランキングで全体 1 位を独占、いま間違いなく全オープンチャット中もっとも勢いのあるルーム');
        }

        // 1 位 (期間別)
        if ($w !== null && $w <= 1) {
            return t('過去 1 週間で全オープンチャット中もっとも人数を伸ばしているルーム (週間成長ランキング 1 位)');
        }
        if ($d !== null && $d <= 1) {
            return t('直近 24 時間で全オープンチャット中もっとも人数を伸ばしているルーム (24 時間成長ランキング 1 位)');
        }
        if ($h !== null && $h <= 1) {
            return t('今この瞬間にもっとも人数が増えているルーム (直近 1 時間成長ランキング 1 位)');
        }

        // トップ 10 入り
        if ($w !== null && $w <= 10) {
            return sprintfT('過去 1 週間の成長ランキングで全体 %d 位、急成長中の注目ルーム', $w);
        }
        if ($d !== null && $d <= 10) {
            return sprintfT('直近 24 時間の成長ランキングで全体 %d 位、いまよく伸びているルーム', $d);
        }
        if ($h !== null && $h <= 10) {
            return sprintfT('直近 1 時間の成長ランキングで全体 %d 位、今動きが活発', $h);
        }

        // トップ 50 入り
        if ($w !== null && $w <= 50) {
            return sprintfT('過去 1 週間の成長ランキングで全体 %d 位の注目ルーム', $w);
        }
        if ($d !== null && $d <= 50) {
            return sprintfT('直近 24 時間の成長ランキングで全体 %d 位の注目ルーム', $d);
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
     *
     * ※ このランキング評価ロジックは仕様確定済み。変更しない。
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
                if ($avg <= 10) return t('総合急上昇でも常時トップ 10 入り、オープンチャット中でも最高クラスの活発さ');
                if ($avg <= 50) return t('総合急上昇で継続して上位 50 位以内、非常に活発な運営');
                if ($avg <= 100) return t('総合急上昇で上位 100 位前後を維持する活発なルーム');
                if ($avg <= 200) return t('総合急上昇ランキングに継続的に登場している活発なルーム');
            }
        } catch (\Throwable $e) {}

        try {
            $r = $this->repository->getAveragePosition($openchatId, 0, 'ranking', 30);
            if ($r['sample_n'] >= 5 && $r['avg_position'] !== null) {
                $avg = (float)$r['avg_position'];
                if ($avg <= 10) return t('全体ランキングでも常時トップ 10 入り、オープンチャット全体を代表する規模');
                if ($avg <= 50) return t('全体ランキングで継続して上位 50 位以内に入る大規模な代表的ルーム');
                if ($avg <= 100) return t('全体ランキングで上位 100 位以内に常在する大規模ルーム');
                if ($avg <= 200) return t('全体ランキング上位に継続的に登場する規模のルーム');
            }
        } catch (\Throwable $e) {}

        $catId = $this->extractCategoryId($oc);
        if ($catId !== null && $catId > 0) {
            try {
                $r = $this->repository->getAveragePosition($openchatId, $catId, 'rising', 30);
                if ($r['sample_n'] >= 5 && $r['avg_position'] !== null) {
                    $avg = (float)$r['avg_position'];
                    if ($avg <= 10) return t('カテゴリ内の急上昇ランキングで継続して上位 10 位以内、いま活発に動いているルーム');
                    if ($avg <= 30) return t('カテゴリ内の急上昇ランキングで上位 30 位以内を維持する活発なルーム');
                }
            } catch (\Throwable $e) {}
        }

        return null;
    }

    /**
     * summary 1 行: {評価ラベル(ランキング, あれば)}。{状態ラベル}。
     * 現在人数はページ上部 (ルーム名横) に表示済みなのでここでは繰り返さない。
     */
    private function buildSummary(array $state, int $openchatId, array $oc): string
    {
        // 評価ラベル (ランキング / 急上昇) を最優先で出す。該当無しなら空。
        $evalLabel = $this->buildSummaryEvalLabel($openchatId, $oc);
        $evalPart = $evalLabel !== null ? $evalLabel . $this->sentenceEnd() : '';

        return $evalPart . t($state['label']) . $this->sentenceEnd();
    }

    /**
     * detail (複数文を 1 段落に連結):
     *   トレンド文 → ピーク / 縮小文脈 → 単日最大の伸び → 期間比較数字(最後)
     *
     * 開設情報・カテゴリ・現在人数はページ内の「ルーム開設」「オプチャ情報」等に
     * 表示済みの重複情報なので出さない (分析独自の情報だけに絞る)。
     */
    private function buildDetail(array $state, array $m): string
    {
        $sentences = [];
        $curr = (int)$m['curr'];
        $pattern = $state['pattern'];

        // トレンド文 (状態分類が選んだ 1 文)
        if ($state['trend'] !== null && $state['trend'] !== '') {
            $sentences[] = t($state['trend']);
        }

        // 全期間ピークから大きく縮小しているルームは「かつて N 人 → 現在 M 人」を文脈として添える。
        // (見出しは直近の動きが主役。これは文脈として後ろに置く)
        $allTimePeak = ($m['all_time_peak'] ?? null) !== null ? (int)$m['all_time_peak'] : null;
        $shrinkContextAdded = false;
        if ($allTimePeak !== null
            && $curr < $allTimePeak * self::PEAK_DECLINE_RATIO
            && ($pattern === 'tiny' || $pattern === 'shrinking_from_peak' || $pattern === 'recovering' || $pattern === 'declining')
        ) {
            $peakDate = ($m['all_time_peak_date'] ?? null) !== null ? (string)$m['all_time_peak_date'] : null;
            if ($peakDate !== null) {
                $sentences[] = sprintfT('かつて %s には %s 人規模だったが、現在は %s 人。',
                    $this->localizedDate($peakDate),
                    number_format($allTimePeak),
                    number_format($curr));
            } else {
                $sentences[] = sprintfT('かつては %s 人規模だったが、現在は %s 人。',
                    number_format($allTimePeak),
                    number_format($curr));
            }
            $shrinkContextAdded = true;
        }

        // 直近 200 日ピーク言及 (全期間ピークの縮小文脈を既に出した場合は重複させない)
        if (!$shrinkContextAdded && ($m['peak_high'] ?? null) !== null && ($m['peak_date'] ?? null) !== null) {
            $peakHigh = (int)$m['peak_high'];
            if ($peakHigh > $curr * self::PEAK_MENTION_RATIO) {
                $sentences[] = sprintfT('ピークは %s の %s 人。',
                    $this->localizedDate((string)$m['peak_date']),
                    number_format($peakHigh));
            }
        }

        // 単日急変動 (減少 / 停滞 / 小規模では誤解を招くため出さない)
        if (!in_array($pattern, ['declining', 'shrinking_from_peak', 'surge_down', 'stagnant', 'tiny'], true)) {
            if (($m['max_single_day_growth'] ?? null) !== null && ($m['max_growth_date'] ?? null) !== null) {
                $growth = (int)$m['max_single_day_growth'];
                $shouldMention = $growth >= self::SINGLE_DAY_GROWTH_ABS
                    || ($curr > 0 && $growth >= ($curr * self::SINGLE_DAY_GROWTH_PCT / 100));
                if ($shouldMention && $growth > 0) {
                    $sentences[] = sprintfT('単日最大の伸びは %s の +%s 人。',
                        $this->localizedDate((string)$m['max_growth_date']),
                        number_format($growth));
                }
            }
        }

        // 期間比較数字 (最後に置く)
        $periodSentence = $this->buildPeriodSentence($pattern, $m, $curr);
        if ($periodSentence !== null) {
            $sentences[] = $periodSentence;
        }

        // 停滞情報
        if ($pattern === 'stagnant' && ($m['curr_date'] ?? null) !== null) {
            $days = $this->daysSince((string)$m['curr_date']);
            if ($days !== null && $days > 0) {
                $months = (int)floor($days / 30);
                if ($months >= 1) {
                    $sentences[] = sprintfT('最終更新は約 %d ヶ月前です。', $months);
                }
            }
        }

        if (empty($sentences)) {
            return '';
        }
        $sentences = array_slice($sentences, 0, 6);

        // 1 段落として流し込む (ja / tw は句点で終わるので直結、th はスペース区切り)
        return implode($this->detailGlue(), $sentences);
    }

    /**
     * 期間比較数字の 1 文を組み立てる。
     * - new / stagnant: % は出さず実数のみ (7 日)
     * - 小規模 (tiny): % はノイズなので実数のみ (30 日 / 7 日)
     * - 通常: 3 ヶ月 / 1 ヶ月 を実数 + % 併記。
     *   1 週間の数字はヘッダーの stats に常時表示されているため本文には出さない
     */
    private function buildPeriodSentence(string $pattern, array $m, int $curr): ?string
    {
        if ($pattern === 'new' || $pattern === 'stagnant' || $pattern === 'tiny') {
            $parts = [];
            $diff30 = $this->diff($curr, $m['m30'] ?? null);
            $diff7 = $this->diff($curr, $m['m7'] ?? null);
            if ($diff30 !== null && $diff30 !== 0) {
                $parts[] = sprintfT('過去 1 ヶ月で %s 人', $this->signedNumberFormat($diff30));
            }
            if ($diff7 !== null && $diff7 !== 0) {
                $parts[] = sprintfT('過去 7 日で %s 人', $this->signedNumberFormat($diff7));
            }
            if (empty($parts)) {
                return null;
            }
            return implode($this->listSep(), $parts) . $this->sentenceEnd();
        }

        // 通常パターン: 実数 + % 併記
        $pct30 = $this->safePct($m['m30'] ?? null, $curr);
        $pct90 = $this->safePct($m['m90'] ?? null, $curr);
        $diff30 = $this->diff($curr, $m['m30'] ?? null);
        $diff90 = $this->diff($curr, $m['m90'] ?? null);

        $parts = [];
        if ($pct90 !== null && $diff90 !== null) {
            $parts[] = sprintfT('過去 3 ヶ月で %s 人 (%s%%)', $this->signedNumberFormat($diff90), $this->signedFloatFormat($pct90));
        }
        if ($pct30 !== null && $diff30 !== null) {
            $parts[] = sprintfT('1 ヶ月で %s 人 (%s%%)', $this->signedNumberFormat($diff30), $this->signedFloatFormat($pct30));
        }
        if (empty($parts)) {
            return null;
        }
        return implode($this->listSep(), $parts) . $this->sentenceEnd();
    }

    private function buildMetaDescription(array $oc, int $curr, ?int $diff30, ?float $pct30, ?string $category): string
    {
        $name = trim((string)($oc['name'] ?? ''));
        if ($name === '') {
            $name = t('オープンチャット');
        }

        $currFmt = number_format($curr);

        $head = sprintfT('%s の統計・メンバー数推移。', $this->truncate($name, 30));

        $stat = sprintfT('現在 %s 人', $currFmt);
        if ($pct30 !== null && $diff30 !== null) {
            $stat .= sprintfT('、過去 30 日 %s%%', $this->signedFloatFormat($pct30));
        }
        $stat .= $this->sentenceEnd();

        $cat = ($category !== null && $category !== '') ? sprintfT('%s カテゴリ。', $category) : '';

        // LINE description 冒頭を足す (短縮版)。meta 属性に改行が混入しないよう連続空白を 1 個に潰す
        $desc = '';
        $rawDesc = trim(preg_replace('/\s+/u', ' ', (string)($oc['description'] ?? '')) ?? '');
        if ($rawDesc !== '') {
            $desc = $this->truncate($rawDesc, 50);
        }

        $combined = $head . $stat . $cat . $desc;
        return $this->truncate($combined, 160);
    }

    /**
     * ルーム開設日 (api_created_at) を DateTime にパース。
     * - api_created_at は LINE API 由来の実開設日。created_at (当サイトへの登録日) は使わない
     * - null / 空 / 0 / '0000-00-00 00:00:00' / パース不能 はすべて null (= 開設日不明)
     */
    private function parseApiCreatedAt(array $oc): ?\DateTime
    {
        $apiCreatedAt = $oc['api_created_at'] ?? null;
        if ($apiCreatedAt === null || $apiCreatedAt === '' || $apiCreatedAt === 0 || $apiCreatedAt === '0000-00-00 00:00:00') {
            return null;
        }
        try {
            return is_int($apiCreatedAt) || ctype_digit((string)$apiCreatedAt)
                ? (new \DateTime())->setTimestamp((int)$apiCreatedAt)
                : new \DateTime((string)$apiCreatedAt);
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
     * curr - past の実数差分。past が null なら null。
     */
    private function diff(int $curr, ?int $past): ?int
    {
        return $past !== null ? $curr - $past : null;
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

    /**
     * 「横ばい」判定: 実数 ±FLAT_DIFF_ABS 以内、または |pct| < FLAT_PCT_ABS%。
     * オプチャの世界では実数十人の増加は意味があるため、+20 / +65 は横ばいではない。
     */
    private function isFlat(?int $diff, ?float $pct): bool
    {
        $absDiff = $diff !== null ? abs($diff) : 0;
        $absPct = $pct !== null ? abs($pct) : 0.0;
        // diff が取れているなら実数で判定 (実数十人は横ばいではない)。
        if ($diff !== null) {
            return $absDiff <= self::FLAT_DIFF_ABS;
        }
        // diff 不明時のみ % で判定
        return $absPct < self::FLAT_PCT_ABS;
    }

    /**
     * 短期サージ判定: 実数 |diff| >= SURGE_DIFF_ABS かつ |pct| >= SURGE_PCT_ABS%。
     */
    private function isSurge(?int $diff, ?float $pct): bool
    {
        if ($diff === null || $pct === null) {
            return false;
        }
        return abs($diff) >= self::SURGE_DIFF_ABS && abs($pct) >= self::SURGE_PCT_ABS;
    }

    /**
     * 1 日あたりのペース比較で加速しているか。
     */
    private function isAccelerating(?float $pct30, ?float $pct90): bool
    {
        if ($pct30 === null || $pct90 === null || $pct90 <= 0) {
            return false;
        }
        return ($pct30 / 30) > ($pct90 / 90) * 1.3;
    }

    /**
     * 1 日あたりのペース比較で鈍化しているか。
     */
    private function isDecelerating(?float $pct30, ?float $pct90): bool
    {
        if ($pct30 === null || $pct90 === null || $pct90 <= 0) {
            return false;
        }
        return ($pct30 / 30) < ($pct90 / 90) * 0.7;
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

    /**
     * locale 別の日付表記 (年月日)。
     * - ja / tw: 「Y年n月j日」(中国語も同じ漢字表記が自然)
     * - th: 数値表記「j/n/Y」(サイト全体が数値日付。タイ語月名は使わない)
     */
    private function localizedDate(string $date): string
    {
        try {
            $dt = new \DateTime($date);
        } catch (\Throwable $e) {
            return $date;
        }
        return MimimalCmsConfig::$urlRoot === '/th'
            ? $dt->format('j/n/Y')
            : $dt->format('Y年n月j日');
    }

    /**
     * locale 別の列挙区切り。ja / tw は読点「、」、th は半角スペース。
     */
    private function listSep(): string
    {
        return MimimalCmsConfig::$urlRoot === '/th' ? ' ' : '、';
    }

    /**
     * locale 別の文末 / 区切り。ja / tw は句点「。」、th は半角スペース。
     * t() テンプレート外で動的に連結する箇所のみで使う。
     */
    private function sentenceEnd(): string
    {
        return MimimalCmsConfig::$urlRoot === '/th' ? ' ' : '。';
    }

    /**
     * detail の文を 1 段落に連結する区切り。ja / tw は句点で終わるので直結、th はスペース。
     */
    private function detailGlue(): string
    {
        return MimimalCmsConfig::$urlRoot === '/th' ? ' ' : '';
    }

    private function truncate(string $s, int $maxLen): string
    {
        if (mb_strlen($s) <= $maxLen) {
            return $s;
        }
        return mb_substr($s, 0, $maxLen) . '…';
    }
}
