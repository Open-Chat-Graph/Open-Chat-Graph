<?php

declare(strict_types=1);

namespace App\Models\ApiRepositories\Alpha;

use App\Models\Repositories\DB;
use App\Models\SQLite\SQLiteStatistics;

/**
 * Alpha「任意のN日増減」専用リポジトリ
 *
 * キーワード（＋カテゴリ）に一致するルームのうち、「N日前と現在のどちらにも
 * 日次統計が存在する」ものに絞り、その期間のメンバー増減でリスト化する。
 *
 * 2段構成:
 *   1. MySQL(open_chat) … キーワード/カテゴリ一致のルームを抽出（候補プール）
 *   2. SQLite(statistics) … 候補idの「N日前時点」「最新時点」のメンバー数を取得
 *      → N日前時点のデータが無いidはフィルタで弾く（N日前には存在しなかった/統計が無い）
 *
 * PHP側でid突き合わせ＆差分計算＆ソートを行う。
 *
 * 負荷対策:
 *   - MySQL候補プールは CANDIDATE_LIMIT 件で打ち切り（member降順 = 規模の大きい順を優先）。
 *     頻出キーワード/大カテゴリでも SQLite 問い合わせを現実的な件数に抑える。
 *   - SQLite の IN 句は SQLITE_IN_CHUNK 件ずつチャンク分割して発行（SQLite の
 *     変数上限 999/expression-tree 上限への安全マージン）。
 */
class AlphaPeriodGrowthRepository
{
    /**
     * MySQLキーワード一致から拾う候補ルームの上限。
     * member降順で上位このN件のみをSQLite突き合わせ対象にする。
     * これを超える一致は「規模の小さい部屋」として割り切って除外する。
     */
    private const CANDIDATE_LIMIT = 3000;

    /**
     * SQLite の IN 句に一度に渡すidの最大数（チャンクサイズ）。
     */
    private const SQLITE_IN_CHUNK = 500;

    /**
     * N日増減リストを取得する
     *
     * @param string   $keyword  検索キーワード（スペース区切りでAND）
     * @param int      $category カテゴリID（0=全カテゴリ）
     * @param int      $days     N（日数）
     * @param string   $order    'desc'（増加多い順）/ 'asc'（少ない順）
     * @param int      $limit    返却件数の上限（1ページ分）
     * @param int      $offset   先頭から読み飛ばす件数（ページング用）
     * @return array{data: array<int, array<string, mixed>>, days: int, totalMatched: int, baseDate: ?string, pastDate: ?string, hasMore: bool}
     */
    public function findPeriodGrowth(
        string $keyword,
        int $category,
        int $days,
        string $order,
        int $limit,
        int $offset = 0
    ): array {
        // 1. MySQL: キーワード(＋カテゴリ)一致のルームを候補として取得（member降順上限付き）
        $candidates = $this->fetchCandidates($keyword, $category);

        if (empty($candidates)) {
            return ['data' => [], 'days' => $days, 'totalMatched' => 0, 'baseDate' => null, 'pastDate' => null, 'hasMore' => false];
        }

        $ids = array_map(static fn($c) => (int)$c['id'], $candidates);

        // 2. SQLite: 基準日（最新日）とN日前の対象日を解決
        $pdo = SQLiteStatistics::connect(['mode' => '?mode=ro']);

        $baseDate = $this->resolveBaseDate($pdo);
        if ($baseDate === null) {
            // 統計がまだ無い
            return ['data' => [], 'days' => $days, 'totalMatched' => 0, 'baseDate' => null, 'pastDate' => null, 'hasMore' => false];
        }
        // N日前の「狙う」日付。実データはこの日付以下で最も近い日を採用する。
        $targetPastDate = (new \DateTime($baseDate))->modify("-{$days} day")->format('Y-m-d');

        // 各候補idについて「基準日のメンバー数」と「N日前(以下で最も近い日)のメンバー数」を取得
        $currentMap = $this->fetchMemberAtOrBeforeDate($pdo, $ids, $baseDate);
        $pastMap    = $this->fetchMemberAtOrBeforeDate($pdo, $ids, $targetPastDate);

        // 3 & 4. 突き合わせ・フィルタ・差分計算・ソート（order=desc:増加多い順／asc:少ない順）
        $rows = $this->buildRows($candidates, $currentMap, $pastMap, $order);

        $totalMatched = count($rows);

        // 5. ページング（limit+1 で hasMore を判定し、表示は limit 件に切る）
        [$paged, $hasMore] = $this->paginate($rows, $offset, $limit);

        return [
            'data'         => $paged,
            'days'         => $days,
            'totalMatched' => $totalMatched,
            'baseDate'     => $baseDate,
            'pastDate'     => $targetPastDate,
            'hasMore'      => $hasMore,
        ];
    }

