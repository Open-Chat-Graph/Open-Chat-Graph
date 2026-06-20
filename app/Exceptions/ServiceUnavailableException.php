<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * サーバが一時的にリクエストを処理できないときに投げる例外（HTTP 503）。
 *
 * 主な用途: MySQL のユーザー単位同時接続上限（max_user_connections）への瞬間的な
 * 張り付きや、サーバ瞬断による接続枯渇。これを 500 ではなく 503 + Retry-After として
 * 返すことで、クローラには「混雑中・後で再試行」を正しく伝え（SEO 降格を避け）、
 * ブラウザには再読み込み画面（app/Views/errors/error.php の 5xx 表示）を出す。
 *
 * 接続枯渇の元例外（\PDOException）は $previous として連結する。これにより
 * DB::isConnectionException() が getPrevious をたどって接続障害と判定でき、
 * cron 等の既存リトライ判定の挙動を保てる。
 *
 * 検出と本例外への変換は App\Models\Repositories\DB が行い、503 の描画は
 * app/Exceptions/Handlers/ApplicationExceptionHandler（$exceptionMap 経由）が担う。
 * フレームワーク本体（shadow/・shared/MimimalCMS_*.php）には手を入れない。
 */
class ServiceUnavailableException extends \RuntimeException
{
}
