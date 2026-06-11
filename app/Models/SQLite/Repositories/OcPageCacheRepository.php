<?php

declare(strict_types=1);

namespace App\Models\SQLite\Repositories;

use App\Models\SQLite\SQLiteOcPageCache;
use App\Services\Storage\FileStorageInterface;

/**
 * ルーム個別ページの分析(narrative)事前計算データの読み書き。
 * キャッシュに入れるのは分析の「データ」(JSON)のみで、HTMLは保存しない
 * （レンダリングはリクエスト時に oc_narrative_section テンプレートが行う）。
 * DB/テーブルは他のSQLite同様、setup(setup/schema/sqlite/oc_page_cache.sql)と prod-sync が用意する。
 *
 * - 読み: /oc 表示時に PK 一発 SELECT（mode=ro。読み取り専用接続。書き込み権限や
 *   WAL チェックポイントを発生させず、Web(www-data)から安全に読むため）。
 *   キャッシュ未生成（DBファイル無し・該当行無し）は null を返し、呼び出し側は空表示にフォールバックする。
 * - 書き: 背景バッチ（OcPageCacheGenerator）が単一プロセスで INSERT OR REPLACE する。
 *   値はクォートを含むため、SQLiteInsertImporter（値を素で埋め込む・OR IGNORE）は使わず、
 *   パラメータ化したプリペアドステートメント + トランザクションで upsert する。
 */
class OcPageCacheRepository
{
    public function __construct(
        private FileStorageInterface $fileStorage,
    ) {
    }

    /**
     * narrative_data はデプロイ時の冪等ALTERで追加されたカラムのため、
     * 未生成の既存行では NULL があり得る（呼び出し側は empty() で判定する）。
     *
     * @return array{narrative_data: ?string}|null
     */
    public function get(int $open_chat_id): ?array
    {
        try {
            SQLiteOcPageCache::connect(['mode' => '?mode=ro']);
            $row = SQLiteOcPageCache::fetch(
                'SELECT narrative_data FROM oc_page_cache WHERE open_chat_id = ?',
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
        // updated_at は「データの最終時点」= 毎時クロール完了時刻を採る（wall-clock の now ではない）。
        // クロールが止まった環境で再生成しても、キャッシュの鮮度がデータと一致する。取得不能なら now。
        $now = $this->generatedAt();

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

    /**
     * キャッシュ行の updated_at（= データの最終時点）。毎時クロール完了時刻を採り、
     * 取得不能（ファイル無し/空/不正）なら wall-clock の now にフォールバックする。
     */
    private function generatedAt(): string
    {
        try {
            $cron = trim((string)$this->fileStorage->getContents('@hourlyCronUpdatedAtDatetime'));
            if ($cron !== '') {
                return (new \DateTime($cron))->format('Y-m-d H:i:s');
            }
        } catch (\Throwable) {
        }
        return (new \DateTime)->format('Y-m-d H:i:s');
    }
}
