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
     * 広告オプトアウト（/admin/disable-ads）の合言葉。
     *
     * この合言葉を知っている人だけが「自分のブラウザだけ広告を出さない」状態にできる。
     * 合言葉そのものはクッキーにもページのJSにも一切出力しない（サーバ内で照合するだけ）。
     * 空文字のあいだは機能全体が無効（ページも 404 になる）。
     */
    static string $adOptOutPassphrase = '';

    /**
     * 広告オプトアウトのトークン導出鍵（ランダムな長い文字列・環境ごとに別の値）。
     *
     * クッキーに入れるトークンは hash_hmac('sha256', $adOptOutPassphrase, $adOptOutSecret) で導出し、
     * ページには sha256(トークン) だけを埋め込む（一方向）。この鍵が無いと、埋め込みハッシュから
     * 合言葉を総当たりで当てることもトークンを偽造することもできない。
     *
     * ※ この鍵を変えると発行済みのクッキーは全て無効になる（＝緊急時の一括失効に使える）。
     */
    static string $adOptOutSecret = '';
}
