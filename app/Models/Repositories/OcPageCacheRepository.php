<?php

declare(strict_types=1);

namespace App\Models\Repositories;

use App\Models\Repositories\DB;
use App\Services\Storage\FileStorageInterface;

/**
 * ルーム個別ページの分析文(narrative)事前計算データの書き込み(MySQL: oc_page_cache)。
 *
 * キャッシュに入れるのは分析の「データ」(JSON {summary,detail,meta_description,pattern})のみで、
 * HTMLは保存しない（レンダリングはリクエスト時に oc_narrative_section テンプレートが行う）。
 * 言語別 DB（ocgraph_ocreview / _tw / _th）に格納し、urlRoot に応じて DB::connect() が
 * 接続先を選ぶ。書き込みは背景バッチ(OcPageCacheGenerator)が単一プロセスで行う。
 *
 * 読み取りは getOpenChatByIdWithTag() の oc_page_cache LEFT JOIN で /oc 表示時に
 * open_chat と一緒に1クエリで取得するため、このクラスに read メソッドは無い。
 * テーブルは setup/schema/mysql/*.sql に定義し、デプロイ時 sync_mysql_schema.php が追加する。
 */
class OcPageCacheRepository implements OcPageCacheRepositoryInterface
{
    public function __construct(
        private FileStorageInterface $fileStorage,
    ) {
    }

    public function upsertMany(array $rows): void
    {
        if (!$rows) {
            return;
        }

        $pdo = DB::connect();
        // updated_at は「データの最終時点」= 毎時クロール完了時刻を採る（wall-clock の now ではない）。
        // クロールが止まった環境で再生成しても、キャッシュの鮮度がデータと一致する。取得不能なら now。
        $now = $this->generatedAt();

        $stmt = $pdo->prepare(
            'INSERT INTO oc_page_cache (open_chat_id, narrative_data, updated_at)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE
                 narrative_data = VALUES(narrative_data),
                 updated_at     = VALUES(updated_at)'
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
