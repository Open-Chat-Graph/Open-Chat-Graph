<?php

declare(strict_types=1);

namespace App\Services\OpenChat;

use App\Config\AppConfig;
use App\Exceptions\ApplicationException;
use App\Models\Repositories\SyncOpenChatStateRepositoryInterface;
use App\Services\Cron\Enum\SyncOpenChatStateType;
use Shared\MimimalCmsConfig;

/**
 * デイリークローリング並列実行クラス
 *
 * OpenChatDailyCrawlingの並列版。複数のプロセスを同時に起動し、
 * クローリング処理を高速化する。
 */
class OpenChatDailyCrawlingParallel
{
    // killフラグのチェック間隔（秒）
    const CHECK_KILL_FLAG_INTERVAL = 5;

    function __construct(
        private SyncOpenChatStateRepositoryInterface $syncOpenChatStateRepository,
    ) {}

    /**
     * 並列クローリング実行
     *
     * @param int[] $openChatIdArray クローリング対象のID配列
     * @return int 処理した件数
     * @throws \RuntimeException
     */
    function crawling(array $openChatIdArray): int
    {
        $this->setKillFlagFalse();

        if (empty($openChatIdArray)) {
            return 0;
        }

        // 並列数を取得
        $parallelCount = AppConfig::DAILY_CRAWLING_PARALLEL_PROCESS_COUNT[MimimalCmsConfig::$urlRoot] ?? 3;

        // IDを分割
        $chunks = array_chunk($openChatIdArray, (int)ceil(count($openChatIdArray) / $parallelCount));

        addCronLog("OpenChatDailyCrawlingParallel: Start {$parallelCount} parallel processes");

        // 各プロセスを起動
        $processes = [];
        foreach ($chunks as $index => $chunk) {
            $processes[$index] = $this->startChildProcess($chunk, $index);
        }

        // プロセス完了待ち
        $this->waitForProcesses($processes);

        addCronLog("OpenChatDailyCrawlingParallel: All processes completed");

        return count($openChatIdArray);
    }

    /**
     * 子プロセスを起動
     *
     * @param int[] $chunk 処理対象のID配列
     * @param int $index プロセスインデックス
     * @return array プロセス情報
     */
    private function startChildProcess(array $chunk, int $index): array
    {
        $cmd = sprintf(
            '%s %s/batch/exec/daily_crawling_child.php %s %s %d',
            AppConfig::$phpBinary,
            AppConfig::ROOT_PATH,
            MimimalCmsConfig::$urlRoot,
            base64_encode(serialize($chunk)),
            $index
        );

        $descriptorspec = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w']   // stderr
        ];

        $process = proc_open($cmd, $descriptorspec, $pipes);

        if (!is_resource($process)) {
            throw new \RuntimeException("Failed to start child process {$index}");
        }

        // stdin不要なので閉じる
        fclose($pipes[0]);

        return [
            'process' => $process,
            'pipes' => $pipes,
            'index' => $index,
            'chunk_size' => count($chunk),
        ];
    }

    /**
     * 全プロセスの完了を待つ
     *
     * @param array $processes プロセス情報の配列
     * @throws ApplicationException killフラグが立った場合
     * @throws \RuntimeException プロセスがエラー終了した場合
     */
    private function waitForProcesses(array $processes): void
    {
        $activeProcesses = $processes;
        $lastCheck = time();

        while (!empty($activeProcesses)) {
            // killフラグチェック（一定間隔）
            if (time() - $lastCheck >= self::CHECK_KILL_FLAG_INTERVAL) {
                $this->checkKillFlag();
                $lastCheck = time();
            }

            // 各プロセスの状態をチェック
            foreach ($activeProcesses as $key => $processInfo) {
                $status = proc_get_status($processInfo['process']);

                // プロセスが終了していたら
                if (!$status['running']) {
                    $this->handleProcessCompletion($processInfo, $status);
                    unset($activeProcesses[$key]);
                }
            }

            // CPU負荷軽減のため少し待機
            usleep(100000); // 100ms
        }
    }

    /**
     * プロセス完了時の処理
     *
     * @param array $processInfo プロセス情報
     * @param array $status プロセスステータス
     * @throws \RuntimeException プロセスがエラー終了した場合
     */
    private function handleProcessCompletion(array $processInfo, array $status): void
    {
        $index = $processInfo['index'];
        $exitCode = $status['exitcode'];

        // stdout/stderrを読み取る
        $stdout = stream_get_contents($processInfo['pipes'][1]);
        $stderr = stream_get_contents($processInfo['pipes'][2]);

        // パイプとプロセスを閉じる
        fclose($processInfo['pipes'][1]);
        fclose($processInfo['pipes'][2]);
        proc_close($processInfo['process']);

        // エラーチェック
        if ($exitCode !== 0) {
            $errorMessage = "Child process {$index} failed with exit code {$exitCode}\n";
            if ($stderr) {
                $errorMessage .= "STDERR: {$stderr}\n";
            }
            throw new \RuntimeException($errorMessage);
        }

        addCronLog("Child process {$index} completed successfully");
    }

    /**
     * killフラグチェック
     *
     * @throws ApplicationException killフラグが立っている場合
     */
    private function checkKillFlag(): void
    {
        if ($this->syncOpenChatStateRepository->getBool(SyncOpenChatStateType::openChatDailyCrawlingKillFlag)) {
            // 全子プロセスを終了させる処理は親プロセスで実施済みと想定
            throw new ApplicationException(
                'OpenChatDailyCrawlingParallel: 強制終了しました',
                AppConfig::DAILY_UPDATE_EXCEPTION_ERROR_CODE
            );
        }
    }

    /**
     * killフラグをfalseに設定
     */
    private function setKillFlagFalse(): void
    {
        $this->syncOpenChatStateRepository->setFalse(SyncOpenChatStateType::openChatDailyCrawlingKillFlag);
    }

    /**
     * killフラグをtrueに設定（静的メソッド）
     */
    static function setKillFlagTrue(): void
    {
        /** @var SyncOpenChatStateRepositoryInterface $syncOpenChatStateRepository */
        $syncOpenChatStateRepository = app(SyncOpenChatStateRepositoryInterface::class);
        $syncOpenChatStateRepository->setTrue(SyncOpenChatStateType::openChatDailyCrawlingKillFlag);
    }
}
