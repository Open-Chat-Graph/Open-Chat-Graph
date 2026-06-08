<?php

declare(strict_types=1);

namespace App\Services\Alpha;

use App\Models\ApiRepositories\Alpha\AlphaAlertRepository;
use App\Models\ApiRepositories\Alpha\AlphaPushRepository;

/**
 * Alpha 通知/アラートの算出サービス（毎時 cron から呼ばれる）。
 *
 * 全ユーザーのウォッチを走査し、以下を算出して alpha_notification_ja に保存する:
 *   ① ウォッチキーワード … LINE公式検索APIで一致する「新着部屋」を検出
 *        - 登録済み(open_chat にある)部屋  … open_chat.created_at（こちらのDBへの登録日時）が
 *          ウォッチ created_at 以降のものだけを「新着」として一度だけ通知（カテゴリ照合あり）。
 *          古い部屋（DB登録 < ウォッチ作成）は seen 記録だけ残してスキップする。
 *          open_chat.created_at が NULL の行は古い部屋とみなしスキップ（安全側）。
 *        - 未登録(オプチャグラフ未登録)部屋 … 共有プール alpha_search_seen_room_ja に貯め、
 *          「ウォッチ登録時刻(created_at)以降に初出（first_seen_at >= ウォッチ作成） ＆ 未配信」のものだけ配信。
 *          両経路とも「ウォッチ登録より前から在った部屋を初回に大量配信しない」点で対称。
 *   ② ウォッチ部屋        … 指定人数±/%± の上昇・下降を検出
 *   ③ マイリスト          … %しきい値での上昇・下降を検出
 *   ④ 機微シグナル        … ウォッチ部屋の room_change / rank_jump / pace（AlphaSignalDetectionService に委譲）
 *   ⑤ スマートフォルダ    … ルール一致の新着部屋を自動追加(folder_add)＋フォルダ単位の変動(folder)
 *                            （AlphaSmartFolderService に委譲。② room の後・③ mylist の前に実行する）
 *
 * 通知の優先順位は room > folder > mylist。同毎時に同(user, 部屋)で上位 type が
 * 通知済みなら下位はスキップする（folder は room を、mylist は room と folder を見る。
 * folder 同士＝同部屋が複数フォルダで動いた場合は両方通知してよい）。
 *
 * 負荷対策:
 *   - LINE公式APIは「登録キーワード(＋カテゴリ)のユニーク集合」に対してのみ叩く（ユーザー数に依存しない）。
 *   - ②③の差分は既存の statistics_ranking_hour（毎時クロールで確定済み）をまとめて1クエリで取得。
 *
 * 重複防止:
 *   - ① emid は alpha_keyword_seen_ja(keyword_watch_id, emid) に記録、2回目以降は通知しない。
 *   - ②③ は alpha_notification_ja.dedup_key（時刻 bucket 込み）で同一毎時の重複保存を防ぐ。
 *   - ④ は dedup_key（room_change=変更内容hash+毎時 / rank_jump・pace=日1回）で防ぐ。
 *
 * 毎時処理を止めないため、各ステップは try/catch で握りつぶしログのみ（呼び出し側で集約）。
 *
 * @return array{computedAt:string, keywordHits:int, movements:int, signals:int, folderAdds:int, notifiedUserIds:string[], errors:array<int,string>}
 */
class AlphaAlertService
{
    /** LINE検索APIに渡す1キーワードあたりの取得件数 */
    private const SEARCH_LIMIT = 20;

    /**
     * この実行で「新規に通知をINSERTした」ユーザーID集合（dedupで弾かれたものは含まない）。
     * Web Push tickle（AlphaPushService::notifyUsers）の対象抽出に使う。
     *
     * @var array<string, true>
     */
    private array $notifiedUserIds = [];

