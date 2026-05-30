<?php

declare(strict_types=1);

namespace App\Controllers\Pages;

use App\Config\AppConfig;

class StagingIconController
{
    /**
     * アイコン画像を返す (192x192)
     * ステージング環境ならオレンジアイコン、本番環境ならグリーンアイコン
     */
    public function icon192()
    {
        if (AppConfig::$isStaging) {
            $filePath = AppConfig::ROOT_PATH . 'public/assets/icon-192x192-staging.png';
        } else {
            $filePath = AppConfig::ROOT_PATH . 'public/assets/icon-192x192.png';
        }

        $this->serveImage($filePath, 'image/png');
    }

    /**
     * faviconを返す
     * ステージング環境ならオレンジfavicon、本番環境ならグリーンfavicon
     */
    public function favicon()
    {
        if (AppConfig::$isStaging) {
            $filePath = AppConfig::ROOT_PATH . 'public/favicon-staging.ico';
        } else {
            $filePath = AppConfig::ROOT_PATH . 'public/favicon.ico';
        }

        $this->serveImage($filePath, 'image/x-icon');
    }

    /**
     * 画像ファイルを配信
     */
    private function serveImage(string $filePath, string $mimeType): void
    {
        if (!file_exists($filePath)) {
            http_response_code(404);
            exit;
        }

        header('Cache-Control: public, max-age=86400');
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . filesize($filePath));

        readfile($filePath);
        exit;
    }
}
