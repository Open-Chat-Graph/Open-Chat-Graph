<?php

declare(strict_types=1);

namespace App\Exceptions\Handlers;

use App\Exceptions\TransientDatabaseException;
use App\Services\Error\DeferredTransientErrorNotifierInterface;
use ExceptionHandler\ExceptionHandler;
use Shadow\ApplicationExceptionHandlerInterface;
use Shadow\Kernel\Reception;
use Shared\MimimalCmsConfig;

class ApplicationExceptionHandler implements ApplicationExceptionHandlerInterface
{
    public static array $exceptionMap = [
        \App\Exceptions\ApplicationException::class => 'app',
        TransientDatabaseException::class => 'transient_database',
    ];

    public static function handleException(\Throwable $e, string $className)
    {
        if ($className === TransientDatabaseException::class) {
            self::handleTransientDatabase($e);
            return;
        }

        echo static::$exceptionMap[$className] . ': ';
        echo $e->getMessage();
    }

    /**
     * 一過性DB障害(TransientDatabaseException)の処理。
     *
     * この経路に来るのは Web の未 catch 例外のみ。CLI(cron/batch)は batch/cron/cron_crawling.php の
     * ように各実行体が自前で try-catch して即時 Discord 通知する（フレームワーク本体の例外ハンドラは
     * 元々 CLI/Web を区別しない設計なので、ここで PHP_SAPI を見て分岐する必要はない）。
     *
     * - 握り潰さず exception.log に必ず残す（過去に 503 経路がログ無しで握り潰していた反省）。
     * - 一時的な過負荷なので 500 ではなく 503(+Retry-After) を返す。
     * - Discord 通知はスマホが鳴り止まないのを避け「10件たまったら1通」のバッファに積む。
     */
    private static function handleTransientDatabase(\Throwable $e): void
    {
        // 握り潰さない: まず必ずログに残す
        ExceptionHandler::errorLog($e);

        // 通知は10件バッチに積む（即時には送らない）
        try {
            app(DeferredTransientErrorNotifierInterface::class)->record($e);
        } catch (\Throwable $ignore) {
            // 通知バッファの失敗はレスポンスを巻き込まない
        }

        self::renderServiceUnavailable();
    }

    /**
     * HTTP 503 (Service Unavailable) を返す（Web リクエスト時の描画のみ）。
     *
     * アクセス集中で MySQL 接続が枯渇・SQLite がロック輻輳したときなど、一時的な過負荷を 500 ではなく
     * 「混雑・後で再試行」として返すことで、クローラ(Googlebot等)の検索順位への悪影響を避ける。
     * 画面は 5xx 共通の「再読み込み」画面(app/Views/errors/error.php。$httpCode>=500 で専用表示)を
     * 流用し、Retry-After を添えて少し後の再試行を促す。
     *
     * ログ記録(exception.log)と通知は呼び出し元の handleTransientDatabase() が担う（ここは描画専用）。
     * 一過性障害の検出と TransientDatabaseException への変換は App\Models\Repositories\DB(MySQL) と
     * App\Models\SQLite\AbstractSQLite(SQLite) が行い、フレームワーク本体(shadow/・shared/MimimalCMS_*.php)
     * には手を入れない方針。
     */
    private static function renderServiceUnavailable(): void
    {
        // 途中まで出力されたバッファがあれば破棄してからエラー画面を描画する
        if (ob_get_length() !== false && ob_get_length() > 0) {
            @ob_clean();
        }

        if (!headers_sent()) {
            http_response_code(503);
            // 復帰直後の再集中(サンダリングハード)を避けるため軽いジッタを付ける
            header('Retry-After: ' . random_int(30, 90));
        }

        // API(JSON)リクエストには JSON で返す
        $isJson = (class_exists(Reception::class) && (Reception::$isJson ?? false))
            || str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json');
        if ($isJson) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode(['error' => ['code' => 503, 'message' => 'Service Unavailable']]);
            return;
        }

        // HTML: 5xx 再読み込み画面（error.php は $httpCode/$httpStatusMessage/$detailsMessage を参照）
        $viewsDir = class_exists(MimimalCmsConfig::class) ? (MimimalCmsConfig::$viewsDir ?? null) : null;
        $httpCode = 503;
        $httpStatusMessage = 'Service Unavailable';
        $detailsMessage = '';
        if ($viewsDir !== null && is_file($viewsDir . '/errors/error.php')) {
            require $viewsDir . '/errors/error.php';
        } else {
            echo '503 Service Unavailable';
        }
    }
}
