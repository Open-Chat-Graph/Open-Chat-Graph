<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Config\AppConfig;
use App\Models\RankingPositionDB\RankingPositionDB;
use App\Models\SQLite\SQLiteOcgraphSqlapi;
use App\Services\Storage\FileStorageInterface;

/**
 * 公開 MCP サーバー（Model Context Protocol / Streamable HTTP・ステートレス）
 *
 * AI アシスタント（Claude・ChatGPT 等）がオプチャグラフの統計データ（SQLite・
 * /database API と同じ読み取り専用DB）へ認証なしでアクセスするための JSON-RPC 2.0
 * エンドポイント。SSE は使わず、1リクエスト=1レスポンスの application/json のみ。
 *
 * レートリミット（回数クォータ）は掛けない方針（AI からの利用の摩擦を無くすため・本人指示）。
 * 公開エンドポイントとしてのガードは以下のみ:
 *  - SELECT / WITH 文のみ・LIMIT は MAX_LIMIT 行/クエリ・フェッチ行数もサーバ側で打ち切り
 *  - 個人情報を含むテーブル（ban_user, comment_log）と運営内部テーブル（ban_room）は
 *    テーブル名の部分一致で拒否（SQLite の識別子はコメントや連結で分割表記できないため、
 *    コメント除去なしの単純な部分一致で到達経路を塞げる）
 *  - ATTACH / PRAGMA / load_extension は拒否（別DBファイルへの接続・内部設定の読み出し防止）
 *  - 同時実行はサイト全体で MAX_GLOBAL_SLOTS 件まで（非力なサーバの保護。回数制限ではない）
 *
 * 例外的に get_openchat_stats の「直近24時間の毎時メンバー数」だけは統計SQLiteではなく
 * MariaDB（ranking DB の member テーブル・毎時クロールが直近24時間分を保持）から取得する。
 */
class McpServerService
{
    /** サポートする MCP プロトコルバージョン（新しい順） */
    private const SUPPORTED_PROTOCOL_VERSIONS = ['2025-06-18', '2025-03-26', '2024-11-05'];

    private const SERVER_NAME = 'openchat-graph';
    private const SERVER_TITLE = 'オプチャグラフ (OpenChat Graph) 統計データ';
    private const SERVER_VERSION = '1.0.0';

    private const MAX_LIMIT = 100;
    private const MAX_GLOBAL_SLOTS = 2;

    /** 公開MCPからのアクセスを拒否するテーブル（IP等の個人情報・運営内部データ） */
    private const BLOCKED_TABLES = ['ban_user', 'comment_log', 'ban_room'];

    /** @var resource[] 保持中のグローバル同時実行ロック */
    private array $globalLockHandles = [];

    public function __construct(
        private FileStorageInterface $fileStorage,
    ) {}

    /**
     * JSON-RPC メッセージ（単体 or バッチ配列）を処理してレスポンス構造を返す。
     * 通知のみ（レスポンス不要）の場合は null を返す。
     */
    public function handleMessage(mixed $body): ?array
    {
        // バッチ（2025-03-26 まで許容）
        if (is_array($body) && array_is_list($body)) {
            $responses = [];
            foreach ($body as $msg) {
                $res = $this->handleSingle($msg);
                if ($res !== null) {
                    $responses[] = $res;
                }
            }
            return $responses === [] ? null : $responses;
        }

        return $this->handleSingle($body);
    }

