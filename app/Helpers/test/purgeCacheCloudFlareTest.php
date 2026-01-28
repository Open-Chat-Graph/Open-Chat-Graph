<?php

declare(strict_types=1);

use App\Config\AppConfig;
use PHPUnit\Framework\TestCase;

/**
 * docker compose exec app vendor/bin/phpunit app/Helpers/test/purgeCacheCloudFlareTest.php
 */
class purgeCacheCloudFlareTest extends TestCase
{
    public function testPurgeCacheCloudFlare()
    {
        AppConfig::$isDevlopment = false;
        AppConfig::$isStaging = false;
        AppConfig::$enableCloudflare = true;

        // テスト用の値を設定してください
        $zoneID = null;  // または実際のzoneID文字列
        $apiKey = null;  // または実際のAPIキー文字列
        $files = [
            'https://openchat-review.me/recent-comment-api'
        ];   // または ['https://example.com/file1.jpg', 'https://example.com/file2.css']
        $prefixes = null; // または ['https://example.com/prefix1/', 'https://example.com/prefix2/']

        $result = purgeCacheCloudFlare($zoneID, $apiKey, $files, $prefixes);

        debug($result);

        // 結果が文字列であることを確認
        $this->assertIsString($result);
    }

    public function testPurgeCacheCloudFlareFailure()
    {
        AppConfig::$isDevlopment = false;
        AppConfig::$isStaging = false;
        AppConfig::$enableCloudflare = true;

        // 不正なAPIキーとゾーンIDで失敗させる
        $zoneID = 'invalid_zone_id';
        $apiKey = 'invalid_api_key';
        $files = [
            'https://openchat-review.me/recent-comment-api'
        ];

        // RuntimeExceptionが投げられることを確認
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/CDNキャッシュ削除失敗/');

        try {
            purgeCacheCloudFlare($zoneID, $apiKey, $files, null);
        } catch (\RuntimeException $e) {
            debug($e->getMessage());
            throw $e;
        }
    }
}
