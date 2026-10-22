<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Config\AppConfig;

/**
 * 利用者が書いた SQL（/database API・公開 MCP）を実行する間だけ、プロセスの仮想メモリの上限
 * （RLIMIT_AS）を「いまの使用量 + AppConfig::USER_SQL_MEMORY_HEADROOM_BYTES」まで下げる。
 *
 * SQLite のメモリは PHP の memory_limit の外で確保されるため、printf の桁指定・|| の入れ子・
 * 直積の group_concat などで、短いクエリから 1GB 級の値を作れてしまう（キーワードでは塞ぎきれない）。
 * 上限を超える確保は失敗し、SQLite は「out of memory」の例外を返す。
 *
 * Web の PHP は FastCGI で、プロセスが次のリクエストにも使い回されるため、終わったら必ず元に戻す。
 * 致命的エラーで止まった場合は例外ハンドラの通知の前と shutdown で戻し、それでも残ったら
 * 次のリクエストの最初（shared/bootstrap.php の restoreLeftover）で戻す。
 * posix 拡張か /proc が無い環境では何もしない。
 */
final class UserSqlMemoryLimit
{
    private static bool $active = false;

    private static bool $shutdownRegistered = false;

    /**
     * @template T
     * @param \Closure(): T $fn
     * @return T
     */
    public static function run(\Closure $fn): mixed
    {
        self::lower();
        try {
            return $fn();
        } finally {
            self::restore();
        }
    }

    /** 上限を下げている最中か（致命的エラーが利用者の SQL によるものかの判定に使う） */
    public static function isActive(): bool
    {
        return self::$active;
    }

    public static function restore(): void
    {
        if (!self::$active) {
            return;
        }

        self::$active = false;
        self::restoreLeftover();
    }

    /**
     * このクラスが下げた上限が残っていれば元に戻す。下げるのは soft と hard が同じときだけで、
     * 下げた値は奇数にしてある（ページ単位の普通の設定値と見分ける目印）ので、soft を hard に揃えれば元どおり。
     */
    public static function restoreLeftover(): void
    {
        $limit = self::getLimit();
        if ($limit !== null && $limit[0] !== $limit[1] && $limit[0] % 2 === 1) {
            posix_setrlimit(POSIX_RLIMIT_AS, $limit[1], $limit[1]);
        }
    }

    private static function lower(): void
    {
        $limit = self::getLimit();
        if (self::$active || $limit === null || $limit[0] !== $limit[1]) {
            return;
        }

        $status = @file_get_contents('/proc/self/status');
        if ($status === false || !preg_match('/^VmSize:\s+(\d+) kB$/m', $status, $m)) {
            return;
        }

        $soft = ((int)$m[1] * 1024 + AppConfig::USER_SQL_MEMORY_HEADROOM_BYTES) | 1;
        if ($limit[1] !== POSIX_RLIMIT_INFINITY && $limit[1] <= $soft) {
            return; // もともと十分低い
        }

        if (!self::$shutdownRegistered) {
            register_shutdown_function(self::restore(...));
            self::$shutdownRegistered = true;
        }

        self::$active = posix_setrlimit(POSIX_RLIMIT_AS, $soft, $limit[1]);
    }

    /** @return array{0: int, 1: int}|null [soft, hard]。unlimited は POSIX_RLIMIT_INFINITY */
    private static function getLimit(): ?array
    {
        if (!function_exists('posix_getrlimit') || ($limit = posix_getrlimit(POSIX_RLIMIT_AS)) === false) {
            return null;
        }

        return array_map(fn($v) => $v === 'unlimited' ? POSIX_RLIMIT_INFINITY : (int)$v, [$limit[0], $limit[1]]);
    }
}