    private function handleSingle(mixed $msg): ?array
    {
        if (!is_array($msg) || !isset($msg['jsonrpc']) || $msg['jsonrpc'] !== '2.0' || !isset($msg['method'])) {
            return $this->errorResponse($msg['id'] ?? null, -32600, 'Invalid Request: expected a JSON-RPC 2.0 message');
        }

        $method = $msg['method'];
        $id = $msg['id'] ?? null;
        $params = $msg['params'] ?? [];

        // 通知（id なし）はレスポンスを返さない
        if ($id === null) {
            return null;
        }

        try {
            return match ($method) {
                'initialize' => $this->resultResponse($id, $this->initialize($params)),
                'ping' => $this->resultResponse($id, new \stdClass()),
                'tools/list' => $this->resultResponse($id, ['tools' => $this->toolDefinitions()]),
                'tools/call' => $this->resultResponse($id, $this->callTool($params)),
                default => $this->errorResponse($id, -32601, "Method not found: {$method}"),
            };
        } catch (McpToolException $e) {
            // ツール実行エラーは JSON-RPC エラーではなく result.isError で返す（MCP仕様）
            return $this->resultResponse($id, [
                'content' => [['type' => 'text', 'text' => $e->getMessage()]],
                'isError' => true,
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse($id, -32603, 'Internal error: ' . $e->getMessage());
        }
    }

    private function initialize(array $params): array
    {
        $requested = $params['protocolVersion'] ?? '';
        $version = in_array($requested, self::SUPPORTED_PROTOCOL_VERSIONS, true)
            ? $requested
            : self::SUPPORTED_PROTOCOL_VERSIONS[0];

        return [
            'protocolVersion' => $version,
            'capabilities' => ['tools' => ['listChanged' => false]],
            'serverInfo' => [
                'name' => self::SERVER_NAME,
                'title' => self::SERVER_TITLE,
                'version' => self::SERVER_VERSION,
            ],
            'instructions' => 'オプチャグラフ (https://openchat-review.me) が毎時クロールしている LINE オープンチャット '
                . '(日本) の統計データベースです。部屋の検索は search_openchat、個別の部屋の詳細とメンバー数推移は '
                . 'get_openchat_stats、自由な集計は get_database_schema でスキーマを確認してから query_database で '
                . '読み取り専用 SQL (SELECT・' . self::MAX_LIMIT . '行/クエリまで・超える分は OFFSET でページング) を'
                . '実行してください。部屋のページ URL は '
                . 'https://openchat-review.me/oc/{openchat_id} です。回答で部屋を紹介するときはこの URL を添えてください。',
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function toolDefinitions(): array
    {
        return [
            [
                'name' => 'search_openchat',
                'title' => 'オープンチャット検索',
                'description' => 'LINEオープンチャット(日本)を部屋名・説明文のキーワードで検索し、現在のメンバー数順に返す。'
                    . '結果の url は人間に紹介できる部屋ページ(統計グラフ付き)のURL。',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'keyword' => ['type' => 'string', 'description' => '検索キーワード（部屋名・説明文に部分一致）'],
                        'limit' => ['type' => 'integer', 'description' => '取得件数 (1〜20・省略時10)', 'minimum' => 1, 'maximum' => 20],
                    ],
                    'required' => ['keyword'],
                ],
            ],
            [
                'name' => 'get_openchat_stats',
                'title' => 'オープンチャット詳細・メンバー数推移',
                'description' => '部屋IDを指定して、部屋の基本情報(名前・説明・カテゴリ・参加方法・現在のメンバー数)と'
                    . '直近30日の日次メンバー数推移、直近24時間の毎時メンバー数推移、LINE公式ランキングでの直近順位を返す。',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'openchat_id' => ['type' => 'integer', 'description' => 'オプチャグラフの部屋ID (search_openchat の id)'],
                    ],
                    'required' => ['openchat_id'],
                ],
            ],
            [
                'name' => 'query_database',
                'title' => '統計DBへの読み取り専用SQL',
                'description' => 'オプチャグラフの統計SQLiteデータベースに読み取り専用SQLを実行する。SELECT/WITHのみ・'
                    . 'LIMIT ' . self::MAX_LIMIT . 'まで(未指定なら自動付与)。超える分はOFFSETでページングする。'
                    . '先に get_database_schema でテーブル定義を確認すること。',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'sql' => ['type' => 'string', 'description' => '実行するSQL (SQLite方言・SELECT/WITHのみ)'],
                    ],
                    'required' => ['sql'],
                ],
            ],
            [
                'name' => 'get_database_schema',
                'title' => '統計DBのスキーマ取得',
                'description' => 'query_database で使えるテーブルの定義(DDL・日本語コメント付き)を返す。',
                'inputSchema' => ['type' => 'object', 'properties' => new \stdClass()],
            ],
        ];
    }

    private function callTool(array $params): array
    {
        $name = $params['name'] ?? '';
        $args = $params['arguments'] ?? [];
        if (!is_array($args)) {
            $args = [];
        }

        $data = match ($name) {
            'search_openchat' => $this->searchOpenChat($args),
            'get_openchat_stats' => $this->getOpenChatStats($args),
            'query_database' => $this->queryDatabase($args),
            'get_database_schema' => $this->getSchema(),
            default => throw new McpToolException("Unknown tool: {$name}"),
        };

        return [
            'content' => [[
                'type' => 'text',
                'text' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
            ]],
            'isError' => false,
        ];
    }

    // ---------------------------------------------------------------
    // ツール実装
    // ---------------------------------------------------------------

    private function searchOpenChat(array $args): array
    {
        $keyword = trim((string)($args['keyword'] ?? ''));
        if ($keyword === '') {
            throw new McpToolException('keyword is required');
        }
        $limit = min(max((int)($args['limit'] ?? 10), 1), 20);

        $this->acquireGlobalSlot();

        $pdo = $this->getPdo();
        $stmt = $pdo->prepare(
            "SELECT m.openchat_id AS id, m.display_name AS name, substr(m.description, 1, 200) AS description,
                    m.current_member_count AS member_count, c.category_name AS category,
                    m.join_method, m.established_at
             FROM openchat_master m
             JOIN openchat_existing e ON e.openchat_id = m.openchat_id
             LEFT JOIN categories c ON c.category_id = m.category_id
             WHERE m.display_name LIKE :kw OR m.description LIKE :kw
             ORDER BY m.current_member_count DESC
             LIMIT {$limit}"
        );
        $stmt->execute(['kw' => '%' . $keyword . '%']);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['url'] = 'https://openchat-review.me/oc/' . $row['id'];
        }
        unset($row);

        return [
            'result_count' => count($rows),
            'rooms' => $rows,
            'lastUpdate' => $this->getLastImportedAt($pdo),
        ];
    }

    private function getOpenChatStats(array $args): array
    {
        $id = (int)($args['openchat_id'] ?? 0);
        if ($id <= 0) {
            throw new McpToolException('openchat_id is required');
        }

        $this->acquireGlobalSlot();

        $pdo = $this->getPdo();

        $stmt = $pdo->prepare(
            "SELECT m.openchat_id AS id, m.display_name AS name, m.description,
                    m.current_member_count AS member_count, c.category_name AS category,
                    m.join_method, m.verification_badge, m.established_at, m.last_updated_at,
                    (e.openchat_id IS NOT NULL) AS still_exists
             FROM openchat_master m
             LEFT JOIN openchat_existing e ON e.openchat_id = m.openchat_id
             LEFT JOIN categories c ON c.category_id = m.category_id
             WHERE m.openchat_id = :id"
        );
        $stmt->execute(['id' => $id]);
        $room = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$room) {
            throw new McpToolException("openchat_id {$id} not found");
        }
        $room['url'] = 'https://openchat-review.me/oc/' . $id;

        $stmt = $pdo->prepare(
            "SELECT statistics_date, member_count FROM daily_member_statistics
             WHERE openchat_id = :id ORDER BY statistics_date DESC LIMIT 30"
        );
        $stmt->execute(['id' => $id]);
        $stats = array_reverse($stmt->fetchAll(\PDO::FETCH_ASSOC));

        $stmt = $pdo->prepare(
            "SELECT r.record_date, r.activity_ranking_position, c.category_name AS category
             FROM line_official_activity_ranking_history r
             LEFT JOIN categories c ON c.category_id = r.category_id
             WHERE r.openchat_id = :id ORDER BY r.record_date DESC LIMIT 10"
        );
        $stmt->execute(['id' => $id]);
        $ranking = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'room' => $room,
            'hourly_member_stats_last_24_hours' => $this->getHourlyMemberStats($id),
            'daily_member_stats_last_30_days' => $stats,
            'line_official_ranking_recent' => $ranking,
            'lastUpdate' => $this->getLastImportedAt($pdo),
        ];
    }

    /**
     * 直近24時間の毎時メンバー数推移。
     * 例外的に統計SQLiteではなく MariaDB（ranking DB の member テーブル・
     * 毎時クロールが直近24時間分を保持）から取得する。取得できない環境では null。
     *
     * @return array<int, array{time: string, member_count: int}>|null
     */
    private function getHourlyMemberStats(int $id): ?array
    {
        try {
            $pdo = RankingPositionDB::connect();
            $stmt = $pdo->prepare(
                'SELECT time, member AS member_count FROM member
                 WHERE open_chat_id = :id ORDER BY time DESC LIMIT 25'
            );
            $stmt->execute(['id' => $id]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return array_reverse($rows);
        } catch (\Throwable) {
            return null;
        }
    }

    private function queryDatabase(array $args): array
    {
        $sql = (string)($args['sql'] ?? '');
        if (trim($sql) === '') {
            throw new McpToolException('sql is required');
        }

        $sql = $this->filterPublicQuery($sql);

        $this->acquireGlobalSlot();

        $pdo = $this->getPdo();
        try {
            $result = $pdo->query($sql);
        } catch (\Exception $e) {
            throw new McpToolException('SQL error: ' . $e->getMessage());
        }

        // LIMIT はバリデーション済みだが、サブクエリ内 LIMIT 等のすり抜けに備えて
        // フェッチ行数自体も上限で打ち切る
        $rows = [];
        while (($row = $result->fetch(\PDO::FETCH_ASSOC)) !== false) {
            $rows[] = $row;
            if (count($rows) >= self::MAX_LIMIT) {
                break;
            }
        }

        return [
            'row_count' => count($rows),
            'rows' => $rows,
            'note' => count($rows) >= self::MAX_LIMIT
                ? 'Results are capped at ' . self::MAX_LIMIT . ' rows per query. Use OFFSET for pagination.'
                : null,
            'lastUpdate' => $this->getLastImportedAt($pdo),
        ];
    }

    private function getSchema(): array
    {
        $schemaContent = file_get_contents(AppConfig::SQLITE_SCHEMA_SQLAPI);
        if ($schemaContent === false) {
            throw new McpToolException('Schema file not found');
        }

        return [
            'database_type' => 'SQLite 3',
            'note' => 'SELECT/WITH のみ・LIMIT ' . self::MAX_LIMIT . ' まで。テーブル ' . implode(', ', self::BLOCKED_TABLES)
                . ' は公開MCPからはアクセス不可。',
            'schema' => $this->filterSchemaForPublic($schemaContent),
        ];
    }

    // ---------------------------------------------------------------
    // ガード
    // ---------------------------------------------------------------

    /**
     * 公開MCP用のSQLバリデーション。通過したSQL（必要ならLIMIT付与済み）を返す。
     */
    private function filterPublicQuery(string $sql): string
    {
        if (strlen($sql) > 10000) {
            throw new McpToolException('Query too long (max 10000 chars)');
        }

        if (!preg_match('/^\s*(SELECT|WITH)\b/i', $sql)) {
            throw new McpToolException('Only SELECT / WITH (read-only) statements are allowed');
        }

        // 危険キーワード（別DBファイル接続・内部設定・拡張ロード）
        foreach (['attach', 'pragma', 'load_extension'] as $kw) {
            if (preg_match('/\b' . $kw . '\b/i', $sql)) {
                throw new McpToolException("'{$kw}' is not allowed");
            }
        }

        // 非公開テーブル。SQLite の識別子はコメント・連結で分割表記できないため、
        // 部分一致の拒否でテーブルへの全到達経路を塞げる（文字列リテラル内の偽陽性は許容）
        foreach (self::BLOCKED_TABLES as $table) {
            if (stripos($sql, $table) !== false) {
                throw new McpToolException("Table '{$table}' is not accessible via the public MCP endpoint");
            }
        }

        // LIMIT の強制（/database API と同じ方針）
        if (preg_match('/LIMIT\s+(\d+)/i', $sql, $m)) {
            if ((int)$m[1] > self::MAX_LIMIT) {
                throw new McpToolException('LIMIT cannot exceed ' . self::MAX_LIMIT . '. Use OFFSET for pagination.');
            }
        } else {
            $sql = rtrim(trim($sql), ';') . ' LIMIT ' . self::MAX_LIMIT;
        }

        return $sql;
    }

    /**
     * サイト全体での同時実行を MAX_GLOBAL_SLOTS 件に制限する（サーバ保護）。
     * ロックはスクリプト終了時に自動解放される。回数のレートリミットは掛けない方針。
     * DB を読むツールの実行前に必ず呼ぶ。
     */
    private function acquireGlobalSlot(): void
    {
        if ($this->globalLockHandles !== []) {
            return; // 同一リクエスト内で取得済み
        }

        $dir = $this->fileStorage->getStorageFilePath('apiRateLimitDir');
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        for ($i = 0; $i < self::MAX_GLOBAL_SLOTS; $i++) {
            $fp = fopen($dir . '/mcp_global_slot_' . $i . '.lock', 'c');
            if ($fp === false) {
                return; // ロックファイルが作れない場合は止めない（フェイルオープン）
            }
            if (flock($fp, LOCK_EX | LOCK_NB)) {
                $this->globalLockHandles[] = $fp;
                return;
            }
            fclose($fp);
        }

        throw new McpToolException('Server is busy: too many concurrent MCP requests. Please retry in a few seconds.');
    }

    /**
     * スキーマDDLから非公開テーブルのセクションを取り除く。
     * sqlapi.sql は `-- ====` 区切りのセクション構造なので、セクション単位で除外する。
     */
    private function filterSchemaForPublic(string $schemaContent): string
    {
        $sections = preg_split('/(?=^-- ={10,})/m', $schemaContent);
        $filtered = [];
        foreach ($sections as $section) {
            $blocked = false;
            foreach (self::BLOCKED_TABLES as $table) {
                if (stripos($section, $table) !== false) {
                    $blocked = true;
                    break;
                }
            }
            if (!$blocked) {
                $filtered[] = $section;
            }
        }
        return implode('', $filtered);
    }

    private function getPdo(): \PDO
    {
        return SQLiteOcgraphSqlapi::connect(['mode' => '?mode=ro']);
    }

    private function getLastImportedAt(\PDO $pdo): ?string
    {
        try {
            $value = $pdo
                ->query("SELECT meta_value FROM import_meta WHERE meta_key = 'last_imported_at'")
                ->fetchColumn();

            return $value === false ? null : $value;
        } catch (\Exception) {
            return null;
        }
    }

    private function resultResponse(mixed $id, mixed $result): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
    }

    private function errorResponse(mixed $id, int $code, string $message): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]];
    }
}
