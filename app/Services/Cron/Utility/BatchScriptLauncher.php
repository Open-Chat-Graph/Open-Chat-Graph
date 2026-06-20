<?php

declare(strict_types=1);

namespace App\Services\Cron\Utility;

use App\Config\AppConfig;
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
    /**
     * バッチ処理本体を実行し、CLI(バッチ)共通のエラー処理を一元化する。
     *
     * 各 batch エントリスクリプトに重複していた「try/catch で errorLog + cronログ + Discord 通知」を
     * ここへ集約する。run() が全例外(バグ含む)を内部で処理しきるので、呼び出し側は自前の try/catch を
     * 持たず run() に処理を渡すだけでよい（例外は呼び出し元へ伝播しない）。
     *
     * 一過性DB障害(TransientDatabaseException)も CLI では他の例外と同じく即時通知する
     * （Web のような10件バッチ化はしない＝従来どおり）。Web 経路の 503/バッチ通知は
     * App\Exceptions\Handlers\ApplicationExceptionHandler::handleTransientDatabase が別途担当し、
     * ここ(CLI)とは構造的に分離する（PHP_SAPI で分岐しない）。
     *
     * @param callable $task            実行する処理本体
     * @param ?callable $suppressNotify 例外を受け取り true を返すと通知を抑制する（期待された
     *                                  中断など。抑制時に必要なログはコールバック側で出す）
     */
    public function run(callable $task, ?callable $suppressNotify = null): void
    {
        try {
            $task();
        } catch (\Throwable $e) {
            if ($suppressNotify !== null && $suppressNotify($e)) {
                return;
            }
            ExceptionHandler::errorLog($e);
            CronUtility::addCronLog($e->__toString());
            AdminTool::sendDiscordNotify($e->__toString());
        } finally {
            $this->flushDeferredTransientErrors();
        }
    }

    /**
     * Web リクエストで溜まった一過性DBエラーの端数(10件未満)を掃き出す。
     * どのバッチでも完了時に1回掃ければ、10件たまらないまま長時間放置されるのを防げる。
     * 通知系の失敗はバッチ本処理に波及させない。
     */
    private function flushDeferredTransientErrors(): void
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
