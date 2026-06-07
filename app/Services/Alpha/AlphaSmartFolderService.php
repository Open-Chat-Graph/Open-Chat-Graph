<?php

declare(strict_types=1);

namespace App\Services\Alpha;

use App\Models\ApiRepositories\Alpha\AlphaAlertRepository;
use App\Models\ApiRepositories\Alpha\AlphaSmartFolderRepository;

/**
 * スマートフォルダ（ルールによる自動追加＋フォルダ単位アラート）のサービス。
 *
 * 1. 初回フィル（initialFill）… folder-settings PUT でルールが新規有効化 / keyword・category
 *    変更されたときに呼ばれる。現在の一致部屋・人数上位 INITIAL_FILL_LIMIT 件を
 *    source='auto' で即時追加し、seen に記録、type='folder_add' 通知を入れる。
 * 2. 毎時の自動追加（autoAddNewRooms）… AlphaAlertService::run() の⑤から呼ばれる。
 *    「rule_created_at 以降にDB収録（open_chat.created_at）された新着の一致部屋」を自動追加。
 *    再追加防止は alpha_folder_seen_ja（恒久）。seen にある部屋＝過去に自動追加済み
 *    （ユーザーが消した可能性がある）部屋は二度と追加しない。
 *    既にユーザーのマイリストにある部屋は INSERT IGNORE（PK user×oc）で動かさない。
 * 3. フォルダ変動（computeFolderMovements）… alpha_folder_threshold_ja の有効行について、
 *    フォルダ＋子孫フォルダ（サーバ側でフォルダ木を再帰解決）配下のアイテムを
 *    statistics_ranking_hour の毎時差分で判定し type='folder' 通知を入れる。
 *    判定は AlphaAlertService::evaluateThreshold と共用（部屋/マイリストと同セマンティクス）。
 *
 * 通知:
 *   - folder_add … dedup 'fa:{folderId}:{ocId}'（恒久一意＝同部屋×同フォルダは一度だけ）
 *   - folder     … dedup 'fm:{folderId}:{ocId}:{direction}:{hourBucket}'。
 *     payload は mylist movement と同形＋folderId/folderName。
 *     優先順位 room > folder > mylist: 同毎時に同(user, 部屋)で room 通知済みならスキップ。
 *     folder 同士（同部屋が複数フォルダ）は両方通知してよい（フォルダごとに意味が違うため）。
 */
class AlphaSmartFolderService
{
    /** 初回フィルで追加する一致部屋の上限（人数上位） */
    private const INITIAL_FILL_LIMIT = 50;

    /** 毎時の自動追加で1フォルダ（ルール）あたり1回に追加する上限（残りは次の毎時で追加される） */
    private const HOURLY_ADD_LIMIT = 50;

    /** 毎時のグループ検索（同一 keyword+category のルール集合）で先読みする部屋数 */
    private const HOURLY_FETCH_LIMIT = 200;

    /** @var array<string, true> この実行で新規通知を入れたユーザーID集合 */
    private array $notifiedUserIds = [];

    public function __construct(
        private AlphaSmartFolderRepository $repo,
        private AlphaAlertRepository $alertRepo,
    ) {
    }

    // ====================== 初回フィル（folder-settings PUT から） ======================

    /**
     * ルール設定時の初回フィル。現在の一致部屋・人数上位 INITIAL_FILL_LIMIT 件を即時追加する。
     * 既にフォルダ/seen にある部屋・マイリストの他の場所にある部屋は追加しない（カウントもしない）。
     * 部屋ごとの個別通知はせず、追加件数をまとめた**サマリ通知1通**を発行する。
     *
     * @param string $ruleCreatedAt ルールの rule_created_at（'Y-m-d H:i:s'）。サマリ通知の dedup キーに使う。
     * @return int 実際に追加した件数（autoAdded）
     */
    public function initialFill(string $userId, string $folderId, string $folderName, string $keyword, ?int $category, string $ruleCreatedAt): int
    {
        $rooms = $this->repo->findMatchingRooms($keyword, $category, self::INITIAL_FILL_LIMIT);
        return $this->addRoomsToFolder($userId, $folderId, $folderName, $rooms, markNotified: false, summaryMode: true, ruleCreatedAt: $ruleCreatedAt);
    }

    // ====================== 毎時処理（AlphaAlertService::run() の⑤） ======================

    /**
     * 毎時のスマートフォルダ処理本体（自動追加＋フォルダ変動）。
     *
     * @param string $hourBucket dedup 用の時刻 bucket（'Y-m-d-H'）
     * @return array{folderAdds:int, folderMovements:int, notifiedUserIds:string[], errors:array<int,string>}
     */
    public function run(string $hourBucket): array
    {
        $this->notifiedUserIds = [];
        $errors = [];
        $adds = 0;
        $movements = 0;

        try {
            $adds = $this->autoAddNewRooms();
        } catch (\Throwable $e) {
            $errors[] = 'folder_add: ' . $e->getMessage();
        }

        try {
            $movements = $this->computeFolderMovements($hourBucket);
        } catch (\Throwable $e) {
            $errors[] = 'folder: ' . $e->getMessage();
        }

        return [
            'folderAdds' => $adds,
            'folderMovements' => $movements,
            'notifiedUserIds' => array_keys($this->notifiedUserIds),
            'errors' => $errors,
        ];
    }

