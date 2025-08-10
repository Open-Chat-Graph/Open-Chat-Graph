<?php

declare(strict_types=1);

namespace App\Services\RankingBan;

use App\Services\Admin\AdminTool;

class ProgressNotifier
{
    const NOTIFY_MIN_COUNT = 1000;

    private int $totalCount = 0;
    private int $processedCount = 0;
    private ?\DateTime $startTime = null;
    private ?\DateTime $lastNotifyTime = null;
    private int $notifyIntervalSeconds = 300; // 5分

    public function setTotalCount(int $totalCount): void
    {
        $this->totalCount = $totalCount;
        $this->processedCount = 0;
        $this->startTime = new \DateTime();
        $this->lastNotifyTime = null;
    }

    public function notifyStart(string $processName): void
    {
        if ($this->totalCount <= self::NOTIFY_MIN_COUNT) {
            return;
        }

        if (!$this->startTime) {
            return;
        }

        AdminTool::sendDiscordNotify(
            "{$processName} 開始: {$this->totalCount}件の処理を開始します"
        );

        $this->lastNotifyTime = new \DateTime();
    }

    public function incrementAndNotify(string $processName): void
    {
        $this->processedCount++;

        if ($this->totalCount <= self::NOTIFY_MIN_COUNT || !$this->startTime) {
            return;
        }

        $now = new \DateTime();

        // 最後の処理の場合
        if ($this->processedCount >= $this->totalCount) {
            $duration = $now->diff($this->startTime);
            AdminTool::sendDiscordNotify(
                "{$processName} 完了: {$this->processedCount}/{$this->totalCount}件 (処理時間: {$duration->format('%H:%I:%S')})"
            );
            return;
        }

        // 5分経過した場合
        if ($this->lastNotifyTime && $now->getTimestamp() - $this->lastNotifyTime->getTimestamp() >= $this->notifyIntervalSeconds) {
            AdminTool::sendDiscordNotify(
                "{$processName} 進捗: {$this->processedCount}/{$this->totalCount}件"
            );
            $this->lastNotifyTime = $now;
        }
    }
}
