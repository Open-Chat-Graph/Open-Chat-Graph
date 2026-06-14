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
    case isUpdateRecommendStaticDataActive = 'isUpdateRecommendStaticDataActive';
    case isUpdateOcPageCacheActive = 'isUpdateOcPageCacheActive';
    case isRecommendTagRebuildActive = 'isRecommendTagRebuildActive';
    case recommendTagRebuildStartedAt = 'recommendTagRebuildStartedAt';
    case recommendTagsJsonHash = 'recommendTagsJsonHash';
    case rankingPersistenceBackground = 'rankingPersistenceBackground';
    case ocreviewApiDataImportBackground = 'ocreviewApiDataImportBackground';
    case persistMemberStatsLastDate = 'persistMemberStatsLastDate';
    case filterCacheDate = 'filterCacheDate';
}