    /**
     * 「ルール作成（rule_created_at）以降にDB収録された新着の一致部屋」を各フォルダへ自動追加する。
     * 同一 keyword+category のルールはグループ化し、open_chat への LIKE 検索を1回にまとめる
     * （グループ内の最古 rule_created_at で取得し、ルールごとに created_at >= rule_created_at で絞る）。
     */
    private function autoAddNewRooms(): int
    {
        $rules = $this->repo->getAllEnabledRules();
        if (empty($rules)) {
            return 0;
        }

        // keyword+category でグループ化（LIKE 検索の重複実行を防ぐ）
        $groups = [];
        foreach ($rules as $rule) {
            $key = $rule['keyword'] . "\x00" . ($rule['category'] === null ? 'null' : (string)$rule['category']);
            $groups[$key]['keyword'] = $rule['keyword'];
            $groups[$key]['category'] = $rule['category'];
            $groups[$key]['minCreated'] = isset($groups[$key]['minCreated'])
                ? min($groups[$key]['minCreated'], $rule['rule_created_at'])
                : $rule['rule_created_at'];
            $groups[$key]['rules'][] = $rule;
        }

        $count = 0;
        foreach ($groups as $g) {
            $rooms = $this->repo->findNewMatchingRooms(
                $g['keyword'],
                $g['category'],
                $g['minCreated'],
                self::HOURLY_FETCH_LIMIT
            );
            if (empty($rooms)) {
                continue;
            }

            foreach ($g['rules'] as $rule) {
                // ルールごとの新着判定: DB収録日（open_chat.created_at）>= rule_created_at
                $targets = array_values(array_filter(
                    $rooms,
                    static fn($room) => $room['created_at'] !== null && $room['created_at'] >= $rule['rule_created_at']
                ));
                if (empty($targets)) {
                    continue;
                }
                $targets = array_slice($targets, 0, self::HOURLY_ADD_LIMIT);

                $added = $this->addRoomsToFolder(
                    $rule['user_id'],
                    $rule['folder_id'],
                    $rule['folder_name'],
                    $targets,
                    markNotified: true
                );
                $count += $added;
            }
        }

        return $count;
    }

    /**
     * 部屋集合をフォルダへ source='auto' で追加する共通処理。
     *   - seen 済みはスキップ（二度と自動追加しない）
     *   - 追加時は必ず seen に記録（ユーザーが消しても戻さないための恒久記録）
     *   - 実際に INSERT できた部屋（マイリスト未所持）だけ通知・カウント
     *
     * @param array<int, array{id:int, name:string, member:int, created_at:?string}> $rooms
     * @param bool $markNotified true なら通知ユーザーを notifiedUserIds（Push tickle 対象）へ合流
     * @param bool $summaryMode  true なら部屋ごとの個別通知をやめ、追加完了後にサマリ通知1通を発行する（初回フィル用）
     * @param string $ruleCreatedAt サマリ通知の dedup キー用タイムスタンプ（summaryMode=true 時に使用）
     */
    private function addRoomsToFolder(
        string $userId,
        string $folderId,
        string $folderName,
        array $rooms,
        bool $markNotified = false,
        bool $summaryMode = false,
        string $ruleCreatedAt = '',
    ): int {
        if (empty($rooms)) {
            return 0;
        }

        $seen = $this->repo->getSeenOcIds($userId, $folderId);
        $nextOrder = $this->repo->getNextSortOrder($userId, $folderId);

        $count = 0;
        /** @var string[] $sampleNames サマリ用：先頭3部屋の名前 */
        $sampleNames = [];

        foreach ($rooms as $room) {
            $ocId = $room['id'];
            if (isset($seen[$ocId])) {
                continue; // 過去に自動追加済み（ユーザーが消したら戻さない）
            }

            $inserted = $this->repo->insertAutoItem($userId, $folderId, $ocId, $nextOrder);
            $this->repo->markSeen($userId, $folderId, $ocId);
            if (!$inserted) {
                continue; // 既にマイリストのどこかにある部屋（動かさない・通知しない）
            }
            $nextOrder++;

            if ($summaryMode) {
                // サマリモード: 個別通知は発行しない。名前だけ収集して後でまとめる
                if (count($sampleNames) < 3) {
                    $sampleNames[] = $room['name'];
                }
                $count++;
                continue;
            }

            // 通常モード（毎時の新着追加）: 部屋ごとに個別通知
            $payload = [
                'folderId' => $folderId,
                'folderName' => $folderName,
                'openChatId' => $ocId,
                'name' => $room['name'],
                'member' => $room['member'],
            ];
            $dedup = 'fa:' . $folderId . ':' . $ocId; // 恒久一意（同部屋×同フォルダは一度だけ）
            if ($this->alertRepo->insertNotification($userId, 'folder_add', $payload, $dedup) && $markNotified) {
                $this->notifiedUserIds[$userId] = true;
            }
            $count++;
        }

        // サマリモード: 1通のまとめ通知を発行（追加0件ならスキップ）
        if ($summaryMode && $count > 0) {
            // dedup キー: rule_created_at の unix 秒を使う（同じルール設定では一度だけ）。
            // 空の場合は現在時刻にフォールバック（念のため）。
            $ruleCreatedTs = $ruleCreatedAt !== '' ? strtotime($ruleCreatedAt) : time();
            $dedup = 'fa:' . $folderId . ':backfill:' . $ruleCreatedTs;
            $payload = [
                'folderId' => $folderId,
                'folderName' => $folderName,
                'count' => $count,
                'sampleNames' => $sampleNames,
            ];
            $this->alertRepo->insertNotification($userId, 'folder_add', $payload, $dedup);
        }

        return $count;
    }

