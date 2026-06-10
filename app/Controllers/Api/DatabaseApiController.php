<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\SQLite\SQLiteOcgraphSqlapi;
use App\Models\Repositories\Api\ApiDeletedOpenChatListRepository;
use Shared\Exceptions\ValidationException;

class DatabaseApiController
{
    // Migrated to SQLite - no longer need DB_NAME constant
    // private const DB_NAME = 'ocgraph_sqlapi';
    private const MAX_LIMIT = 20;
    private const DEFAULT_LIMIT = 20;

    function index(string $stmt)
    {
        header('Content-Type: application/json');
        ob_start('ob_gzhandler');

        try {
            $pdo = $this->getPdo();
            $result = $pdo->query($this->filterQuery($stmt));

            echo json_encode([
                'status' => 'success',
                'data' => $result->fetchAll(\PDO::FETCH_ASSOC),
                'lastUpdate' => $this->getLastImportedAt($pdo),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        }
    }

    function ban(ApiDeletedOpenChatListRepository $repo, string $date)
    {
        header('Content-Type: application/json');
        ob_start('ob_gzhandler');

        $result = $repo->getDeletedOpenChatList($date, 999999);

        $response = [];
        if($result) {
            $response = array_column($result, 'openchat_id');
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    function schema()
    {
        header('Content-Type: application/json');
        ob_start('ob_gzhandler');

        try {
            // schema.sqlファイルの内容をそのまま返す
            $schemaFilePath = \App\Config\AppConfig::SQLITE_SCHEMA_SQLAPI;

            if (!file_exists($schemaFilePath)) {
                throw new \Exception('Schema file not found: ' . $schemaFilePath);
            }

            $schemaContent = file_get_contents($schemaFilePath);

            // 改行で分割して配列として返す
            $schemaLines = explode("\n", $schemaContent);

            // レスポンス
            $response = [
                'database_type' => 'SQLite 3',
                'schema' => $schemaLines,
                'lastUpdate' => $this->getLastImportedAt($this->getPdo())
            ];

            echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        } catch (\Exception $e) {
            echo json_encode([
                'error' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        }
    }

    private function getPdo(): \PDO
    {
        // Use SQLite connection
        return SQLiteOcgraphSqlapi::connect(['mode' => '?mode=ro']); // Read-only mode
    }

    /**
     * インポート処理（OcreviewApiDataImporter）が最後に完了した時刻を返す。
     * import_meta テーブルが無い・未記録の場合は null。
     */
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

    private function filterQuery(string $query): string
    {
        // 長さチェックのみ
        if (strlen($query) > 10000) {
            throw new ValidationException('Query too long');
        }

        // UPDATE / DELETE / INSERT を禁止（大文字小文字を問わず）
        if (preg_match('/^\s*(UPDATE|DELETE|INSERT)\b/i', $query)) {
            throw new ValidationException('UPDATE / DELETE / INSERT statements are not allowed');
        }

        // LIMITチェック
        if (preg_match('/^\s*SELECT/i', $query)) {
            if (preg_match('/LIMIT\s+(\d+)/i', $query, $matches)) {
                $limit = (int)$matches[1];
                if ($limit > self::MAX_LIMIT) {
                    throw new ValidationException('LIMIT cannot exceed ' . self::MAX_LIMIT);
                }
            } else {
                $query = rtrim($query, ';') . ' LIMIT ' . self::DEFAULT_LIMIT;
            }
        }

        return $query;
    }
}
