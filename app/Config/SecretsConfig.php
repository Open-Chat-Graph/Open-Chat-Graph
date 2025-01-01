<?php

namespace App\Config;

class SecretsConfig
{
    static bool $isDevlopment = false;

    /** @var array<string, int> */
    static array $developmentEnvUpdateLimit = [
        'OpenChatImageUpdater' => 10,
        'OpenChatHourlyInvitationTicketUpdater' => 10,
        'DailyUpdateCronService' => 10,
        'RankingBanTableUpdater' => 10,
    ];

    static string $adminApiKey = '';
    static string $lineNotifyToken = '';
    static string $googleRecaptchaSecretKey = '';
    static string $cloudFlareZoneId = '';
    static string $cloudFlareApiKey = '';
    static string $yahooClientId = '';
}