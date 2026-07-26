<?php

declare(strict_types=1);

namespace App\Services\Ads;

use App\Config\SecretsConfig;

/**
 * 広告オプトアウト（合言葉を知っている人だけ広告が出なくなる）のサーバ側ロジック。
 *
 * ## なぜこの作りなのか
 *
 * サイトのページは Cloudflare の Cache Everything でエッジキャッシュされる（オリジンの nginx も
 * 全キャッシュ）。つまり **サーバが返す HTML は全訪問者で完全に同一** でなければならず、
 * 「このリクエストのクッキーを見て広告を出し分ける」というサーバ側の分岐は原理的に使えない。
 * また `/admin-check` のような非同期のサーバ問い合わせでは、返事が来る前に広告スクリプトが
 * 走ってしまうので間に合わない。
 *
 * そこで判定は **同期のクライアント JS** で行う。ただし素朴にやるとページのソースに
 * 「正解のクッキー値」が載ってしまい誰でも真似できるので、次の一方向構造にしている:
 *
 * ```
 *   合言葉 --HMAC(secret)--> トークン        … クッキーに入れる値（サーバでしか作れない）
 *   トークン --sha256-->      ページ埋め込みハッシュ … JS が突き合わせる値（逆算できない）
 * ```
 *
 * ページを読んでも sha256 ハッシュしか手に入らず、そこからトークンは復元できない。
 * `$adOptOutSecret`（ランダムな鍵）を噛ませているのが要で、これが無いと本リポジトリが
 * オープンソースである以上「合言葉の候補を総当たりして埋め込みハッシュと突き合わせる」
 * 辞書攻撃でトークンが復元できてしまう。
 *
 * ## 限界（承知の上）
 *
 * ブラウザの開発者ツールで JS を読んで判定分岐を潰せば誰でも広告を消せる。これは既存の
 * アドブロッカーを入れるのと同じ手間で、防ぎようがない（クライアントで判定する以上は必然）。
 * 一方「合言葉を知らない人がクッキーを偽造する」は HMAC のおかげで現実的に不可能であり、
 * 求めている「外からは普通に破れない」は満たしている。
 */
class AdOptOutService
{
    /** 導出に使うラベル（用途ごとに鍵を分離するため。変更すると発行済みクッキーが無効になる） */
    private const TOKEN_LABEL = 'ocg-ad-opt-out|v1';
    private const COOKIE_LABEL = 'ocg-ad-opt-out-cookie|v1';

    /** クッキーの有効期間（秒）: 1年 */
    public const COOKIE_LIFETIME = 3600 * 24 * 365;

    /**
     * 機能が使える状態か（合言葉と鍵の両方が secrets に入っているか）
     *
     * 未設定の環境（開発用のクリーンな環境など）では、この機能に関する出力を一切行わない。
     */
    public static function isConfigured(): bool
    {
        return SecretsConfig::$adOptOutPassphrase !== '' && SecretsConfig::$adOptOutSecret !== '';
    }

    /**
     * クッキー名。秘密鍵から決定的に導出する（環境ごとに別の名前になる）。
     *
     * 固定の分かりやすい名前にしないのは、ページのソースやリポジトリを見ただけでは
     * 「何という名前のクッキーを探せばいいか」すら分からないようにするため。
     */
    public static function cookieName(): string
    {
        return '_' . substr(hash_hmac('sha256', self::COOKIE_LABEL, SecretsConfig::$adOptOutSecret), 0, 12);
    }

    /**
     * クッキーに入れるトークン（合言葉から決定的に導出。サーバの鍵が無いと作れない）
     */
    public static function token(): string
    {
        return hash_hmac(
            'sha256',
            self::TOKEN_LABEL . '|' . SecretsConfig::$adOptOutPassphrase,
            SecretsConfig::$adOptOutSecret
        );
    }

    /**
     * ページに埋め込む照合用ハッシュ（トークンの sha256。ここからトークンは逆算できない）
     */
    public static function pageHash(): string
    {
        return hash('sha256', self::token());
    }

    /**
     * 入力された合言葉が正しいか（タイミング攻撃を避けるため hash_equals で比較）
     */
    public static function verifyPassphrase(string $input): bool
    {
        if (!self::isConfigured()) {
            return false;
        }

        return hash_equals(SecretsConfig::$adOptOutPassphrase, $input);
    }

    /**
     * このリクエストのクッキーが有効なオプトアウト状態か（設定ページの状態表示用）
     *
     * ※ 実際の広告停止はクライアント JS が行う（CDN キャッシュのためサーバ側では分岐できない）。
     *    このメソッドはキャッシュしないページ（/admin/disable-ads）でのみ使う。
     */
    public static function isOptedOut(): bool
    {
        if (!self::isConfigured()) {
            return false;
        }

        $cookie = $_COOKIE[self::cookieName()] ?? null;
        if (!is_string($cookie) || $cookie === '') {
            return false;
        }

        return hash_equals(self::token(), $cookie);
    }

    /**
     * オプトアウトのクッキーを発行する
     *
     * JS から読めないと判定できないので httpOnly は必ず false。
     */
    public static function issueCookie(): void
    {
        cookie(
            [self::cookieName() => self::token()],
            time() + self::COOKIE_LIFETIME,
            samesite: 'Lax',
            httpOnly: false
        );
    }

    /**
     * オプトアウトのクッキーを削除する（広告を再表示する）
     */
    public static function clearCookie(): void
    {
        cookie()->remove(self::cookieName());
    }
}
