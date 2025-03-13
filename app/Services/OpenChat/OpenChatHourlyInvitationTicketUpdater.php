<?php

declare(strict_types=1);

namespace App\Services\OpenChat;

use App\Config\AppConfig;
use App\Exceptions\InvalidMemberCountException;
use App\Models\Repositories\Log\LogRepositoryInterface;
use App\Models\Repositories\UpdateOpenChatRepositoryInterface;
use App\Services\OpenChat\Crawler\OpenChatApiFromEmidDownloader;
use App\Models\Repositories\DB;

class OpenChatHourlyInvitationTicketUpdater
{
    function __construct(
        private OpenChatApiFromEmidDownloader $openChatApiFromEmidDownloader,
        private UpdateOpenChatRepositoryInterface $updateOpenChatRepository,
        private LogRepositoryInterface $logRepository,
    ) {}

    function updateInvitationTicketAll()
    {
        $ocArray = $this->updateOpenChatRepository->getEmptyUrlOpenChatId();

        // 開発環境の場合、更新制限をかける
        if (AppConfig::$isDevlopment ?? false) {
            $limit = AppConfig::$developmentEnvUpdateLimit['OpenChatHourlyInvitationTicketUpdater'] ?? 1;
            $ocArrayCount = count($ocArray);
            $ocArray = array_slice($ocArray, 0, $limit);
            addCronLog("Development environment. Update limit: {$limit} / {$ocArrayCount}");
        }

        foreach ($ocArray as $oc) {
            $this->updateInvitationTicket($oc['id'], $oc['emid']);
        }
    }

    function updateInvitationTicket(int $open_chat_id, string $emid): bool
    {
        try {
            $dto = $this->openChatApiFromEmidDownloader->fetchOpenChatDto($emid);
            if (!$dto) return false;
        } catch (\RuntimeException | InvalidMemberCountException $e) {
            $this->logRepository->logUpdateOpenChatError($open_chat_id, $e->getMessage());
            return false;
        }

        $this->updateOpenChatRepository->updateUrl($open_chat_id, $dto->invitationTicket);
        return true;
    }
}
