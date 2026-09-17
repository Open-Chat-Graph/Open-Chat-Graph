<?php

/**
 * テスト実行コマンド:
 * docker compose exec app vendor/bin/phpunit app/Services/Ads/test/AdOptOutServiceTest.php
 */

declare(strict_types=1);

use App\Config\SecretsConfig;
use App\Services\Ads\AdOptOutService;
use PHPUnit\Framework\TestCase;

class AdOptOutServiceTest extends TestCase
{
    private string $passphrase;
    private string $secret;

    protected function setUp(): void
    {
        // 実環境の secrets を退避して、テスト用の固定値に差し替える
        $this->passphrase = SecretsConfig::$adOptOutPassphrase;
        $this->secret = SecretsConfig::$adOptOutSecret;

        SecretsConfig::$adOptOutPassphrase = 'test-passphrase';
        SecretsConfig::$adOptOutSecret = 'test-secret-0123456789';
    }

    protected function tearDown(): void
    {
        SecretsConfig::$adOptOutPassphrase = $this->passphrase;
        SecretsConfig::$adOptOutSecret = $this->secret;
    }

    /**
     * X 通用口は合言葉側と完全に別のクッキー名・別のトークンになる
     * （同じだと、X リンクを踏んだ瞬間に合言葉の永続クッキーがセッションクッキーで上書きされる）
     */
    public function testXCredentialsAreSeparatedFromPassphraseOnes(): void
    {
        $this->assertNotSame(AdOptOutService::cookieName(), AdOptOutService::xCookieName());
        $this->assertNotSame(AdOptOutService::token(), AdOptOutService::xToken());
        $this->assertNotSame(AdOptOutService::pageHash(), AdOptOutService::xPageHash());
    }

    /**
     * ページに埋め込むのはトークンの sha256（逆算できない）
     */
    public function testXPageHashIsSha256OfToken(): void
    {
        $this->assertSame(hash('sha256', AdOptOutService::xToken()), AdOptOutService::xPageHash());
        $this->assertSame(64, strlen(AdOptOutService::xToken()));
    }

    /**
     * 鍵を変えると X 側のトークンもクッキー名も変わる（＝鍵の回転で一括失効できる）
     */
    public function testXTokenDependsOnSecret(): void
    {
        $token = AdOptOutService::xToken();
        $cookieName = AdOptOutService::xCookieName();

        SecretsConfig::$adOptOutSecret = 'another-secret-9876543210';

        $this->assertNotSame($token, AdOptOutService::xToken());
        $this->assertNotSame($cookieName, AdOptOutService::xCookieName());
    }

    /**
     * 合言葉を変えても X 側は影響を受けない（X トークンは合言葉に依存しない）
     */
    public function testXTokenIsIndependentOfPassphrase(): void
    {
        $token = AdOptOutService::xToken();

        SecretsConfig::$adOptOutPassphrase = 'changed-passphrase';

        $this->assertSame($token, AdOptOutService::xToken());
    }

    /**
     * クッキーは 3 時間で切れる（セッションクッキーだと Chromium のタブ復元で生き残るため）
     */
    public function testXCookieLifetimeIsThreeHours(): void
    {
        $this->assertSame(3600 * 3, AdOptOutService::X_COOKIE_LIFETIME);
    }

    /**
     * 転送先には GA4 で流入を数えるための utm が付く
     */
    public function testXEntryRedirectHasUtm(): void
    {
        $this->assertStringStartsWith('?', AdOptOutService::X_ENTRY_REDIRECT);
        $this->assertStringContainsString('utm_source=x', AdOptOutService::X_ENTRY_REDIRECT);
    }
}
