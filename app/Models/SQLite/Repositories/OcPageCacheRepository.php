<?php

declare(strict_types=1);

namespace App\Models\SQLite\Repositories;

use App\Models\SQLite\SQLiteOcPageCache;

/**
 * ルーム個別ページの分析(narrative)事前計算データの読み書き。
 * キャッシュに入れるのは分析の「データ」(JSON)のみで、HTMLは保存しない
 * （レンダリングはリクエスト時に oc_narrative_section テンプレートが行う）。
 * DB/テーブルは他のSQLite同様、setup(setup/schema/sqlite/oc_page_cache.sql)と prod-sync が用意する。
 *
 * - 読み: /oc 表示時に PK 一発 SELECT（mode=rw。mode=ro×WAL の locking protocol を避けるため）。
 *   キャッシュ未生成（DBファイル無し・該当行無し）は null を返し、呼び出し側は空表示にフォールバックする。
 *   narrative_html は旧形式（事前レンダリングHTML）の行のための移行期読み取り専用。
 * - 書き: 背景バッチ（OcPageCacheGenerator）が単一プロセスで INSERT OR REPLACE する。
 *   値はクォートを含むため、SQLiteInsertImporter（値を素で埋め込む・OR IGNORE）は使わず、
 *   パラメータ化したプリペアドステートメント + トランザクションで upsert する。
 */
class OcPageCacheRepository
{
    /**
     * narrative_data はデプロイ時の冪等ALTERで追加されたカラムのため、
     * 移行された既存行では NULL があり得る（呼び出し側は empty() で判定する）。
     *
     * @return array{narrative_data: ?string, narrative_html: string}|null
     */
    public function get(int $open_chat_id): ?array
    {
        try {
            SQLiteOcPageCache::connect(['mode' => '?mode=rw']);
            $row = SQLiteOcPageCache::fetch(
                'SELECT narrative_data, narrative_html FROM oc_page_cache WHERE open_chat_id = ?',
                [$open_chat_id]
            );
        } catch (\PDOException) {
            // DBファイル未作成（バックフィル前）等は「キャッシュ無し」として扱う
            return null;
        }

        return is_array($row) ? $row : null;
    }

    /**
     * 部屋単位の分析データを一括 upsert する（背景バッチ専用・単一プロセス直列書き込み）。
     * 旧形式カラム(narrative_html/recommend_html)は空で上書きし、再生成された行から旧形式を排除する。
     *
     * @param array<array{open_chat_id: int, narrative_data: string}> $rows
     */
    public function upsertMany(array $rows): void
    {
        if (!$rows) {
            return;
        }

        $pdo = SQLiteOcPageCache::connect();
        $now = (new \DateTime)->format('Y-m-d H:i:s');

        $stmt = $pdo->prepare(
            'INSERT OR REPLACE INTO oc_page_cache
                (open_chat_id, narrative_data, narrative_html, recommend_html, updated_at)
             VALUES (?, ?, \'\', \'\', ?)'
        );

        $pdo->beginTransaction();
        try {
            foreach ($rows as $r) {
                $stmt->execute([
                    $r['open_chat_id'],
                    $r['narrative_data'],
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
