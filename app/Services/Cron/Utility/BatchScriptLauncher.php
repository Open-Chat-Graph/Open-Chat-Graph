<?php

declare(strict_types=1);

namespace App\Services\Cron\Utility;

use App\Config\AppConfig;
use App\Exceptions\TransientDatabaseException;
use App\Models\Repositories\DB;
use App\Services\Admin\AdminTool;
use App\Services\Cron\Enum\BatchScript;
use App\Services\Error\DeferredTransientErrorNotifierInterface;
use ExceptionHandler\ExceptionHandler;

/**
 * バッチスクリプト(batch/)の起動・実行を一元化するランチャー。
 *
 * - パス解決は BatchScript enum（AppConfig::ROOT_PATH 起点）に集約
 * - PHPバイナリは CLI なら PHP_BINARY、Web(FPM) なら AppConfig::$phpBinary
 *   （FPM の PHP_BINARY は php-fpm を指すため CLI 実行には使えない）
 * - 引数は全て escapeshellarg で安全化して渡す
 * - run(): バッチ本体の実行＋CLI共通エラー処理（各エントリscriptの try/catch 重複を排除）
 */
class BatchScriptLauncher
{
    /** CLI(バッチ)で一過性DB障害が出たときに処理全体を再試行する最大試行回数（初回を含む） */
    private const TRANSIENT_MAX_ATTEMPTS = 4;
    /** 全体再試行の前に待つ秒数（旧 SyncOpenChat::HOURLY_RETRY_INTERVAL_SEC 相当） */
    private const TRANSIENT_RETRY_INTERVAL_SEC = 60;

    /**
     * バッチ処理本体を実行し、CLI(バッチ)共通のエラー処理を一元化する。
     *
     * 各 batch エントリスクリプトに重複していた「try/catch で errorLog + cronログ + Discord 通知」を
     * ここへ集約する。run() が全例外(バグ含む)を内部で処理しきるので、呼び出し側は自前の try/catch を
     * 持たず run() に処理を渡すだけでよい（例外は呼び出し元へ伝播しない）。
     *
     * 一過性DB障害（接続枯渇・瞬断・SQLiteロック＝TransientDatabaseException / 接続障害）は、
     * 少し待って**処理全体を再試行**する（旧 SyncOpenChat::hourlyTaskWithRetry の丸ごと再試行を
     * ランチャに一般化し、毎時だけでなく page-cache 等の各バックグラウンドプロセスにも効かせる。
     * バッチは冪等前提）。再試行で救えなければ ログ＋即時通知する。CLI のみ再試行（Web からは
     * run() を呼ばない。Web 経路の 503/10件バッチ通知は ApplicationExceptionHandler が別途担当し、
     * ここ(CLI)とは構造的に分離する＝PHP_SAPI で出し分けない）。
     *
     * @param callable $task            実行する処理本体
     * @param ?callable $suppressNotify 例外を受け取り true を返すと通知を抑制する（期待された
     *                                  中断など。抑制時に必要なログはコールバック側で出す）
     */
    public function run(callable $task, ?callable $suppressNotify = null): void
    {
        try {
            $maxAttempts = (PHP_SAPI === 'cli') ? self::TRANSIENT_MAX_ATTEMPTS : 1;

            for ($attempt = 1; ; $attempt++) {
                try {
                    $task();
                    return;
                } catch (\Throwable $e) {
                    if ($attempt < $maxAttempts && self::isTransientDbFailure($e)) {
                        $this->onTransientRetry($e, $attempt, $maxAttempts);
                        continue;
                    }

                    if ($suppressNotify !== null && $suppressNotify($e)) {
                        return;
                    }
                    $this->report($e);
                    return;
                }
            }
        } catch (\Throwable $fatal) {
            // report()/suppressNotify()/onTransientRetry() 自体が投げても run() は呼び出し元へ伝播しない
            // （バッチエントリは run() に渡すだけ＝自前 try/catch を持たない前提の契約を守る）。
            try {
                ExceptionHandler::errorLog($fatal);
            } catch (\Throwable $ignore) {
            }
        } finally {
            $this->flushDeferredTransientErrors();
        }
    }

