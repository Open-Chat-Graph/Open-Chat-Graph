<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Config\SecretsConfig;
use App\Models\RankingPositionDB\RankingPositionDB;
use App\Models\Repositories\Api\ApiDeletedOpenChatListRepository;
use Shared\Exceptions\ValidationException;
use Shared\MimimalCmsConfig;

class DatabaseApiController
{
    private const DB_NAME = 'ocgraph_sqlapi';
    private const MAX_LIMIT = 10000;
    private const DEFAULT_LIMIT = 1000;

    function index(string $stmt)
    {
        header('Content-Type: application/json');
        ob_start('ob_gzhandler');

        // データベースの最終更新時間を取得
        $lastUpdateQuery = "SELECT MAX(time) as last_update FROM rising";
        $lastUpdateStmt = RankingPositionDB::connect()->query($lastUpdateQuery);
        $lastUpdate = $lastUpdateStmt->fetchColumn();

        try {
            $pdo = $this->getPdo();
            $result = $pdo->query($this->filterQuery($stmt));

            echo json_encode([
                'status' => 'success',
                'data' => $result->fetchAll(\PDO::FETCH_ASSOC),
                'lastUpdate' => $lastUpdate,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        } catch (\Exception $e) {
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
            $pdo = $this->getPdo();

            // 全テーブルのCREATE文を一括で取得
            $schemas = [];

            // テーブル一覧を取得
            $tablesQuery = "SHOW TABLES";
            $stmt = $pdo->query($tablesQuery);
            $tables = $stmt->fetchAll(\PDO::FETCH_COLUMN);

            // 各テーブルのCREATE文を取得
            foreach ($tables as $tableName) {
                $createQuery = "SHOW CREATE TABLE `$tableName`";
                $createStmt = $pdo->query($createQuery);
                $createResult = $createStmt->fetch(\PDO::FETCH_ASSOC);

                // 改行とインデントを除去して1行にする
                $createTable = $createResult['Create Table'];
                $createTable = preg_replace('/\n\s*/', ' ', $createTable);
                $createTable = preg_replace('/\s+/', ' ', $createTable);
                $schemas[] = $createTable . ';';
            }

            // データベースの最終更新時間を取得
            $lastUpdateQuery = "SELECT MAX(time) as last_update FROM rising";
            $lastUpdateStmt = RankingPositionDB::connect()->query($lastUpdateQuery);
            $lastUpdate = $lastUpdateStmt->fetchColumn();

            // レスポンス
            $response = [
                'database_type' => 'MariaDB 10.5',
                'tables_count' => count($schemas),
                'schemas' => $schemas,
                'lastUpdate' => $lastUpdate
            ];

            echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        } catch (\PDOException $e) {
            echo json_encode([
                'error' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        }
    }

    private function getPdo(): \PDO
    {
        return new \PDO(
            'mysql:host=' . MimimalCmsConfig::$dbHost . ';dbname=' . self::DB_NAME . ';charset=' . (MimimalCmsConfig::$dbCharset ?? 'utf8mb4'),
            SecretsConfig::$apiDbUser,
            SecretsConfig::$apiDbPassword,
            [
                \PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION TRANSACTION READ ONLY",
            ]
        );
    }

    private function filterQuery(string $query): string
    {
        // 長さチェックのみ
        if (strlen($query) > 10000) {
            throw new ValidationException('Query too long');
        }

        // UPDATE / DELETE を禁止（大文字小文字を問わず）
        if (preg_match('/^\s*(UPDATE|DELETE)\b/i', $query)) {
            throw new ValidationException('UPDATE / DELETE statements are not allowed');
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
