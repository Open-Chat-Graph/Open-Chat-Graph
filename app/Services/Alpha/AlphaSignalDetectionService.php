<?php

declare(strict_types=1);

namespace App\Services\Alpha;

use App\Models\ApiRepositories\Alpha\AlphaAlertRepository;
use App\Models\ApiRepositories\Alpha\AlphaRoomSnapshotRepository;
use App\Models\Repositories\OcNarrativeRepositoryInterface;
use App\Services\Storage\FileStorageInterface;

/**
 * ウォッチ部屋の「機微検知」サービス（AlphaAlertService::run() から毎時呼ばれる）。
 *
 * ウォッチ部屋（alpha_room_watch_ja のユーザー×部屋）に対して3種のシグナルを検知し、
 * alpha_notification_ja に保存する（Push は AlertService の notifiedUserIds 機構に乗る）:
 *
 *   1. room_change … 部屋名/説明文/カテゴリの変更。
 *        open_chat は上書き更新で履歴が無いため、alpha_room_snapshot_ja に前回値を退避して比較する。
 *        スナップショットが無い部屋は seed するだけで通知しない（初回大量通知の防止）。
 *        ウォッチが消えた部屋のスナップショットは毎時掃除（DELETE）する。
 *        dedup: chg:{ocId}:{変更内容hash}:{hourBucket}
 *   2. rank_jump … 公式ランキング順位の急変。SQLite ranking_position（日次）を読む。
 *        (a) enter: 24時間以上ランキング不在（日次データで前回観測から2日以上空き or 初観測）→ 掲載再開/初掲載
 *        (b) jump : 前回観測から RANK_JUMP_MIN_DELTA 位以上 かつ RANK_JUMP_MIN_RATIO 以上の上昇
 *        評価カテゴリは category=0（全体）を優先し、無ければ部屋自身のカテゴリ
 *        （全体ランキングは上位数千件のみで、大半の部屋はカテゴリ別ランキングにしか載らないため）。
 *        dedup: rj:{ocId}:{date}（日1回まで。データ自体が日次なので毎時走っても重複しない）
 *   3. pace … ペース異常。AlphaInsightsService::appendPaceAnomaly と同じ式
 *        （直近7日の1日あたり増加が過去90日平均ペースの PACE_ANOMALY_RATIO 倍以上 かつ diff7>=最低増加）。
 *        dedup: pace:{ocId}:{date}（日1回）
 *
 * 各シグナルは try/catch で独立させ、1種の失敗が他を止めない（エラーは戻り値で集約）。
 */
class AlphaSignalDetectionService
{
    /** rank_jump(enter): 「24時間以上不在」とみなす日次観測の最小間隔（日）。日次データなので2日空き=丸1日以上不在 */
    private const RANK_ENTER_ABSENT_MIN_DAYS = 2;

    /** rank_jump(jump): 上昇とみなす最小順位差 */
    private const RANK_JUMP_MIN_DELTA = 30;

    /** rank_jump(jump): 上昇とみなす最小上昇率（前回順位比） */
    private const RANK_JUMP_MIN_RATIO = 0.30;

    /** rank_jump(jump): 前回観測がこれより古い場合は jump 判定しない（不在明けの比較は enter 側が拾う） */
    private const RANK_JUMP_MAX_GAP_DAYS = 2;

    /** rank_jump: SQLite を遡る日数（enter の不在判定に十分な窓） */
    private const RANKING_LOOKBACK_DAYS = 14;

    /** pace: 直近ペースが過去90日平均ペースの何倍以上か（AlphaInsightsService::PACE_ANOMALY_RATIO と同値） */
    private const PACE_ANOMALY_RATIO = 2.0;

    /** pace: 直近7日の最低実増加（AlphaInsightsService::PACE_ANOMALY_MIN_DIFF7 と同値） */
    private const PACE_ANOMALY_MIN_DIFF7 = 15;

    /** room_change: payload に載せる説明文 old/new の最大文字数 */
    private const DESCRIPTION_EXCERPT_LEN = 200;

    /** @var array<string, true> この実行で新規通知を入れたユーザーID集合 */
    private array $notifiedUserIds = [];

    public function __construct(
        private AlphaAlertRepository $repo,
        private AlphaRoomSnapshotRepository $snapshotRepo,
        private OcNarrativeRepositoryInterface $narrativeRepository,
        private FileStorageInterface $fileStorage,
    ) {
    }

