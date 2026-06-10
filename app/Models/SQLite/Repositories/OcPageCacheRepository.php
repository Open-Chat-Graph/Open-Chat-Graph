<?php

declare(strict_types=1);

namespace App\Models\SQLite\Repositories;

use App\Models\SQLite\SQLiteOcPageCache;

/**
 * ルーム個別ページの事前計算HTML断片（分析文・関連ルーム）の読み書き。
 *
 * - 読み: /oc 表示時に PK 一発 SELECT（mode=rw。mode=ro×WAL の locking protocol を避けるため）。
 *   キャッシュ未生成（DBファイル無し・該当行無し）は null を返し、呼び出し側は空表示にフォールバックする。
 * - 書き: 背景バッチ（OcPageCacheGenerator）が単一プロセスで INSERT OR REPLACE する。
 *   HTML はクォートを含むため、SQLiteInsertImporter（値を素で埋め込む・OR IGNORE）は使わず、
 *   パラメータ化したプリペアドステートメント + トランザクションで upsert する。
 */
class OcPageCacheRepository
{
    /**
     * @return array{narrative_html: string, recommend_html: string}|null
     */
    public function get(int $open_chat_id): ?array
    {
        try {
            SQLiteOcPageCache::connect(['mode' => '?mode=rw']);
            $row = SQLiteOcPageCache::fetch(
                'SELECT narrative_html, recommend_html FROM oc_page_cache WHERE open_chat_id = ?',
                [$open_chat_id]
            );
        } catch (\PDOException) {
            // DBファイル未作成（バックフィル前）等は「キャッシュ無し」として扱う
            return null;
        }

        return is_array($row) ? $row : null;
    }

    /**
     * 部屋単位HTMLを一括 upsert する（背景バッチ専用・単一プロセス直列書き込み）。
     *
     * @param array<array{open_chat_id: int, narrative_html: string, recommend_html: string}> $rows
     */
    public function upsertMany(array $rows): void
    {
        if (!$rows) {
            return;
        }

        $pdo = SQLiteOcPageCache::connect(); // 既定 rwc: WAL有効・ファイル未作成なら生成

        // 既存環境では setup スクリプト未再実行で .db/テーブルが無いことがあるため、
        // バックフィルが自前でテーブルを作る（schema は setup/schema/sqlite/oc_page_cache.sql と同一）。
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS oc_page_cache ('
                . 'open_chat_id INTEGER PRIMARY KEY, '
                . "narrative_html TEXT NOT NULL DEFAULT '', "
                . "recommend_html TEXT NOT NULL DEFAULT '', "
                . 'updated_at TEXT NOT NULL)'
        );

        $now = (new \DateTime)->format('Y-m-d H:i:s');

        $stmt = $pdo->prepare(
            'INSERT OR REPLACE INTO oc_page_cache
                (open_chat_id, narrative_html, recommend_html, updated_at)
             VALUES (?, ?, ?, ?)'
        );

        $pdo->beginTransaction();
        try {
            foreach ($rows as $r) {
                $stmt->execute([
                    $r['open_chat_id'],
                    $r['narrative_html'],
                    $r['recommend_html'],
                    $now,
                ]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
