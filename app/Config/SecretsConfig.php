<?php

namespace App\Config;

class SecretsConfig
{
    static string $adminApiKey = '';
    static string $discordWebhookUrl = '';
    static string $googleRecaptchaSecretKey = '';
    static string $cloudFlareZoneId = '';
    static string $cloudFlareApiKey = '';
    static string $yahooClientId = '';

    // もう使われていない
    static string $apiDbUser = '';
    static string $apiDbPassword = '';
}
