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
 * 上限より小さい値を何行も返させて、その後の JSON 化で PHP を落とす・巨大な応答を返させることも
 * できるので、結果は fetchRows で 1 リクエスト合計 AppConfig::USER_SQL_MAX_RESULT_BYTES までに抑える。
 *
 * Web の PHP は FastCGI で、プロセスが次のリクエストにも使い回されるため、終わったら必ず元に戻す。
 * 致命的エラーで止まった場合は例外ハンドラの通知の前と shutdown で戻し、それでも残ったら
 * 次のリクエストの最初（shared/bootstrap.php の restoreLeftover）で戻す。
 * posix 拡張か /proc が無い環境では何もしない。
 */
final class UserSqlMemoryLimit
{
    private static bool $active = false;

    private static bool $ran = false;

    private static bool $shutdownRegistered = false;

    /**
     * @template T
     * @param \Closure(): T $fn
     * @return T
     */
    public static function run(\Closure $fn): mixed
    {
        self::$ran = true;
        $lowered = self::lower();
        try {
            return $fn();
        } finally {
            if ($lowered) {
                self::restore();
            }
        }
    }

    /**
     * 結果を最大 $maxRows 行取り出す。値の大きさは $bytes に足していき、1 リクエストの合計が
     * AppConfig::USER_SQL_MAX_RESULT_BYTES を超えたら例外（呼び出し側でエラーの応答にする）。
     *
     * @param int $bytes このリクエストでこれまでに取り出した値の合計（一括リクエストでは同じ変数を渡し続ける）
     * @return array<int, array<string, mixed>>
     */
    public static function fetchRows(\PDOStatement $result, int $maxRows, int &$bytes): array
    {
        $rows = [];
        while (count($rows) < $maxRows && ($row = $result->fetch(\PDO::FETCH_ASSOC)) !== false) {
            foreach ($row as $value) {
                $bytes += is_string($value) ? strlen($value) : 8;
            }
            if ($bytes > AppConfig::USER_SQL_MAX_RESULT_BYTES) {
                throw new \RuntimeException(sprintf(
                    'Result too large: up to %d MB per request in total. Select fewer columns or rows (use substr() for long text).',
                    AppConfig::USER_SQL_MAX_RESULT_BYTES / 1024 / 1024
                ));
            }
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * このリクエストで利用者の SQL を実行したか。実行時間の上限もこのときに掛けるので、以降の致命的エラー
     * （メモリ・実行時間の上限）は利用者の SQL が原因とみなす（クエリの合間に時間切れになることもある）
     */
    public static function hasRun(): bool
    {
        return self::$ran;
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

    /** @return bool このとき上限を下げたか（既に下げている入れ子の呼び出しでは false） */
    private static function lower(): bool
    {
        $limit = self::getLimit();
        if (self::$active || $limit === null || $limit[0] !== $limit[1]) {
            return false;
        }

        // フレームワークのエラーハンドラは @ を付けても警告を例外にするので、読めるか先に確かめる
        if (!is_readable('/proc/self/status') || !preg_match('/^VmSize:\s+(\d+) kB$/m', (string)file_get_contents('/proc/self/status'), $m)) {
            return false;
        }

        $soft = ((int)$m[1] * 1024 + AppConfig::USER_SQL_MEMORY_HEADROOM_BYTES) | 1;
        if ($limit[1] !== POSIX_RLIMIT_INFINITY && $limit[1] <= $soft) {
            return false; // もともと十分低い
        }

        if (!self::$shutdownRegistered) {
            register_shutdown_function(self::restore(...));
            self::$shutdownRegistered = true;
        }

        self::$active = posix_setrlimit(POSIX_RLIMIT_AS, $soft, $limit[1]);
        return self::$active;
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
