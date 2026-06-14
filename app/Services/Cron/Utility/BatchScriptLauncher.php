<?php

declare(strict_types=1);

namespace App\Services\Cron\Utility;

use App\Config\AppConfig;
use App\Services\Cron\Enum\BatchScript;

/**
 * バッチスクリプト(batch/)の起動を一元化するランチャー。
 *
 * - パス解決は BatchScript enum（AppConfig::ROOT_PATH 起点）に集約
 * - PHPバイナリは CLI なら PHP_BINARY、Web(FPM) なら AppConfig::$phpBinary
 *   （FPM の PHP_BINARY は php-fpm を指すため CLI 実行には使えない）
 * - 引数は全て escapeshellarg で安全化して渡す
 */
class BatchScriptLauncher
{
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
