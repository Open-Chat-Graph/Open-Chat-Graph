<?php

declare(strict_types=1);

namespace App\Services\Alpha;

use App\Models\ApiRepositories\Alpha\AlphaAlertRepository;

/**
 * Alpha 通知/アラートの算出サービス（毎時 cron から呼ばれる）。
 *
 * 全ユーザーのウォッチを走査し、以下を算出して alpha_notification に保存する:
 *   ① ウォッチキーワード … LINE公式検索APIで一致する「新しい部屋」を検出（未通知 emid のみ）
 *   ② ウォッチ部屋        … 指定人数±/%± の上昇・下降を検出
 *   ③ マイリスト          … %しきい値での上昇・下降を検出
 *
 * 負荷対策:
 *   - LINE公式APIは「登録キーワード(＋カテゴリ)のユニーク集合」に対してのみ叩く（ユーザー数に依存しない）。
 *   - ②③の差分は既存の statistics_ranking_hour（毎時クロールで確定済み）をまとめて1クエリで取得。
 *
 * 重複防止:
 *   - ① emid は alpha_keyword_seen に記録、2回目以降は通知しない。
 *   - ②③ は alpha_notification.dedup_key（時刻 bucket 込み）で同一毎時の重複保存を防ぐ。
 *
 * 毎時処理を止めないため、各ステップは try/catch で握りつぶしログのみ（呼び出し側で集約）。
 *
 * @return array{computedAt:string, keywordHits:int, movements:int, errors:array<int,string>}
 */
class AlphaAlertService
{
    /** LINE検索APIに渡す1キーワードあたりの取得件数 */
    private const SEARCH_LIMIT = 20;

    public function __construct(
        private AlphaAlertRepository $repo,
        private AlphaKeywordSearchClient $searchClient,
    ) {
    }

    /**
     * 毎時 cron 本体。
     *
     * @return array{computedAt:string, keywordHits:int, movements:int, errors:array<int,string>}
     */
    public function run(): array
    {
        $computedAt = date('Y-m-d H:i:s');
        $hourBucket = date('Y-m-d-H'); // dedup 用の時刻 bucket（同一毎時の重複保存を防ぐ）
        $errors = [];
        $keywordHits = 0;
        $movements = 0;

        try {
            $keywordHits = $this->computeKeywordHits();
        } catch (\Throwable $e) {
            $errors[] = 'keyword: ' . $e->getMessage();
        }

        try {
            $movements += $this->computeRoomMovements($hourBucket);
        } catch (\Throwable $e) {
            $errors[] = 'room: ' . $e->getMessage();
        }

        try {
            $movements += $this->computeMylistMovements($hourBucket);
        } catch (\Throwable $e) {
            $errors[] = 'mylist: ' . $e->getMessage();
        }

        return [
            'computedAt' => $computedAt,
            'keywordHits' => $keywordHits,
            'movements' => $movements,
            'errors' => $errors,
        ];
    }

    // ====================== ① キーワード新規検出 ======================

    private function computeKeywordHits(): int
    {
        $watches = $this->repo->getAllKeywordWatches();
        if (empty($watches)) {
            return 0;
        }

        // ユニークキーワード集合に対してのみ LINE API を叩く（負荷対策）
        $distinct = $this->repo->getDistinctKeywords();
        $searchCache = []; // keyword => squares[]
        foreach ($distinct as $d) {
            $kw = $d['keyword'];
            if (!isset($searchCache[$kw])) {
                $searchCache[$kw] = $this->searchClient->search($kw, self::SEARCH_LIMIT);
            }
        }

        $count = 0;
        foreach ($watches as $w) {
            $squares = $searchCache[$w['keyword']] ?? [];
            if (empty($squares)) {
                continue;
            }

            // カテゴリ絞り込み: LINE検索 square にはカテゴリが無いため、open_chat 既登録のものは
            // そのカテゴリで照合、未登録（=真の新規）はカテゴリ不問で拾う（本家登録を待たない方針）。
            $emids = array_map(static fn($s) => $s['emid'], $squares);
            $registered = $this->repo->getRegisteredEmidMap($emids); // emid => open_chat_id
            $ocMap = $this->repo->getOpenChatMap(array_values($registered));

            $seen = $this->repo->getSeenEmids($w['id']);

            foreach ($squares as $sq) {
                $emid = $sq['emid'];
                if (isset($seen[$emid])) {
                    continue; // 既に通知済み
                }

                $ocId = $registered[$emid] ?? null;

                // カテゴリ指定があり、既登録部屋のカテゴリが一致しなければスキップ
                if ($w['category'] !== null && $ocId !== null) {
                    $cat = isset($ocMap[$ocId]['category']) ? (int)$ocMap[$ocId]['category'] : null;
                    if ($cat !== $w['category']) {
                        continue;
                    }
                }

                $payload = [
                    'keyword' => $w['keyword'],
                    'category' => $w['category'],
                    'emid' => $emid,
                    'openChatId' => $ocId, // 未登録なら null
                    'name' => $sq['name'],
                    'desc' => $sq['desc'],
                    'img' => $sq['profileImageObsHash'],
                    'joinMethodType' => $sq['joinMethodType'],
                    'isRegistered' => $ocId !== null,
                    'member' => $ocId !== null && isset($ocMap[$ocId]['member']) ? (int)$ocMap[$ocId]['member'] : null,
                ];

                $dedup = 'kw:' . $w['id'] . ':' . $emid;
                $inserted = $this->repo->insertNotification($w['user_id'], 'keyword', $payload, $dedup);
                $this->repo->markEmidSeen($w['id'], $emid);
                if ($inserted) {
                    $count++;
                }
            }
        }

        return $count;
    }

