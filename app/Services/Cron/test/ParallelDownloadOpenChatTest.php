<?php

use App\Services\Cron\ParallelDownloadOpenChat;
use App\Services\OpenChat\OpenChatApiDbMergerWithParallelDownloader;
use PHPUnit\Framework\TestCase;

class ParallelDownloadOpenChatTest extends TestCase
{
    public function test()
    {
        set_time_limit(3600 * 10);

        /**
         * @var ParallelDownloadOpenChat $inst
         */
        $inst = app(ParallelDownloadOpenChat::class);

        try {
            OpenChatApiDbMergerWithParallelDownloader::setKillFlagFalse();
            $inst->handle([['type' => 'rising', 'category' => 6], ['type' => 'ranking', 'category' => 6]]);
        } catch (\Throwable $e) {
            // 全てのダウンロードプロセスを強制終了する
            OpenChatApiDbMergerWithParallelDownloader::setKillFlagTrue();
            addCronLog($e->__toString());
            $this->assertTrue(false);
        }

        $this->assertTrue(true);
    }
}