    /**
     * ソート済み全行を offset/limit で切り出し、次ページ有無(hasMore)を返す。
     * limit+1 件を読む方式ではなく既に全件 PHP 側に持っているので、offset 以降の
     * 残件数で hasMore を判定する（access-ranking の limit+1 と意味は同じ）。
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array{0: array<int, array<string, mixed>>, 1: bool}
     */
    private function paginate(array $rows, int $offset, int $limit): array
    {
        $offset = max(0, $offset);
        $paged = array_slice($rows, $offset, $limit);
        $hasMore = (count($rows) > $offset + $limit);
        return [$paged, $hasMore];
    }

    /**
     * 期間（開始日〜終了日）指定の増減リストを取得する。
     *
     * findPeriodGrowth が「基準日とそのN日前」で比較するのに対し、こちらは
     * 明示した startDate / endDate で比較する（フロントの日付ピッカー用）。
     * 各idについて「endDate 以下で最も近い日」と「startDate 以下で最も近い日」の
     * メンバー数を取り、両方そろうものだけを残す。
     *
     * @param string $startDate 期間開始日 (Y-m-d)
     * @param string $endDate   期間終了日 (Y-m-d)
     * @param int    $offset    先頭から読み飛ばす件数（ページング用）
     * @return array{data: array<int, array<string, mixed>>, days: int, totalMatched: int, baseDate: ?string, pastDate: ?string, hasMore: bool}
     */
    public function findPeriodGrowthByDateRange(
        string $keyword,
        int $category,
        string $startDate,
        string $endDate,
        string $order,
        int $limit,
        int $offset = 0
    ): array {
        // 終了日 < 開始日なら入れ替えて常に start <= end にする
        if ($startDate > $endDate) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }
        $days = (int)((new \DateTime($startDate))->diff(new \DateTime($endDate))->days);

        $candidates = $this->fetchCandidates($keyword, $category);
        if (empty($candidates)) {
            return ['data' => [], 'days' => $days, 'totalMatched' => 0, 'baseDate' => null, 'pastDate' => null, 'hasMore' => false];
        }

        $ids = array_map(static fn($c) => (int)$c['id'], $candidates);

        $pdo = SQLiteStatistics::connect(['mode' => '?mode=ro']);

        // endDate 以下で最も近い日（＝基準）と startDate 以下で最も近い日（＝過去）
        $currentMap = $this->fetchMemberAtOrBeforeDate($pdo, $ids, $endDate);
        $pastMap    = $this->fetchMemberAtOrBeforeDate($pdo, $ids, $startDate);

        $rows = $this->buildRows($candidates, $currentMap, $pastMap, $order);
        $totalMatched = count($rows);
        [$paged, $hasMore] = $this->paginate($rows, $offset, $limit);