    /** 一過性DB障害（接続枯渇・瞬断・SQLiteロック）か。$previous を辿る判定も含む。 */
    private static function isTransientDbFailure(\Throwable $e): bool
    {
        return $e instanceof TransientDatabaseException || DB::isConnectionException($e);
    }

    /** 全体再試行の直前処理（cronログ＋待機）。テストで差し替えられるよう protected。 */
    protected function onTransientRetry(\Throwable $e, int $attempt, int $maxAttempts): void
    {
        CronUtility::addCronLog(sprintf(
            '[警告] 一過性DB障害で中断。%d秒後に処理全体を再試行します（%d/%d回目）: %s',
            self::TRANSIENT_RETRY_INTERVAL_SEC,
            $attempt,
            $maxAttempts - 1,
            $e->getMessage(),
        ));
        sleep(self::TRANSIENT_RETRY_INTERVAL_SEC);
    }

    /** 最終的に救えなかった例外の記録＋通知。テストで差し替えられるよう protected。 */
    protected function report(\Throwable $e): void
    {
        ExceptionHandler::errorLog($e);
        CronUtility::addCronLog($e->__toString());
        AdminTool::sendDiscordNotify($e->__toString());
    }

    /**
     * Web リクエストで溜まった一過性DBエラーの端数(10件未満)を掃き出す。
     * どのバッチでも完了時に1回掃ければ、10件たまらないまま長時間放置されるのを防げる。
     * 通知系の失敗はバッチ本処理に波及させない。テストで差し替えられるよう protected。
     */
    protected function flushDeferredTransientErrors(): void
    {
        try {
            app(DeferredTransientErrorNotifierInterface::class)->flush();
        } catch (\Throwable $ignore) {
        }
    }

    /**
     * バックグラウンドで起動する（出力破棄・完了を待たない）
     */
    public function launchInBackground(BatchScript $script, string ...$args): void
    {
        exec($this->buildCommand($script, $args) . ' >/dev/null 2>&1 &');
    }

    /**
     * 同期実行する（完了まで待つ）
     */
    public function launchSync(BatchScript $script, string ...$args): void
    {
        exec($this->buildCommand($script, $args));
    }

    /**
     * 自分以外の同名スクリプトのプロセスをkillする（多重実行の解消用）
     *
     * @return string ログ用のkill結果サマリー
     */
    public function killOtherInstances(BatchScript $script): string
    {
        $myPid = getmypid();
        $cmd = "ps aux | grep {$script->basename()} | grep -v grep | grep -v '{$myPid}' | awk '{print \$2}' | xargs -r kill";
        exec($cmd, $output, $returnCode);

        return implode(' ', $output) . " (return code: {$returnCode})";
    }

    /**
     * 自分以外に同名スクリプトのプロセスが動いているかを返す（プロセス死活判定用）。
     *
     * killOtherInstances と同じ条件（同名スクリプト・自PID除外）で対象を数えるだけの読み取り版。
     * ロックの所有プロセスが本当に生きているかの確認に使う。
     */
    public function isAnyInstanceRunning(BatchScript $script): bool
    {
        $myPid = getmypid();
        $cmd = "ps aux | grep {$script->basename()} | grep -v grep | grep -v '{$myPid}' | awk '{print \$2}'";
        exec($cmd, $output);

        foreach ($output as $line) {
            if (trim($line) !== '') {
                return true;
            }
        }

        return false;
    }

    private function buildCommand(BatchScript $script, array $args): string
    {
        $phpBinary = PHP_SAPI === 'cli' ? PHP_BINARY : AppConfig::$phpBinary;
        $escapedArgs = array_map('escapeshellarg', $args);

        return rtrim($phpBinary . ' ' . $script->absolutePath() . ' ' . implode(' ', $escapedArgs));
    }
}
