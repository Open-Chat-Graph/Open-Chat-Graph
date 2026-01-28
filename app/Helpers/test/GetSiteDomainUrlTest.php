<?php

declare(strict_types=1);

use App\Config\AppConfig;
use PHPUnit\Framework\TestCase;
use Shared\MimimalCmsConfig;

/**
 * docker compose exec app vendor/bin/phpunit app/Helpers/test/GetSiteDomainUrlTest.php
 */
class GetSiteDomainUrlTest extends TestCase
{
    public function testGetSiteDomainUrl()
    {
        // テスト用の設定
        AppConfig::$siteDomain = 'https://example.com';
        MimimalCmsConfig::$urlRoot = '';

        // テスト1: 単純なパス結合
        $result = getSiteDomainUrl('home', 'article');
        $this->assertSame('https://example.com/home/article', $result);

        // テスト2: スラッシュ付きパス（先頭スラッシュは削除される）
        $result = getSiteDomainUrl('/home', '/article');
        $this->assertSame('https://example.com/home/article', $result);

        // テスト3: スラッシュ付きパス（末尾スラッシュは保持される）
        $result = getSiteDomainUrl('home/', 'article/');
        $this->assertSame('https://example.com/home//article/', $result);

        // テスト4: 配列形式（urlRootとpathsを指定）
        $result = getSiteDomainUrl(['urlRoot' => '/en', 'paths' => ['home', 'article']]);
        $this->assertSame('https://example.com/en/home/article', $result);

        // テスト5: urlRoot設定時
        MimimalCmsConfig::$urlRoot = '/ja';
        $result = getSiteDomainUrl('home', 'article');
        $this->assertSame('https://example.com/ja/home/article', $result);

        // テスト6: 空のパス
        MimimalCmsConfig::$urlRoot = '';
        $result = getSiteDomainUrl();
        $this->assertSame('https://example.com', $result);

        // テスト7: 単一のパス
        $result = getSiteDomainUrl('home');
        $this->assertSame('https://example.com/home', $result);

        // テスト8: InvalidArgumentException（urlRootキーなし）
        $this->expectException(\InvalidArgumentException::class);
        getSiteDomainUrl(['paths' => ['home']]);
    }
}
