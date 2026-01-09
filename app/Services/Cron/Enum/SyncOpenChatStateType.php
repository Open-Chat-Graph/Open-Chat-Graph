<?php

declare(strict_types=1);

namespace App\Services\Cron\Enum;

enum SyncOpenChatStateType: string
{
    case isDailyTaskActive = 'isDailyTaskActive';
    case isHourlyTaskActive = 'isHourlyTaskActive';
    case openChatApiDbMergerKillFlag = 'openChatApiDbMergerKillFlag';
    case openChatDailyCrawlingKillFlag = 'openChatDailyCrawlingKillFlag';
    case isUpdateInvitationTicketActive = 'isUpdateInvitationTicketActive';
    case persistMemberStatsLastDate = 'persistMemberStatsLastDate';
    case filterCacheDate = 'filterCacheDate';
}
