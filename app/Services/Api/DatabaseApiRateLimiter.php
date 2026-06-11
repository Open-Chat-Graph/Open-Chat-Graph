<?php

declare(strict_types=1);

namespace App\Services\Api;

/**
 * データAPI（/database/{username}/query）のユーザー単位レートリミッター
 *
 * 1. 同時実行制限: 同一ユーザーが同時に実行できるリクエストは1件まで
 *    （ファイルの排他ロックで判定。ロックはスクリプト終了時に自動解放される）
 * 2. レコード数制限: 直近5分間に取得できるレコード数は合計1000件まで
 *    （取得履歴 [時刻, 件数] をユーザーごとのJSONファイルに保存して集計する）
 *
 * 状態ファイルは storage/api_rate_limit/ 配下に置く（gitignore対象）。
 * 取得履歴ファイルの読み書きは同時実行ロックを保持した状態でのみ行うため、
 * 履歴ファイル自体の排他制御は不要。
 */
class DatabaseApiRateLimiter
{
    public const WINDOW_SECONDS = 300;
    public const MAX_RECORDS_PER_WINDOW = 1000;

    private const STORAGE_DIR = __DIR__ . '/../../../storage/api_rate_limit';

    /** @var resource|null 同時実行ロックのファイルハンドル */
    private $lockHandle = null;

    private string $lockFile;
    private string $usageFile;

    public function __construct(string $username)
    {
        // username はURLパス由来のためファイル名にはハッシュを使う
        $key = hash('sha256', $username);

        if (!is_dir(self::STORAGE_DIR)) {
            mkdir(self::STORAGE_DIR, 0777, true);
        }

        $this->lockFile = self::STORAGE_DIR . '/' . $key . '.lock';
        $this->usageFile = self::STORAGE_DIR . '/' . $key . '.json';
    }

    /**
     * 同時実行ロックを取得する。同一ユーザーのリクエストが実行中なら false。
     */
    public function acquireLock(): bool
    {
        $fp = fopen($this->lockFile, 'c');
        if ($fp === false) {
            // ロックファイルが作れない場合はリクエストを止めない（フェイルオープン）
            return true;
        }

        if (!flock($fp, LOCK_EX | LOCK_NB)) {
            fclose($fp);
            return false;
        }

        $this->lockHandle = $fp;
        return true;
    }

    public function releaseLock(): void
    {
        if ($this->lockHandle !== null) {
            flock($this->lockHandle, LOCK_UN);
            fclose($this->lockHandle);
            $this->lockHandle = null;
        }
    }

    /**
     * 直近5分間の取得済みレコード数が上限に達しているか
     */
    public function isQuotaExceeded(): bool
    {
        return $this->getUsedRecordCount() >= self::MAX_RECORDS_PER_WINDOW;
    }

    /**
     * 直近5分間に取得済みのレコード数
     */
    public function getUsedRecordCount(): int
    {
        return array_sum(array_column($this->loadEntries(), 1));
    }

    /**
     * 上限を下回るまでの待ち時間（秒）。Retry-After ヘッダ用
     */
    public function getRetryAfterSeconds(): int
    {
        $entries = $this->loadEntries();
        $used = array_sum(array_column($entries, 1));

        foreach ($entries as [$time, $count]) {
            $used -= $count;
            if ($used < self::MAX_RECORDS_PER_WINDOW) {
                return max(1, $time + self::WINDOW_SECONDS - time());
            }
        }

        return 1;
    }

    /**
     * 取得したレコード数を履歴に追加する
     */
    public function addRecordCount(int $count): void
    {
        if ($count <= 0) {
            return;
        }

        $entries = $this->loadEntries();
        $entries[] = [time(), $count];
        file_put_contents($this->usageFile, json_encode($entries));
    }

    /**
     * 取得履歴を読み込み、ウィンドウ外（5分より前）のエントリを除いて返す
     *
     * @return array<int, array{0: int, 1: int}> [UNIXタイム, レコード数] の昇順リスト
     */
    private function loadEntries(): array
    {
        if (!file_exists($this->usageFile)) {
            return [];
        }

        $entries = json_decode((string)file_get_contents($this->usageFile), true);
        if (!is_array($entries)) {
            return [];
        }

        $threshold = time() - self::WINDOW_SECONDS;

        return array_values(array_filter(
            $entries,
            fn($entry) => is_array($entry)
                && isset($entry[0], $entry[1])
                && (int)$entry[0] > $threshold
        ));
    }
}
