<?php

namespace App\Config;

class AdminConfig
{
    const IS_DEVELOPMENT = true;
    const DEVELOPMENT_ENV_UPDATE_LIMIT = [
        'OpenChatImageUpdater' => 10,
        'OpenChatHourlyInvitationTicketUpdater' => 10,
        'DailyUpdateCronService' => 10,
        'RankingBanTableUpdater' => 10,
    ];

    const ADMIN_API_KEY = '';
    const LINE_NOTIFY_TOKEN = '';
    const GOOGLE_RECAPTCHA_SECRET_KEY = '';
    const CloudFlareZoneID = '';
    const CloudFlareApiKey = '';
    const YahooClientID = '';
}
