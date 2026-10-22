<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Config\AppConfig;
use App\Models\SQLite\SQLiteOcgraphSqlapi;
use App\Services\Api\DatabaseApiRateLimiter;
use App\Services\Storage\FileStorageInterface;
use Shared\Exceptions\ValidationException;

class DatabaseApiController
{
    // Migrated to SQLite - no longer need DB_NAME constant
    // private const DB_NAME = 'ocgraph_sqlapi';
    private const MAX_LIMIT = 20;
    private const DEFAULT_LIMIT = 20;

    /**
     * 登録ユーザーにも見せないテーブル（公開 MCP と同じ基準）。
     * ban_user / comment_log は IP 等の個人情報（sqlapi.db にはハッシュ化して書き出すが二重に塞ぐ）、
     * ban_room は運営内部データ。
     */
    private const BLOCKED_TABLES = ['ban_user', 'comment_log', 'ban_room'];

    /** 別 DB ファイルへの接続・内部設定・拡張ロード・終わらない再帰クエリを拒否するキーワード */
    private const BLOCKED_KEYWORDS = ['attach', 'pragma', 'load_extension', 'recursive'];

    function index(string $username, string $stmt)
    {
        header('Content-Type: application/json');
        ob_start('ob_gzhandler');

        // レートリミット（管理者は対象外。{username}=admin は routing.php で Basic 認証済み）
        $limiter = null;
        if ($username !== 'admin') {
            $limiter = new DatabaseApiRateLimiter($username, app(FileStorageInterface::class));

            // 1. 同時実行は1リクエストまで
            if (!$limiter->acquireLock()) {
                $this->sendError(429, 'Too many concurrent requests: only one request can be processed at a time. Please wait for the previous request to finish.');
                return;
            }

            // 2. 直近5分間で取得できるレコード数は1000件まで
            if ($limiter->isQuotaExceeded()) {
                $retryAfter = $limiter->getRetryAfterSeconds();
                header('Retry-After: ' . $retryAfter);
                $this->sendError(429, sprintf(
                    'Rate limit exceeded: up to %d records can be fetched per %d minutes. Retry after %d seconds.',
                    AppConfig::API_RATE_LIMIT_MAX_RECORDS,
                    AppConfig::API_RATE_LIMIT_WINDOW_SECONDS / 60,
                    $retryAfter,
                ));
                return;
            }
        }

        try {
            $pdo = $this->getPdo();
            $result = $pdo->query($this->filterQuery($stmt));
            $data = $result->fetchAll(\PDO::FETCH_ASSOC);

            $limiter?->addRecordCount(count($data));

            echo json_encode([
                'status' => 'success',
                'data' => $data,
                'lastUpdate' => $this->getLastImportedAt($pdo),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        } catch (\Exception $e) {
            $this->sendError(400, $e->getMessage());
        }
    }

    private function sendError(int $code, string $message): void
    {
        http_response_code($code);
        echo json_encode([
            'status' => 'error',
            'message' => $message,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    function schema()
    {
        header('Content-Type: application/json');
        ob_start('ob_gzhandler');

        try {
            // schema.sqlファイルの内容をそのまま返す
            $schemaFilePath = AppConfig::SQLITE_SCHEMA_SQLAPI;

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
        if (strlen($query) > 10000) {
            throw new ValidationException('Query too long');
        }

        foreach (self::BLOCKED_KEYWORDS as $kw) {
            if (preg_match('/\b' . $kw . '\b/i', $query)) {
                throw new ValidationException("'{$kw}' is not allowed");
            }
        }

        // SQLite の識別子はコメントや連結で分割表記できないため、部分一致の拒否で到達経路を塞げる
        foreach (self::BLOCKED_TABLES as $table) {
            if (stripos($query, $table) !== false) {
                throw new ValidationException("Table '{$table}' is not accessible via the database API");
            }
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