    // ====================== フォルダ変動（しきい値判定） ======================

    /**
     * フォルダ単位の変動アラート。フォルダ＋子孫フォルダ配下のアイテムを
     * statistics_ranking_hour の毎時差分で判定する。
     * 優先順位: 同毎時に同(user, 部屋)で room 通知済みならスキップ（room > folder）。
     */
    private function computeFolderMovements(string $hourBucket): int
    {
        $thresholds = $this->repo->getAllEnabledThresholds();
        if (empty($thresholds)) {
            return 0;
        }

        // 各しきい値の対象 open_chat_id（フォルダ＋子孫）を解決し、全体をまとめて差分取得
        $folderParentsByUser = []; // user_id => (folder_id => parent_id) のキャッシュ
        $targets = []; // index => int[] ocIds
        $allIds = [];
        foreach ($thresholds as $i => $t) {
            $userId = $t['user_id'];
            if (!isset($folderParentsByUser[$userId])) {
                $folderParentsByUser[$userId] = $this->repo->getUserFolderParents($userId);
            }
            $folderIds = $this->resolveDescendantFolderIds($folderParentsByUser[$userId], $t['folder_id']);
            $ocIds = $this->repo->getItemOcIdsInFolders($userId, $folderIds);
            $targets[$i] = $ocIds;
            foreach ($ocIds as $id) {
                $allIds[$id] = true;
            }
        }
        if (empty($allIds)) {
            return 0;
        }

        $allIds = array_keys($allIds);
        $diffMap = $this->alertRepo->getHourlyDiffMap($allIds);
        $ocMap = $this->alertRepo->getOpenChatMap($allIds);

        // 優先順位 room > folder: この毎時に room 通知済みの (user, oc) はスキップ
        $roomNotified = [];
        foreach ($this->alertRepo->getRoomNotificationKeys($hourBucket) as $r) {
            $roomNotified[$r['user_id'] . ':' . $r['open_chat_id']] = true;
        }

        $count = 0;
        foreach ($thresholds as $i => $t) {
            $userId = $t['user_id'];
            foreach ($targets[$i] as $ocId) {
                if (!isset($diffMap[$ocId])) {
                    continue; // この毎時で変動なし / ランキング外
                }
                if (isset($roomNotified[$userId . ':' . $ocId])) {
                    continue; // 部屋単体アラートが優先（重複排除）
                }
                $diff = $diffMap[$ocId]['diff_member'];
                $percent = $diffMap[$ocId]['percent_increase'];

                $direction = AlphaAlertService::evaluateThreshold([
                    'up_member' => $t['up_member'],
                    'up_percent' => $t['up_percent'],
                    'down_member' => $t['down_member'],
                    'down_percent' => $t['down_percent'],
                ], $diff, $percent);
                if ($direction === null) {
                    continue;
                }

                // mylist movement と同形＋folderId/folderName（フロント契約）
                $payload = AlphaAlertService::buildMovementPayload('folder', $ocId, $ocMap[$ocId] ?? null, $diff, $percent, $direction)
                    + ['folderId' => $t['folder_id'], 'folderName' => $t['folder_name']];
                $dedup = 'fm:' . $t['folder_id'] . ':' . $ocId . ':' . $direction . ':' . $hourBucket;
                if ($this->alertRepo->insertNotification($userId, 'folder', $payload, $dedup)) {
                    $this->notifiedUserIds[$userId] = true;
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * フォルダ木（folder_id => parent_id）から、指定フォルダ自身＋全子孫の folder_id を返す。
     * サイクル（parent_id の循環）があっても無限ループしない（訪問済み集合で打ち切り）。
     *
     * @param array<string, ?string> $parents folder_id => parent_id
     * @return string[]
     */
    private function resolveDescendantFolderIds(array $parents, string $rootFolderId): array
    {
        // 子リストへ反転
        $children = [];
        foreach ($parents as $folderId => $parentId) {
            if ($parentId !== null) {
                $children[$parentId][] = $folderId;
            }
        }

        $result = [];
        $queue = [$rootFolderId];
        $visited = [];
        while (!empty($queue)) {
            $current = array_shift($queue);
            if (isset($visited[$current])) {
                continue;
            }
            $visited[$current] = true;
            $result[] = $current;
            foreach ($children[$current] ?? [] as $child) {
                $queue[] = $child;
            }
        }
        return $result;
    }
}
