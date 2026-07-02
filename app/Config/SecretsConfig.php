<?php

namespace App\Config;

class SecretsConfig
{
    static string $adminApiKey = '';
    static string $discordWebhookUrl = '';
    static string $googleRecaptchaSecretKey = '';
    static string $googleRecaptchaSiteKey = '';
    static string $cloudFlareZoneId = '';
    static string $cloudFlareApiKey = '';
    static string $yahooClientId = '';
    static string $stagingBasicAuthUser = '';
    static string $stagingBasicAuthPassword = '';

    /**
     * TikTok 動画レンダリング用 GitHub repository_dispatch の fine-grained PAT
     * （AppConfig::TIKTOK_VIDEO_DISPATCH_REPO への Contents: Read and write のみ）。
     * 未設定('')ならディスパッチは無効（GitHubVideoDispatcher が何もしない＝ローカル/stg/mock は自然に無効）。
     */
    static string $gitHubVideoDispatchToken = '';
}
