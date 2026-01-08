<?php

/**
 * DailyUpdateCronServiceの動作確認テスト
 *
 * テスト実行コマンド:
 * docker compose exec app vendor/bin/phpunit app/Services/test/DailyUpdateCronServiceTest.php
 *
 */

declare(strict_types=1);

use App\Config\AppConfig;
use App\Services\DailyUpdateCronService;
use PHPUnit\Framework\TestCase;

class DailyUpdateCronServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // 開発環境とverboseログを有効化
        AppConfig::$isDevlopment = true;
        AppConfig::$verboseCronLog = true;

        echo "\n=== DailyUpdateCronService テスト開始 ===\n";
        echo "AppConfig::\$isDevlopment = " . (AppConfig::$isDevlopment ? 'true' : 'false') . "\n";
        echo "AppConfig::\$verboseCronLog = " . (AppConfig::$verboseCronLog ? 'true' : 'false') . "\n";
        echo "開発環境更新制限: " . (AppConfig::$developmentEnvUpdateLimit['DailyUpdateCronService'] ?? 10) . "件\n";
    }

    /**
     * テスト1: update()メソッドの実行確認
     */
    public function testUpdate(): void
    {
        echo "\n=== testUpdate: DailyUpdateCronService::update()実行 ===\n";

        $service = app(DailyUpdateCronService::class);

        // updateメソッドを実行
        $service->update();

        echo "テスト完了: update()が正常に実行されました\n";

        // テストが正常に完了したことを確認
        $this->assertTrue(true);
    }

    /**
     * テスト2: getCachedMemberChangeIdArray()の動作確認
     */
    public function testGetCachedMemberChangeIdArray(): void
    {
        echo "\n=== testGetCachedMemberChangeIdArray: キャッシュ確認 ===\n";

        $service = app(DailyUpdateCronService::class);

        // まずgetTargetOpenChatIdArrayを呼び出してキャッシュを設定
        $service->getTargetOpenChatIdArray();

        // キャッシュされたデータを取得
        $cachedArray = $service->getCachedMemberChangeIdArray();

        echo "キャッシュされたID数: " . count($cachedArray ?? []) . "件\n";

        // キャッシュが設定されていることを確認
        $this->assertIsArray($cachedArray);

        echo "テスト完了: キャッシュが正しく保存されています\n";
    }
}
