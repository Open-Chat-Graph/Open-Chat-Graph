<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Services\Storage\FileStorageInterface;

/**
 * 同一クライアント(IP)×スコープ単位の「同時1リクエスト」ガード。
 *
 * 重いエンドポイント（公式ランキング掲載分析の一覧 /list・詳細成長分析 /analysis-*）で、
 * 1つのIPからの未完了リクエストがあるうちは同じスコープの2本目を受け付けない（即 429）。
 * これにより、1IPが重いリクエストを多数並列に積み上げてDBを飽和させるのを防ぐ
 * （2026-06-24 のクローラ深堀り巡回による gone away 障害の再発防止・多層防御）。
 *
 * 仕組み: storage/{locale}/inflight_locks/{scope}_{IP由来バケット}.lock を flock(LOCK_EX|LOCK_NB)。
 * - ロックはこのリクエスト処理が終わる（＝スクリプト終了でファイルハンドルが閉じる）と自動解放される。
 * - 取得済みなら別リクエストが処理中 → false を返す（呼び出し側が 429 を返す）。
 * - ロックファイルが作れない等の異常時は true（フェイルオープン＝本処理は止めない）。
 *
 * クライアントが fetch を中断(abort)しても、サーバ側は処理が終わるまでロックを保持する
 * （DBクエリ中は切断検知できないため）。高速なフィルタ変更で 2本目が一時的に 429 になりうるが、
 * フロント側で短い待機後にリトライして吸収する（一覧JS／成長分析フックの既存リトライ）。
 *
 * 排他ロックの取り方は App\Services\Api\DatabaseApiRateLimiter（データAPIのユーザー単位版）と同型。
 */
class ConcurrentRequestGuard
{
    /**
     * IP をこの数のバケットへ写像してロックファイル数を上限化する。
     * IP ごとに無限にファイルを作ると共有ホスティングの inode 上限を食い潰すため。
     * 同一IPは常に同じバケットなので「IP単位の同時1リクエスト」は厳密に保たれる。
     * 別IPが同じバケットに当たる確率は 1/この数で、ロック保持は一瞬（リクエスト処理中だけ）のため誤ブロックは無視できる。
     */
    private const LOCK_BUCKETS = 4096;

    /** @var resource|null 同時実行ロックのファイルハンドル（リクエスト終了まで保持する） */
    private $lockHandle = null;

    public function __construct(
        private FileStorageInterface $fileStorage,
    ) {}

    /**
     * 指定スコープ×IP の同時実行ロックを取得する。実行中の同一リクエストがあれば false。
     * ロックファイルが作れない等の異常時は true（フェイルオープン）。
     *
     * @param string $scope エンドポイント種別（例 'pubanalytics-list'）。別スコープ同士は互いに阻害しない
     * @param string $ip    クライアントIP（getIP()）
     */
    public function tryAcquire(string $scope, string $ip): bool
    {
        // ロック機構の不具合（権限・I/O 等）でリクエスト本体を止めない（フェイルオープン）。
        // ※ このアプリのエラーハンドラは @ を無視して警告を例外化するため try/catch で受ける。
        try {
            $dir = $this->fileStorage->getStorageFilePath('inFlightLockDir');
            if (!is_dir($dir)) {
                // 併発リクエストが同時に mkdir すると「File exists」になるが、出来ていれば問題ない
                try {
                    mkdir($dir, 0777, true);
                } catch (\Throwable $e) {
                    // レースは無視（直後の is_dir で確認）
                }
                if (!is_dir($dir)) {
                    return true; // 作成できない異常時はフェイルオープン
                }
            }

            // ファイル名 = スコープ名＋IP由来のバケット番号（スコープごとに最大 LOCK_BUCKETS 個・無限増殖しない）
            $bucket = hexdec(substr(hash('sha256', $ip), 0, 8)) % self::LOCK_BUCKETS;
            $safeScope = preg_replace('/[^a-z0-9_-]/', '', $scope);
            $fp = fopen($dir . '/' . $safeScope . '_' . $bucket . '.lock', 'c');
            if ($fp === false) {
                return true; // フェイルオープン
            }

            if (!flock($fp, LOCK_EX | LOCK_NB)) {
                fclose($fp);
                return false; // 同一IP×スコープのリクエストが処理中
            }

            $this->lockHandle = $fp;
            return true;
        } catch (\Throwable $e) {
            return true; // フェイルオープン
        }
    }

    /**
     * 明示解放（通常は不要＝リクエスト終了で自動解放されるが、重い処理の前後で早く手放したいとき用）。
     */
    public function release(): void
    {
        if ($this->lockHandle !== null) {
            flock($this->lockHandle, LOCK_UN);
            fclose($this->lockHandle);
            $this->lockHandle = null;
        }
    }
}
