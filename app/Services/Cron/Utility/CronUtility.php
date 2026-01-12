<?php

declare(strict_types=1);

namespace App\Services\Cron\Utility;

use App\Config\AppConfig;

class CronUtility
{
    /**
     * プロセスタグ（セッション識別子）
     * 形式: [言語コード@開始時刻~PID] (例: JA@05:30~12345)
     */
    public static ?string $processTag = null;

    /**
     * Cronログを出力する
     *
     * @param string|array $log ログメッセージ
     * @param string $setProcessTag プロセスタグを設定する場合に指定
     * @param int $backtraceDepth バックトレースの深さ
     * @return string プロセスタグ
     */
    public static function addCronLog(string|array $log = '', string $setProcessTag = '', int $backtraceDepth = 1): string
    {
        // セッション識別子を1回だけ生成: [言語コード@開始時刻~PID] 形式
        if ($setProcessTag !== '' && is_null(self::$processTag)) {
            self::$processTag = $setProcessTag;
            return self::$processTag;
        } elseif ($log === '' && is_string(self::$processTag)) {
            return self::$processTag;
        } elseif (is_null(self::$processTag)) {
            $langCode = match (\Shared\MimimalCmsConfig::$urlRoot) {
                '/th' => 'TH',
                '/tw' => 'TW',
                default => 'JA',
            };
            $startTime = date('H:i');
            self::$processTag = $langCode . '@' . $startTime . '~' . getmypid();
        }

        if (is_string($log)) {
            $log = [$log];
        }

        // 呼び出し元のファイル・行番号を取得してGitHub参照を生成
        $githubRef = self::getCronLogGitHubRef($backtraceDepth);

        foreach ($log as $string) {
            error_log(
                date('Y-m-d H:i:s') . ' [' . self::$processTag . '] ' . $string . ' ' . $githubRef . "\n",
                3,
                AppConfig::getStorageFilePath('addCronLogDest')
            );
        }

        return self::$processTag;
    }

    /**
     * Verbose Cronログを出力する（AppConfig::$verboseCronLogがtrueの場合のみ）
     *
     * @param string|array $log ログメッセージ
     */
    public static function addVerboseCronLog(string|array $log): void
    {
        if (AppConfig::$verboseCronLog) {
            self::addCronLog($log, '', 2);
        }
    }

    /**
     * Cronログ用のGitHub参照文字列を生成する
     *
     * backtraceの構造:
     * - trace[0]: getCronLogGitHubRefを呼び出した場所（addCronLog内）
     * - trace[1]: addCronLogを呼び出した場所（目的の行）
     * - trace[2]: その上位の呼び出し元
     *
     * @param int $backtraceDepth 1=直接の呼び出し元, 2=さらに上位の呼び出し元
     * @return string GitHub::path/to/file.php:123 形式の文字列
     */
    public static function getCronLogGitHubRef(int $backtraceDepth = 1): string
    {
        // backtraceDepth + 1 フレームを取得（0から始まるため+1が必要）
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $backtraceDepth + 2);
        $caller = $trace[$backtraceDepth] ?? $trace[0] ?? null;

        if (!$caller || !isset($caller['file'], $caller['line'])) {
            return '';
        }

        // プロジェクトルートからの相対パスを取得
        $projectRoot = dirname(__DIR__, 4) . '/';
        $relativePath = str_replace($projectRoot, '', $caller['file']);

        return 'GitHub::' . $relativePath . ':' . $caller['line'];
    }

    /**
     * プロセスを終了させる
     *
     * @param int $pid 終了させるプロセスのPID
     * @param int $maxWaitSeconds SIGTERMでの最大待機時間（秒）
     * @throws \RuntimeException プロセスを終了できなかった場合
     */
    public static function killProcess(int $pid, int $maxWaitSeconds = 3): void
    {
        if (posix_getpgid($pid) === false) {
            return;
        }

        // SIGTERM送信
        posix_kill($pid, 15);

        // プロセスが終了するまで待機
        for ($i = 0; $i < $maxWaitSeconds; $i++) {
            sleep(1);
            if (posix_getpgid($pid) === false) {
                return;
            }
        }

        // SIGKILLで強制終了
        self::addCronLog("[警告] プロセス (PID: {$pid}) がSIGTERMで終了しないため、SIGKILL送信");
        posix_kill($pid, 9);
        sleep(1);

        // それでも終了しない場合は例外
        if (posix_getpgid($pid) !== false) {
            throw new \RuntimeException("プロセス (PID: {$pid}) をSIGKILLでも終了できませんでした");
        }
    }
}
