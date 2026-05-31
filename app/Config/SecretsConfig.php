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

    // ------------------------------------------------------------------
    // Alpha Labs: アクセス数ランキング / 検索流入(SEO)ランキング 用
    //
    // 本家 openchat-review.me の Google Analytics 4(GA4) と
    // Search Console(GSC) から、/oc/{id} 詳細ページのアクセス・検索流入を
    // 日次バッチ(batch/exec/alpha_ga_sync.php)で部屋別に集計して保存する。
    //
    // 認証は OAuth（installed app）の refresh_token 方式（oc-pdca と同じ資格情報）。
    // 1つのトークンで GA4(analytics.readonly) と GSC(webmasters.readonly) 両方にアクセスできる。
    //
    // ▼ どこに何を置くか（すべて local-secrets.php に書く。gitignore 済）:
    //   - $ga4PropertyId          GA4 プロパティID（数字のみ。例 '373602810'）
    //   - $gscSiteUrl             Search Console のサイト（例 'sc-domain:openchat-review.me'）
    //   - $googleApiClientId      OAuth クライアントID（credentials.json の installed.client_id）
    //   - $googleApiClientSecret  OAuth クライアントシークレット（installed.client_secret）
    //   - $googleApiRefreshToken  refresh_token（token.json の refresh_token。無期限）
    //   （値は oc-pdca の google-services-config/{credentials.json,token.json} から転記）
    //
    // 揃っていなければバッチは何もせず正常終了する（isGoogleAnalyticsConfigured() 参照）。
    // ------------------------------------------------------------------
    static string $ga4PropertyId = '';
    static string $gscSiteUrl = '';
    static string $googleApiClientId = '';
    static string $googleApiClientSecret = '';
    static string $googleApiRefreshToken = '';

    /**
     * Alpha のGA/GSC同期に必要な設定が揃っているか。
     * 1つでも欠けていれば false（バッチはこの場合 何もせず exit 0 する）。
     */
    public static function isGoogleAnalyticsConfigured(): bool
    {
        return self::$ga4PropertyId !== ''
            && self::$gscSiteUrl !== ''
            && self::$googleApiClientId !== ''
            && self::$googleApiClientSecret !== ''
            && self::$googleApiRefreshToken !== '';
    }
}