    // ====================== ② ウォッチ部屋の人数±/%± ======================

    private function computeRoomMovements(string $hourBucket): int
    {
        $watches = $this->repo->getAllRoomWatches();
        if (empty($watches)) {
            return 0;
        }

        $ids = array_values(array_unique(array_map(static fn($w) => $w['open_chat_id'], $watches)));
        $diffMap = $this->repo->getHourlyDiffMap($ids);
        $ocMap = $this->repo->getOpenChatMap($ids);

        $count = 0;
        foreach ($watches as $w) {
            $ocId = $w['open_chat_id'];
            if (!isset($diffMap[$ocId])) {
                continue; // この毎時で member 変動が無い / ランキング外
            }
            $diff = $diffMap[$ocId]['diff_member'];
            $percent = $diffMap[$ocId]['percent_increase'];

            $direction = $this->evaluateRoomThreshold($w, $diff, $percent);
            if ($direction === null) {
                continue;
            }

            $payload = $this->buildMovementPayload('room', $ocId, $ocMap[$ocId] ?? null, $diff, $percent, $direction);
            $dedup = 'room:' . $ocId . ':' . $direction . ':' . $hourBucket;
            if ($this->repo->insertNotification($w['user_id'], 'room', $payload, $dedup)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * 部屋ウォッチのしきい値判定。
     * 上昇: (up_member 指定なら diff>=up_member) かつ/または (up_percent 指定なら percent>=up_percent)
     *   → 指定された条件「すべて」を満たしたら上昇扱い（両方指定なら AND）。
     * 下降も同様（符号反転、絶対値比較）。
     *
     * @return 'up'|'down'|null
     */
    private function evaluateRoomThreshold(array $w, int $diff, float $percent): ?string
    {
        // 上昇判定
        if ($w['up_member'] !== null || $w['up_percent'] !== null) {
            $ok = true;
            if ($w['up_member'] !== null) {
                $ok = $ok && ($diff >= $w['up_member']);
            }
            if ($w['up_percent'] !== null) {
                $ok = $ok && ($percent >= $w['up_percent']);
            }
            if ($ok && ($diff > 0 || $percent > 0)) {
                return 'up';
            }
        }

        // 下降判定
        if ($w['down_member'] !== null || $w['down_percent'] !== null) {
            $ok = true;
            if ($w['down_member'] !== null) {
                $ok = $ok && ($diff <= -abs($w['down_member']));
            }
            if ($w['down_percent'] !== null) {
                $ok = $ok && ($percent <= -abs($w['down_percent']));
            }
            if ($ok && ($diff < 0 || $percent < 0)) {
                return 'down';
            }
        }

        return null;
    }

    // ====================== ③ マイリスト %上昇/下降 ======================

    private function computeMylistMovements(string $hourBucket): int
    {
        $thresholds = $this->repo->getAllEnabledMylistThresholds();
        if (empty($thresholds)) {
            return 0;
        }

        // 全対象ユーザーのマイリスト id を集約してまとめて差分取得
        $userIdsToOcIds = [];
        $allIds = [];
        foreach ($thresholds as $t) {
            $ocIds = $this->repo->getMylistOpenChatIds($t['user_id']);
            if (empty($ocIds)) {
                continue;
            }
            $userIdsToOcIds[$t['user_id']] = $ocIds;
            foreach ($ocIds as $id) {
                $allIds[$id] = true;
            }
        }
        if (empty($allIds)) {
            return 0;
        }

        $allIds = array_keys($allIds);
        $diffMap = $this->repo->getHourlyDiffMap($allIds);
        $ocMap = $this->repo->getOpenChatMap($allIds);

        $thresholdByUser = [];
        foreach ($thresholds as $t) {
            $thresholdByUser[$t['user_id']] = $t;
        }

        $count = 0;
        foreach ($userIdsToOcIds as $userId => $ocIds) {
            $t = $thresholdByUser[$userId];
            foreach ($ocIds as $ocId) {
                if (!isset($diffMap[$ocId])) {
                    continue;
                }
                $diff = $diffMap[$ocId]['diff_member'];
                $percent = $diffMap[$ocId]['percent_increase'];

                $direction = null;
                if ($t['up_percent'] !== null && $percent >= $t['up_percent'] && $percent > 0) {
                    $direction = 'up';
                } elseif ($t['down_percent'] !== null && $percent <= -abs($t['down_percent']) && $percent < 0) {
                    $direction = 'down';
                }
                if ($direction === null) {
                    continue;
                }

                $payload = $this->buildMovementPayload('mylist', $ocId, $ocMap[$ocId] ?? null, $diff, $percent, $direction);
                $dedup = 'mylist:' . $ocId . ':' . $direction . ':' . $hourBucket;
                if ($this->repo->insertNotification($userId, 'mylist', $payload, $dedup)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    // ====================== 共通: movement payload ======================

    private function buildMovementPayload(
        string $kind,
        int $ocId,
        ?array $oc,
        int $diff,
        float $percent,
        string $direction
    ): array {
        return [
            'kind' => $kind, // 'room' | 'mylist'
            'openChatId' => $ocId,
            'name' => $oc['name'] ?? '',
            'img' => $oc['img_url'] ?? '',
            'category' => isset($oc['category']) ? (int)$oc['category'] : null,
            'currentMember' => isset($oc['member']) ? (int)$oc['member'] : null,
            'diff' => $diff,
            'percent' => $percent,
            'direction' => $direction, // 'up' | 'down'
        ];
    }
}
