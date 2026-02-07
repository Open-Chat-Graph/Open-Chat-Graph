<?php

declare(strict_types=1);

namespace App\Controllers\Pages;

use App\Config\AppConfig;

class RobotsController
{
    public function index()
    {
        // Content-Typeをtext/plainに設定
        header('Content-Type: text/plain; charset=UTF-8');

        // 本番環境以外は全てのクローラーをブロック
        if (AppConfig::$isDevlopment || AppConfig::$isStaging || AppConfig::$isMockEnvironment) {
            echo $this->getDevelopmentRobotsTxt();
        } else {
            echo $this->getProductionRobotsTxt();
        }

        exit;
    }

    /**
     * 開発環境用のrobots.txt（全クローラーをブロック）
     */
    private function getDevelopmentRobotsTxt(): string
    {
        return <<<'TXT'
# 開発環境 - 全てのクローラーをブロック
User-agent: *
Disallow: /
TXT;
    }

    /**
     * 本番環境用のrobots.txt
     */
    private function getProductionRobotsTxt(): string
    {
        return <<<TXT
# Google AdSenseクローラーのみ許可
User-agent: Mediapartners-Google
Allow: /oc/*/jump
Allow: /th/oc/*/jump

User-agent: AdsBot-Google
Allow: /oc/*/jump
Allow: /th/oc/*/jump

# その他すべてのクローラー
User-agent: *
Disallow: /admin/log
Disallow: /tw/recently-registered
Disallow: /th/recently-registered
Disallow: /recently-registered
Disallow: /oc/*/jump
Disallow: /th/oc/*/jump

Sitemap: https://openchat-review.me/sitemap.xml
TXT;
    }
}