    /**
     * メトリクスの「現在の基準日」を毎時クロール基準時刻 ('Y-m-d') から解決する。
     * cron 時刻ファイルが空 / 不正 / 取得不能なら null を返し、Repository 側は従来の
     * date('now') (SQLite 実行時刻、UTC) フォールバックで動く。
     */
    private function resolveBaseDate(): ?string
    {
        try {
            $cron = trim($this->fileStorage->getContents('@hourlyCronUpdatedAtDatetime'));
            if ($cron === '') {
                return null;
            }
            return (new \DateTime($cron))->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * 毎時の機微検知本体。
     *
     * @param string $hourBucket dedup 用の時刻 bucket（'Y-m-d-H'）
     * @return array{count:int, notifiedUserIds:string[], errors:array<int,string>}
     */
    public function detect(string $hourBucket): array
    {
        $this->notifiedUserIds = [];
        $errors = [];
        $count = 0;
        $date = date('Y-m-d');

        $watchersByOc = $this->groupWatchersByOpenChatId($this->repo->getAllRoomWatches());
        $ids = array_keys($watchersByOc);
        $ocMap = !empty($ids) ? $this->repo->getOpenChatMap($ids) : [];

        try {
            $count += $this->detectRoomChanges($watchersByOc, $ocMap, $hourBucket);
        } catch (\Throwable $e) {
            $errors[] = 'room_change: ' . $e->getMessage();
        }

        if (!empty($ids)) {
            try {
                $count += $this->detectRankJumps($watchersByOc, $ocMap);
            } catch (\Throwable $e) {
                $errors[] = 'rank_jump: ' . $e->getMessage();
            }

            try {
                $count += $this->detectPaceAnomalies($watchersByOc, $ocMap, $date);
            } catch (\Throwable $e) {
                $errors[] = 'pace: ' . $e->getMessage();
            }
        }

        return [
            'count' => $count,
            'notifiedUserIds' => array_keys($this->notifiedUserIds),
            'errors' => $errors,
        ];
    }

    /**
     * @param array<int, array{user_id:string, open_chat_id:int}> $watches
     * @return array<int, string[]> open_chat_id => その部屋をウォッチしているユーザーID（ユニーク）
     */
    private function groupWatchersByOpenChatId(array $watches): array
    {
        $map = [];
        foreach ($watches as $w) {
            $map[(int)$w['open_chat_id']][(string)$w['user_id']] = true;
        }
        return array_map(static fn($set) => array_keys($set), $map);
    }

    // ====================== 1. room_change ======================

    /**
     * 部屋名/説明文/カテゴリの変更検知。
     * スナップショットが無い部屋は seed のみ（通知しない）。掃除（ウォッチ解除済み部屋のDELETE）もここで行う。
     *
     * @param array<int, string[]> $watchersByOc
     * @param array<int, array<string, mixed>> $ocMap
     */
    private function detectRoomChanges(array $watchersByOc, array $ocMap, string $hourBucket): int
    {
        $ids = array_keys($watchersByOc);

        // ウォッチが消えた部屋のスナップショットを掃除（ウォッチ0件なら全削除）
        $this->snapshotRepo->deleteSnapshotsNotIn($ids);

        if (empty($ids)) {
            return 0;
        }

        $snapshots = $this->snapshotRepo->getSnapshotMap($ids);

        $count = 0;
        foreach ($watchersByOc as $ocId => $userIds) {
            $oc = $ocMap[$ocId] ?? null;
            if ($oc === null) {
                continue; // open_chat から消えた部屋（削除等）。スナップショットは保持したままスキップ
            }
            $name = (string)($oc['name'] ?? '');
            $description = (string)($oc['description'] ?? '');
            $category = isset($oc['category']) && $oc['category'] !== null ? (int)$oc['category'] : null;

            $snap = $snapshots[$ocId] ?? null;
            if ($snap === null) {
                // 初回は seed するだけで通知しない
                $this->snapshotRepo->upsertSnapshot($ocId, $name, $description, $category);
                continue;
            }

            $changes = $this->diffSnapshot($snap, $name, $description, $category);
            if (empty($changes)) {
                continue;
            }

            $payload = [
                'openChatId' => $ocId,
                'name' => $name,
                'changes' => $changes,
            ];
            // 同じ変更内容（field+old+new の集合）を同一毎時に重複保存しない
            $hash = substr(sha1(json_encode($changes, JSON_UNESCAPED_UNICODE) ?: ''), 0, 12);
            $dedup = 'chg:' . $ocId . ':' . $hash . ':' . $hourBucket;

            foreach ($userIds as $userId) {
                if ($this->repo->insertNotification($userId, 'room_change', $payload, $dedup)) {
                    $this->notifiedUserIds[$userId] = true;
                    $count++;
                }
            }

            // 通知後にスナップショットを現在値へ更新（次回以降は新しい値と比較）
            $this->snapshotRepo->upsertSnapshot($ocId, $name, $description, $category);
        }

        return $count;
    }

    /**
     * スナップショットと現在値の差分を返す。old/new は文字列（フロント契約。category は数値文字列）。
     * description は各 DESCRIPTION_EXCERPT_LEN 文字に切り詰める。
     *
     * @param array{name:string, description:string, category:?int} $snap
     * @return array<int, array{field:string, old:string, new:string}>
     */
    private function diffSnapshot(array $snap, string $name, string $description, ?int $category): array
    {
        $changes = [];
        if ($snap['name'] !== $name) {
            $changes[] = ['field' => 'name', 'old' => $snap['name'], 'new' => $name];
        }
        if ($snap['description'] !== $description) {
            $changes[] = [
                'field' => 'description',
                'old' => $this->excerpt($snap['description']),
                'new' => $this->excerpt($description),
            ];
        }
        if ($snap['category'] !== $category) {
            $changes[] = [
                'field' => 'category',
                'old' => (string)($snap['category'] ?? 0),
                'new' => (string)($category ?? 0),
            ];
        }
        return $changes;
    }

    private function excerpt(string $s): string
    {
        return mb_strlen($s) > self::DESCRIPTION_EXCERPT_LEN
            ? mb_substr($s, 0, self::DESCRIPTION_EXCERPT_LEN)
            : $s;
    }

    // ====================== 2. rank_jump ======================

    /**
     * 公式ランキング順位の急変検知（enter: 不在明けの掲載 / jump: 大幅上昇）。
     *
     * データは SQLite ranking_position の日次観測。最新スナップショット日（lastDate）に
     * 観測がある部屋（=現在掲載中）だけを対象にする。
     * 不在判定（enter）は全カテゴリ横断（どのカテゴリにも載っていなかったか）で行い、
     * 順位の評価は category=0（全体）優先・無ければ部屋自身のカテゴリで行う。
     *
     * @param array<int, string[]> $watchersByOc
     * @param array<int, array<string, mixed>> $ocMap
     */
    private function detectRankJumps(array $watchersByOc, array $ocMap): int
    {
        $data = $this->repo->getRecentRankingObservations(array_keys($watchersByOc), self::RANKING_LOOKBACK_DAYS);
        $lastDate = $data['lastDate'];
        if ($lastDate === null || empty($data['rows'])) {
            return 0;
        }

        // open_chat_id => category => 観測行（date DESC 順）
        $byRoom = [];
        foreach ($data['rows'] as $r) {
            $byRoom[$r['open_chat_id']][$r['category']][] = $r;
        }

        $count = 0;
        foreach ($byRoom as $ocId => $byCat) {
            $userIds = $watchersByOc[$ocId] ?? [];
            if (empty($userIds)) {
                continue;
            }

            // 部屋の最新観測日（全カテゴリ横断）。最新スナップショット日に観測が無ければ「現在非掲載」でスキップ
            $roomLatestDate = null;
            foreach ($byCat as $rows) {
                $d = $rows[0]['date'];
                if ($roomLatestDate === null || $d > $roomLatestDate) {
                    $roomLatestDate = $d;
                }
            }
            if ($roomLatestDate !== $lastDate) {
                continue;
            }

            // 評価カテゴリ: 最新日に観測がある中で category=0（全体）優先、無ければ部屋自身のカテゴリ、それも無ければ最小
            $evalCat = $this->chooseEvalCategory($byCat, $roomLatestDate, $ocMap[$ocId] ?? null);
            if ($evalCat === null) {
                continue;
            }
            $rows = $byCat[$evalCat];
            $latest = $rows[0];
            $prev = $rows[1] ?? null;

            // 不在判定（enter）: 全カテゴリ横断で「最新日より前の直近観測日」を求める
            $roomPrevDate = null;
            foreach ($byCat as $catRows) {
                foreach ($catRows as $r) {
                    if ($r['date'] < $roomLatestDate && ($roomPrevDate === null || $r['date'] > $roomPrevDate)) {
                        $roomPrevDate = $r['date'];
                    }
                }
            }

            $kind = null;
            if ($roomPrevDate === null || $this->daysBetween($roomPrevDate, $roomLatestDate) >= self::RANK_ENTER_ABSENT_MIN_DAYS) {
                // 窓内に前回観測なし=初掲載（または長期不在明け） / 2日以上空き=24時間以上不在→掲載再開
                $kind = 'enter';
            } elseif ($prev !== null && $this->daysBetween($prev['date'], $latest['date']) <= self::RANK_JUMP_MAX_GAP_DAYS) {
                $delta = $prev['position'] - $latest['position']; // 正 = 上昇（順位が小さくなった）
                if (
                    $delta >= self::RANK_JUMP_MIN_DELTA
                    && $prev['position'] > 0
                    && ($delta / $prev['position']) >= self::RANK_JUMP_MIN_RATIO
                ) {
                    $kind = 'jump';
                }
            }
            if ($kind === null) {
                continue;
            }

            $payload = [
                'openChatId' => $ocId,
                'name' => (string)($ocMap[$ocId]['name'] ?? ''),
                'category' => $evalCat,
                'position' => $latest['position'],
                'prevPosition' => $prev !== null ? $prev['position'] : null,
                'kind' => $kind,
            ];
            $dedup = 'rj:' . $ocId . ':' . $roomLatestDate; // 日1回まで（enter/jump 合わせて）

            foreach ($userIds as $userId) {
                if ($this->repo->insertNotification($userId, 'rank_jump', $payload, $dedup)) {
                    $this->notifiedUserIds[$userId] = true;
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * rank_jump の評価カテゴリを選ぶ。
     * 最新日（$latestDate）に観測があるカテゴリのうち、0（全体）→ 部屋自身のカテゴリ → 最小ID の優先順。
     *
     * @param array<int, array<int, array{date:string}>> $byCat category => rows(date DESC)
     */
    private function chooseEvalCategory(array $byCat, string $latestDate, ?array $oc): ?int
    {
        $fresh = [];
        foreach ($byCat as $cat => $rows) {
            if ($rows[0]['date'] === $latestDate) {
                $fresh[] = (int)$cat;
            }
        }
        if (empty($fresh)) {
            return null;
        }
        if (in_array(0, $fresh, true)) {
            return 0;
        }
        $ownCat = isset($oc['category']) && $oc['category'] !== null ? (int)$oc['category'] : null;
        if ($ownCat !== null && in_array($ownCat, $fresh, true)) {
            return $ownCat;
        }
        return min($fresh);
    }

    /** 'Y-m-d' 同士の日数差（$a < $b 前提で正の値）。 */
    private function daysBetween(string $a, string $b): int
    {
        $ta = strtotime($a);
        $tb = strtotime($b);
        if ($ta === false || $tb === false) {
            return 0;
        }
        return (int)round(($tb - $ta) / 86400);
    }

    // ====================== 3. pace ======================

    /**
     * ペース異常検知。AlphaInsightsService::appendPaceAnomaly と同じ式:
     *   recentPace = (curr - m7) / 7 が basePace = (curr - m90) / 90 の PACE_ANOMALY_RATIO 倍以上
     *   かつ diff7 >= PACE_ANOMALY_MIN_DIFF7。
     * メトリクスは OcNarrativeRepository::getMemberMetrics（SQLite statistics）経由。
     *
     * @param array<int, string[]> $watchersByOc
     * @param array<int, array<string, mixed>> $ocMap
     */
    private function detectPaceAnomalies(array $watchersByOc, array $ocMap, string $date): int
    {
        $count = 0;
        $baseDate = $this->resolveBaseDate();
        foreach ($watchersByOc as $ocId => $userIds) {
            try {
                $m = $this->narrativeRepository->getMemberMetrics($ocId, $baseDate);
            } catch (\Throwable $e) {
                continue; // データ不足は黙る（InsightsService と同じ安全策）
            }

            $curr = $m['curr'] ?? null;
            $m7 = $m['m7'] ?? null;
            $m90 = $m['m90'] ?? null;
            if ($curr === null || $m7 === null || $m90 === null) {
                continue;
            }
            $diff7 = (int)$curr - (int)$m7;
            $diff90 = (int)$curr - (int)$m90;
            if ($diff7 < self::PACE_ANOMALY_MIN_DIFF7 || $diff90 <= 0) {
                continue;
            }

            $recentPace = $diff7 / 7.0;
            $basePace = $diff90 / 90.0;
            if ($basePace <= 0 || ($recentPace / $basePace) < self::PACE_ANOMALY_RATIO) {
                continue;
            }

            $payload = [
                'openChatId' => $ocId,
                'name' => (string)($ocMap[$ocId]['name'] ?? ''),
                'diff7' => $diff7,
                'recentPace' => round($recentPace, 2),
                'basePace' => round($basePace, 2),
            ];
            $dedup = 'pace:' . $ocId . ':' . $date; // 日1回

            foreach ($userIds as $userId) {
                if ($this->repo->insertNotification($userId, 'pace', $payload, $dedup)) {
                    $this->notifiedUserIds[$userId] = true;
                    $count++;
                }
            }
        }

        return $count;
    }
}
