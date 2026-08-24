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
 *
 * ## X（旧Twitter）プロフィール用の通用口（/x）
 *
 * 上とは別に、X のプロフィール欄に置く URL（`/x`）を踏んだ人へ **そのセッション限りの**
 * 広告オフを配る経路がある。合言葉は要らず、URL を踏むだけで通る（公開リンクなので当然）。
 *
 * 合言葉側と **クッキー名もトークンも完全に分けて** ある。理由は2つ:
 *
 *  1. X 経路だけを失効させたいとき、`X_TOKEN_LABEL` の版番号を上げるだけで済む
 *     （`$adOptOutSecret` を回すと合言葉ユーザーのクッキーまで巻き添えで消える）。
 *  2. 同じクッキー名にすると、合言葉で入れた永続クッキーが自分の X リンクを踏んだ瞬間
 *     セッションクッキーに上書きされてしまう。
 *
 * UA からは「X から来たか」を判定できない（iOS の SFSafariViewController は UA が Safari と同一、
 * Android にも X 固有トークンは無い）。代わりに **Referer を必須条件**にしている。X のリンクは必ず
 * t.co を経由し、t.co は実ブラウザに HTML＋JS リダイレクトを返す＝ t.co がドキュメントとして
 * 読み込まれるので、転送先には `Referer: https://t.co/...` が付く。よって Referer の無いリクエストは
 * X 由来ではない（ブックマーク・直打ち・コピペ）と見なして配らない。
 *
 * クッキーは 3 時間で切れる。期限なしのセッションクッキーは Chromium のタブ復元で生き残り、
 * 「そのセッションだけ」が実態として無期限になってしまうため。
 */
class AdOptOutService
{
    /** 導出に使うラベル（用途ごとに鍵を分離するため。変更すると発行済みクッキーが無効になる） */
    private const TOKEN_LABEL = 'ocg-ad-opt-out|v1';
    private const COOKIE_LABEL = 'ocg-ad-opt-out-cookie|v1';

    /**
     * X プロフィールの通用口（/x）用のラベル。合言葉側とは別トークン・別クッキー名になる。
     *
     * ※ 末尾の版番号を上げると、**X 経由で配ったクッキーだけ**が一括で無効になる（緊急停止用）。
     *    合言葉ユーザーのクッキーには影響しない。
     */
    private const X_TOKEN_LABEL = 'ocg-ad-opt-out-x|v1';
    private const X_COOKIE_LABEL = 'ocg-ad-opt-out-x-cookie|v1';

    /** /x を踏んだ人の転送先（GA4 で流入を数えるため utm を付ける） */
    public const X_ENTRY_REDIRECT = '?utm_source=x&utm_medium=profile&utm_campaign=ad_free';

    /** /x でクッキーを配ってよい Referer のホスト（サブドメインも許可） */
    private const X_REFERER_HOSTS = ['t.co', 'x.com', 'twitter.com'];

    /** Android の Custom Tabs が付ける Referer（X アプリから開いた場合） */
    private const X_REFERER_ANDROID_APPS = ['android-app://com.twitter.android'];

    /** クッキーの有効期間（秒）: 1年 */
    public const COOKIE_LIFETIME = 3600 * 24 * 365;

    /**
     * X 通用口で配るクッキーの有効期間（秒）: 3時間
     *
     * 期限なしのセッションクッキーにはしない。Chromium は「前回開いていたタブを復元」や
     * アップデート再起動のときに **セッションクッキーごと復元** するため、「ブラウザを閉じたら消える」
     * が実態として成立せず、いつまでも広告オフのままになりかねないため。明示的に切る。
     */
    public const X_COOKIE_LIFETIME = 3600 * 3;

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

    /**
     * X 通用口（/x）用のクッキー名。合言葉側とは別名になる。
     */
    public static function xCookieName(): string
    {
        return '_' . substr(hash_hmac('sha256', self::X_COOKIE_LABEL, SecretsConfig::$adOptOutSecret), 0, 12);
    }

    /**
     * X 通用口用のトークン（合言葉は噛ませない。URL を踏めば通るのが仕様）
     *
     * 合言葉側と違い入力が無いので、鍵とラベルだけから導出する。ページに出るのはこの sha256
     * だけなので、リポジトリからラベルが読めてもトークンは作れない（鍵が要る）。
     */
    public static function xToken(): string
    {
        return hash_hmac('sha256', self::X_TOKEN_LABEL, SecretsConfig::$adOptOutSecret);
    }

    /**
     * X 通用口トークンの照合用ハッシュ（ページに埋め込む値）
     */
    public static function xPageHash(): string
    {
        return hash('sha256', self::xToken());
    }

    /**
     * X 通用口のクッキーを発行する（3時間で切れる）
     *
     * セッションクッキー（expires 0）にすると Chromium のタブ復元で生き残ってしまい、
     * 「そのセッションだけ」が実態として無期限になる。明示的に有効期限を切る。
     * 判定はクライアント JS なので httpOnly は必ず false（合言葉側と同じ）。
     */
    public static function issueXCookie(): void
    {
        cookie(
            [self::xCookieName() => self::xToken()],
            time() + self::X_COOKIE_LIFETIME,
            samesite: 'Lax',
            httpOnly: false
        );
    }

    /**
     * この Referer に対して X 通用口のクッキーを配ってよいか（**X 由来の Referer 必須**）
     *
     * X のリンクは必ず t.co を経由し、t.co は **実ブラウザに HTTP 200 の HTML＋JS
     * (`location.replace`) を返す**（bot にだけ 301）。つまり t.co が本物のドキュメントとして
     * 読み込まれ、その次の遷移に `Referer: https://t.co/...` が付く。X アプリのアプリ内ブラウザも
     * UA は普通のブラウザなので同じ経路をたどる。したがって **Referer が空のリクエストは
     * X から来ていない**（ブックマーク・URL の直打ち・コピペ）と判断してよい。
     *
     * - t.co / x.com / twitter.com とそのサブドメイン … 配る
     * - `android-app://com.twitter.android`（Android の Custom Tabs） … 配る
     * - 自サイト … 配る（サイト内から辿った場合と、動作確認のため）
     * - 空・それ以外の外部サイト（ブックマーク／直打ち／転載） … 配らない
     *
     * 万一 Referer が落ちる端末があっても、その人には広告が出るだけ（安全側に倒れる）。
     *
     * @param ?string $referer リクエストの Referer ヘッダ
     * @param ?string $selfHost 自サイトのホスト名（`$_SERVER['HTTP_HOST']` 相当）
     */
    public static function isAllowedXEntryReferer(?string $referer, ?string $selfHost = null): bool
    {
        $referer = trim((string) $referer);
        if ($referer === '') {
            return false;
        }

        $lower = strtolower($referer);
        foreach (self::X_REFERER_ANDROID_APPS as $app) {
            if (str_starts_with($lower, $app)) {
                return true;
            }
        }

        $host = strtolower((string) parse_url($referer, PHP_URL_HOST));
        if ($host === '') {
            return false;
        }

        $allowed = self::X_REFERER_HOSTS;
        // 自サイトのホスト（ポート番号が付く場合があるので落とす）
        $self = strtolower(explode(':', (string) $selfHost)[0]);
        if ($self !== '') {
            $allowed[] = $self;
        }

        foreach ($allowed as $a) {
            if ($host === $a || str_ends_with($host, '.' . $a)) {
                return true;
            }
        }

        return false;
    }
}