    public function __construct(
        private AlphaAlertRepository $repo,
        private AlphaKeywordSearchClient $searchClient,
        private AlphaSignalDetectionService $signalDetection,
        private AlphaSmartFolderService $smartFolder,
        private AlphaPushRepository $pushRepo,
    ) {
    }

    /**
     * 毎時 cron 本体。
     *
     * @return array{computedAt:string, keywordHits:int, movements:int, signals:int, folderAdds:int, notifiedUserIds:string[], errors:array<int,string>}
     */
    public function run(): array
    {
        $computedAt = date('Y-m-d H:i:s');
        $hourBucket = date('Y-m-d-H'); // dedup 用の時刻 bucket（同一毎時の重複保存を防ぐ）
        $errors = [];
        $keywordHits = 0;
        $movements = 0;
        $signals = 0;
        $folderAdds = 0;
        $this->notifiedUserIds = [];

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

        // ⑤ スマートフォルダ（自動追加 folder_add ＋ フォルダ変動 folder）。
        //    優先順位 room > folder > mylist のため、② room の後・③ mylist の前に実行する
        //    （folder は room 通知済みをスキップし、mylist は room と folder の通知済みをスキップ）。
        try {
            $sf = $this->smartFolder->run($hourBucket);
            $folderAdds = $sf['folderAdds'];
            $movements += $sf['folderMovements'];
            foreach ($sf['notifiedUserIds'] as $uid) {
                $this->notifiedUserIds[$uid] = true;
            }
            $errors = array_merge($errors, $sf['errors']);
        } catch (\Throwable $e) {
            $errors[] = 'smart_folder: ' . $e->getMessage();
        }

        try {
            $movements += $this->computeMylistMovements($hourBucket);
        } catch (\Throwable $e) {
            $errors[] = 'mylist: ' . $e->getMessage();
        }

        // ④ ウォッチ部屋の機微検知（room_change / rank_jump / pace）。
        //    通知ユーザーは既存の notifiedUserIds 機構に合流し Web Push tickle の対象になる。
        try {
            $sig = $this->signalDetection->detect($hourBucket);
            $signals = $sig['count'];
            foreach ($sig['notifiedUserIds'] as $uid) {
                $this->notifiedUserIds[$uid] = true;
            }
            $errors = array_merge($errors, $sig['errors']);
        } catch (\Throwable $e) {
            $errors[] = 'signal: ' . $e->getMessage();
        }

        return [
            'computedAt' => $computedAt,
            'keywordHits' => $keywordHits,
            'movements' => $movements,
            'signals' => $signals,
            'folderAdds' => $folderAdds,
            'notifiedUserIds' => array_keys($this->notifiedUserIds),
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

        // ① 検索結果を走査し、未登録 emid を共有プール(alpha_search_seen_room_ja)へ upsert する。
        //    各検索結果はそれを見つけたキーワードで keywords 集合に足される（ユーザー横断・1行/部屋）。
        $this->syncSeenRooms($searchCache);

        // ② 各ユーザーのウォッチについて、登録済み一致（即時）と未登録一致（プール経由）を配信する。
        $count = 0;
        foreach ($watches as $w) {
            $squares = $searchCache[$w['keyword']] ?? [];

            // 登録済み一致: open_chat.created_at >= ウォッチ作成 の部屋のみ通知（新着性フィルタ）。
            // 古い部屋（DB登録 < ウォッチ作成 または created_at NULL）は seen 記録してスキップ。
            $count += $this->deliverRegisteredHits($w, $squares);

            // 未登録一致は共有プールから「登録時刻以降に初出 ＆ 未配信」のみ配信
            $count += $this->deliverUnregisteredHits($w);
        }

        return $count;
    }

    /**
     * 検索結果の未登録 emid を共有プール(alpha_search_seen_room_ja)へ upsert する。
     * 同一 emid が複数キーワードでヒットしても、各キーワードを keywords 集合に足す。
     *
     * @param array<string, array<int, array{emid:string,name:string,desc:string,profileImageObsHash:string,joinMethodType:int}>> $searchCache
     */
    private function syncSeenRooms(array $searchCache): void
    {
        foreach ($searchCache as $keyword => $squares) {
            if (empty($squares)) {
                continue;
            }
            $emids = array_map(static fn($s) => $s['emid'], $squares);
            $registered = $this->repo->getRegisteredEmidMap($emids); // emid => open_chat_id（登録済み）

            foreach ($squares as $sq) {
                $emid = $sq['emid'];
                if ($emid === '' || isset($registered[$emid])) {
                    continue; // 登録済みはプールに入れない（②の登録済み経路で扱う）
                }
                // square には member が無いので null（登録されれば後日 open_chat 側に出る）
                $this->repo->upsertSeenRoom($emid, $sq['name'], null, (string)$keyword);
            }
        }
    }

    /**
     * 登録済み部屋がキーワード一致したとき、新着性フィルタを通過したものだけ通知する。
     *
     * 新着性フィルタ: open_chat.created_at（こちらのDBへの登録日時）>= ウォッチ created_at
     * を満たす部屋だけを通知対象とする。
     *   - 条件を満たさない（古い）部屋は通知せずに seen 記録だけ行い、以後の再走査を防ぐ。
     *   - open_chat.created_at が NULL の行は古い部屋とみなし skip（安全側の設計）。
     * これにより未登録経路（first_seen_at >= ウォッチ作成）と対称な挙動になる。
     *
     * @param array{id:int,user_id:string,keyword:string,category:?int,created_at:string} $w
     * @param array<int, array{emid:string,name:string,desc:string,profileImageObsHash:string,joinMethodType:int}> $squares
     */
    private function deliverRegisteredHits(array $w, array $squares): int
    {
        if (empty($squares)) {
            return 0;
        }

        $emids = array_map(static fn($s) => $s['emid'], $squares);
        $registered = $this->repo->getRegisteredEmidMap($emids); // emid => open_chat_id
        if (empty($registered)) {
            return 0;
        }
        $ocMap = $this->repo->getOpenChatMap(array_values($registered));
        $seen = $this->repo->getSeenEmids($w['id']);

        $count = 0;
        foreach ($squares as $sq) {
            $emid = $sq['emid'];
            $ocId = $registered[$emid] ?? null;
            if ($ocId === null) {
                continue; // 未登録は別経路（プール）で扱う
            }
            if (isset($seen[$emid])) {
                continue; // 既に通知済み（seen記録あり）
            }

            // 新着性フィルタ: open_chat.created_at（DB登録日時）がウォッチ作成より前ならスキップ。
            // created_at が NULL の行も古い部屋とみなしスキップ（安全側）。
            // スキップ時も markEmidSeen して以後の再走査を防ぐ。
            $ocCreatedAt = $ocMap[$ocId]['created_at'] ?? null;
            if ($ocCreatedAt === null || $ocCreatedAt < $w['created_at']) {
                $this->repo->markEmidSeen($w['id'], $emid);
                continue;
            }

            // カテゴリ指定があり、登録部屋のカテゴリが一致しなければスキップ
            if ($w['category'] !== null) {
                $cat = isset($ocMap[$ocId]['category']) ? (int)$ocMap[$ocId]['category'] : null;
                if ($cat !== $w['category']) {
                    continue;
                }
            }

            $payload = [
                'keyword' => $w['keyword'],
                'category' => $w['category'],
                'emid' => $emid,
                'openChatId' => $ocId,
                'name' => $sq['name'],
                'desc' => $sq['desc'],
                'img' => $sq['profileImageObsHash'],
                'joinMethodType' => $sq['joinMethodType'],
                'isRegistered' => true,
                'member' => isset($ocMap[$ocId]['member']) ? (int)$ocMap[$ocId]['member'] : null,
                'detectedAt' => time(), // 登録済みは初出時刻が無いので検出時刻
            ];

            $dedup = 'kw:' . $w['id'] . ':' . $emid;
            $inserted = $this->repo->insertNotification($w['user_id'], 'keyword', $payload, $dedup);
            $this->repo->markEmidSeen($w['id'], $emid);
            if ($inserted) {
                $this->notifiedUserIds[$w['user_id']] = true;
                $count++;
            }
        }

        return $count;
    }

    /**
     * 未登録部屋（共有プール）から、このウォッチに配信すべきものを通知する。
     *
     * 条件: keyword が keywords 集合に完全一致 ＆ first_seen_at >= watch.created_at
     *       ＆ そのユーザー(watch)に未配信(alpha_keyword_seen_ja で重複排除)。
     * これにより、ウォッチ登録より前から存在した部屋が初回に大量配信されるのを防ぐ。
     *
     * @param array{id:int,user_id:string,keyword:string,category:?int,created_at:string} $w
     */
    private function deliverUnregisteredHits(array $w): int
    {
        $rooms = $this->repo->getDeliverableSeenRooms($w['keyword'], $w['created_at']);
        if (empty($rooms)) {
            return 0;
        }

        $seen = $this->repo->getSeenEmids($w['id']);

        $count = 0;
        foreach ($rooms as $room) {
            $emid = $room['emid'];
            if ($emid === '' || isset($seen[$emid])) {
                continue; // 既に配信済み
            }

            $payload = [
                'keyword' => $w['keyword'],
                'category' => $w['category'],
                'emid' => $emid,
                'openChatId' => null, // 未登録
                'name' => $room['name'],
                'desc' => '',
                'img' => '', // square の obsハッシュは保持していないので空（フロントは name 主体で表示）
                'joinMethodType' => 0,
                'isRegistered' => false,
                'member' => $room['member'],
                'detectedAt' => strtotime($room['first_seen_at']) ?: time(),
            ];

            $dedup = 'kw:' . $w['id'] . ':' . $emid;
            $inserted = $this->repo->insertNotification($w['user_id'], 'keyword', $payload, $dedup);
            $this->repo->markEmidSeen($w['id'], $emid);
            if ($inserted) {
                $this->notifiedUserIds[$w['user_id']] = true;
                $count++;
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

            $direction = self::evaluateThreshold($w, $diff, $percent);
            if ($direction === null) {
                continue;
            }

            $payload = self::buildMovementPayload('room', $ocId, $ocMap[$ocId] ?? null, $diff, $percent, $direction);
            $dedup = 'room:' . $ocId . ':' . $direction . ':' . $hourBucket;
            if ($this->repo->insertNotification($w['user_id'], 'room', $payload, $dedup)) {
                $this->notifiedUserIds[$w['user_id']] = true;
                $count++;
            }
        }

        return $count;
    }

    /**
     * しきい値判定（部屋ウォッチ / マイリスト / スマートフォルダで共用）。
     * 上昇: (up_member 指定なら diff>=up_member) かつ/または (up_percent 指定なら percent>=up_percent)
     *   → 指定された条件「すべて」を満たしたら上昇扱い（両方指定なら AND）。
     * 下降も同様（符号反転、絶対値比較）。
     *
     * @param array{up_member:?int, up_percent:?float, down_member:?int, down_percent:?float} $w
     * @return 'up'|'down'|null
     */
    public static function evaluateThreshold(array $w, int $diff, float $percent): ?string
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

        // 全対象ユーザーのマイリスト id を集約してまとめて差分取得。
        // scope='all' / target_oc_ids=null は従来どおり oc_list_user（全体）にフォールバック。
        // scope='root'/'folder' はフロントが解決済みの target_oc_ids をそのまま使う。
        $userIdsToOcIds = [];
        $allIds = [];
        foreach ($thresholds as $t) {
            $ocIds = ($t['target_oc_ids'] !== null)
                ? $t['target_oc_ids']
                : $this->repo->getMylistOpenChatIds($t['user_id']);
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

        // 二重通知の回避（優先順位 room > folder > mylist）: この毎時に部屋単体(room) または
        // スマートフォルダ変動(folder)で通知済みの (user_id, open_chat_id) はマイリスト側ではスキップする
        // （同一部屋が複数フォルダで動いた場合も mylist 全体はスキップ）。
        $roomNotified = $this->collectPriorityNotifiedKeys($hourBucket);

        $count = 0;
        foreach ($userIdsToOcIds as $userId => $ocIds) {
            $t = $thresholdByUser[$userId];
            foreach ($ocIds as $ocId) {
                if (!isset($diffMap[$ocId])) {
                    continue;
                }
                // 部屋単体アラートで既に通知済みなら、マイリスト側は出さない（重複排除）。
                if (isset($roomNotified[$userId . ':' . $ocId])) {
                    continue;
                }
                $diff = $diffMap[$ocId]['diff_member'];
                $percent = $diffMap[$ocId]['percent_increase'];

                // 部屋ウォッチと同じ判定器を共用。%／人数のどちらの指定でも発火する。
                $direction = self::evaluateThreshold([
                    'up_member' => $t['up_member'] ?? null,
                    'up_percent' => $t['up_percent'] ?? null,
                    'down_member' => $t['down_member'] ?? null,
                    'down_percent' => $t['down_percent'] ?? null,
                ], $diff, $percent);
                if ($direction === null) {
                    continue;
                }

                $payload = self::buildMovementPayload('mylist', $ocId, $ocMap[$ocId] ?? null, $diff, $percent, $direction);
                $dedup = 'mylist:' . $ocId . ':' . $direction . ':' . $hourBucket;
                if ($this->repo->insertNotification($userId, 'mylist', $payload, $dedup)) {
                    $this->notifiedUserIds[(string)$userId] = true;
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * この毎時に部屋単体アラート(type='room')またはフォルダ変動(type='folder')で通知を出した
     * (user_id, open_chat_id) の集合を返す。実行順が room → folder → mylist なので、ここで拾える。
     * 優先順位 room > folder > mylist の重複排除（mylist は上位2種の通知済みをスキップ）のため。
     *
     * @return array<string, true> "user_id:open_chat_id" => true
     */
    private function collectPriorityNotifiedKeys(string $hourBucket): array
    {
        $set = [];
        foreach ($this->repo->getRoomNotificationKeys($hourBucket) as $r) {
            $set[$r['user_id'] . ':' . $r['open_chat_id']] = true;
        }
        foreach ($this->repo->getFolderNotificationKeys($hourBucket) as $r) {
            $set[$r['user_id'] . ':' . $r['open_chat_id']] = true;
        }
        return $set;
    }

    // ====================== 日次掃除 ======================

    /**
     * 日次掃除: 凍結sweep + 孤児 watch 削除。
     *
     * 1. 3日以上連続失敗の購読を frozen=1 に凍結（AlphaPushRepository::freezeStaleSubscriptions）。
     * 2. 購読が1行も無いユーザーの keyword_watch と関連 seen 行を削除（AlphaAlertRepository::deleteOrphanKeywordWatches）。
     *
     * @return array{frozen:int, deletedWatches:int}
     */
    public function runDailyCleanup(): array
    {
        $frozen = $this->pushRepo->freezeStaleSubscriptions();
        $deletedWatches = $this->repo->deleteOrphanKeywordWatches();
        return ['frozen' => $frozen, 'deletedWatches' => $deletedWatches];
    }

    // ====================== 共通: movement payload ======================

    /**
     * 変動通知の payload（room / mylist / folder で同形。folder は呼び出し側で
     * folderId/folderName を追加する）。
     */
    public static function buildMovementPayload(
        string $kind,
        int $ocId,
        ?array $oc,
        int $diff,
        float $percent,
        string $direction
    ): array {
        return [
            'kind' => $kind, // 'room' | 'mylist' | 'folder'
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
