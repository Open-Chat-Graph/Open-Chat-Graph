<?php

namespace App\Config;

class SecretsConfig
{
    static string $adminApiKey = '';
    static string $lineNotifyToken = '';  // TODO: デプロイ後削除
    static string $discordWebhookUrl = '';
    static string $googleRecaptchaSecretKey = '';
    static string $cloudFlareZoneId = '';
    static string $cloudFlareApiKey = '';
    static string $yahooClientId = '';
}