        return [
            'data'         => $paged,
            'days'         => $days,
            'totalMatched' => $totalMatched,
            'baseDate'     => $endDate,
            'pastDate'     => $startDate,
            'hasMore'      => $hasMore,
        ];
    }

    /**
     * 候補×（基準時点マップ, 過去時点マップ）から差分行を組み立ててソートする。
     * findPeriodGrowth / findPeriodGrowthByDateRange 共通。
     *
     * @param array<int, array<string, mixed>> $candidates
     * @param array<int, array{member:int, date:string}> $currentMap
     * @param array<int, array{member:int, date:string}> $pastMap
     * @return array<int, array<string, mixed>>
     */
    private function buildRows(array $candidates, array $currentMap, array $pastMap, string $order): array
    {
        $rows = [];
        foreach ($candidates as $c) {
            $id = (int)$c['id'];

            if (!isset($pastMap[$id])) {
                continue;
            }
            $pastMember = (int)$pastMap[$id]['member'];

            if (!isset($currentMap[$id])) {
                continue;
            }
            $currentMember = (int)$currentMap[$id]['member'];

            $diff = $currentMember - $pastMember;
            $percent = $pastMember > 0 ? round(($diff / $pastMember) * 100, 1) : 0.0;

            $rows[] = [
                'candidate'      => $c,
                'currentMember'  => $currentMember,
                'pastMember'     => $pastMember,
                'diff'           => $diff,
                'percent'        => $percent,
                'pastDateActual' => $pastMap[$id]['date'],
                'baseDateActual' => $currentMap[$id]['date'],
            ];
        }

        usort($rows, function ($a, $b) use ($order) {
            if ($a['diff'] !== $b['diff']) {
                return $order === 'asc'
                    ? $a['diff'] <=> $b['diff']
                    : $b['diff'] <=> $a['diff'];
            }
            return (int)$b['candidate']['member'] <=> (int)$a['candidate']['member'];
        });

        return $rows;
    }

    /**
     * MySQL: キーワード(＋カテゴリ)一致のルームを候補として取得。
     * member降順で CANDIDATE_LIMIT 件まで。
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchCandidates(string $keyword, int $category): array
    {
        DB::connect();

        $params = [];
        $where = [];

        if ($category) {
            $where[] = 'oc.category = :category';
            $params['category'] = $category;
        }

        // キーワードは AlphaQueryBuilder と同じく name/description の LIKE（スペース区切りAND）
        $keywords = $this->parseKeywords($keyword);
        foreach ($keywords as $i => $kw) {
            $where[] = "(oc.name LIKE :keyword{$i} OR oc.description LIKE :keyword{$i})";
            $params["keyword{$i}"] = '%' . $kw . '%';
        }

        $whereClause = empty($where) ? '1' : implode(' AND ', $where);
        $limit = self::CANDIDATE_LIMIT;

        $sql = "
            SELECT
                oc.id,
                oc.name,
                oc.description,
                oc.member,
                oc.img_url,
                oc.emblem,
                oc.join_method_type,
                oc.category,
                oc.created_at,
                oc.api_created_at,
                oc.url
            FROM open_chat AS oc
            WHERE {$whereClause}
            ORDER BY oc.member DESC
            LIMIT {$limit}
        ";

        return DB::fetchAll($sql, $params);
    }

    /**
     * SQLite: 統計テーブルの最新日付（基準日）を取得
     */
    private function resolveBaseDate(\PDO $pdo): ?string
    {
        $stmt = $pdo->query("SELECT MAX(date) AS d FROM statistics");
        $date = $stmt->fetchColumn();

        return ($date === false || $date === null) ? null : (string)$date;
    }

    /**
     * SQLite: 指定id群について「$date 以下で最も近い日」のメンバー数を取得。
     *
     * statistics は (open_chat_id, date) ユニーク・date昇順なので、
     * GROUP BY open_chat_id + MAX(date) でidごとの「その日以前で最も新しい行」を引く。
     *
     * @param int[] $ids
     * @return array<int, array{member: int, date: string}> open_chat_id => 行
     */
    private function fetchMemberAtOrBeforeDate(\PDO $pdo, array $ids, string $date): array
    {
        $result = [];

        foreach (array_chunk($ids, self::SQLITE_IN_CHUNK) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));

            // 各idについて date <= :date の中で最大の date を選び、その行の member を取る。
            // (open_chat_id, date) はユニークなので、MAX(date) に対応する member は一意。
            $sql = "
                SELECT s.open_chat_id, s.member, s.date
                FROM statistics AS s
                JOIN (
                    SELECT open_chat_id, MAX(date) AS max_date
                    FROM statistics
                    WHERE open_chat_id IN ({$placeholders})
                      AND date <= ?
                    GROUP BY open_chat_id
                ) AS m
                  ON s.open_chat_id = m.open_chat_id AND s.date = m.max_date
            ";

            $params = $chunk;
            $params[] = $date;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $result[(int)$row['open_chat_id']] = [
                    'member' => (int)$row['member'],
                    'date'   => (string)$row['date'],
                ];
            }
        }

        return $result;
    }

    /**
     * キーワードをパース（全角スペース→半角、空要素除去）
     * AlphaOpenChatRepository と同じ仕様
     *
     * @return string[]
     */
    private function parseKeywords(string $keyword): array
    {
        $normalized = str_replace('　', ' ', $keyword);
        return array_values(array_filter(explode(' ', $normalized), fn($k) => trim($k) !== ''));
    }
}
