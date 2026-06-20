<?php

declare(strict_types=1);

namespace App\Exceptions\Handlers;

use App\Exceptions\ServiceUnavailableException;
use Shadow\ApplicationExceptionHandlerInterface;
use Shadow\Kernel\Reception;
use Shared\MimimalCmsConfig;

class ApplicationExceptionHandler implements ApplicationExceptionHandlerInterface
{
    public static array $exceptionMap = [
        \App\Exceptions\ApplicationException::class => 'app',
        ServiceUnavailableException::class => 'service_unavailable',
    ];

    public static function handleException(\Throwable $e, string $className)
    {
        if ($className === ServiceUnavailableException::class) {
            self::renderServiceUnavailable();
            return;
        }

        echo static::$exceptionMap[$className] . ': ';
        echo $e->getMessage();
    }

    /**
     * HTTP 503 (Service Unavailable) を返す。
     *
     * アクセス集中で MySQL 接続が枯渇したときなど、一時的な過負荷を 500 ではなく
     * 「混雑・後で再試行」として返すことで、クローラ(Googlebot等)の検索順位への悪影響を避ける。
     * 画面は 5xx 共通の「再読み込み」画面(app/Views/errors/error.php。$httpCode>=500 で専用表示)を
     * 流用し、Retry-After を添えて少し後の再試行を促す。一過性のため例外ログには残さない。
     *
     * 接続枯渇の検出と本例外への変換は App\Models\Repositories\DB が行い、フレームワーク本体
     * (shadow/・shared/MimimalCMS_*.php) には手を入れない方針。
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
