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
     * Referer フィルタ: X 由来（t.co・x.com・Androidアプリ）は必ず通す
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('allowedRefererProvider')]
    public function testAllowedReferers(?string $referer): void
    {
        $this->assertTrue(AdOptOutService::isAllowedXEntryReferer($referer, 'openchat-review.me'));
    }

    public static function allowedRefererProvider(): array
    {
        return [
            't.co' => ['https://t.co/AbCdEfG'],
            'x.com' => ['https://x.com/openchat_graph'],
            'twitter.com' => ['https://twitter.com/openchat_graph'],
            'mobile.twitter.com' => ['https://mobile.twitter.com/openchat_graph'],
            'Android の X アプリ' => ['android-app://com.twitter.android'],
            'lit.link（プロフィールのリンク集）' => ['https://lit.link/openchatgraph'],
            'lit.link のサブドメイン' => ['https://www.lit.link/openchatgraph'],
            '自サイト' => ['https://openchat-review.me/ranking'],
            '自サイト（ポート付き）' => ['https://openchat-review.me:8443/ranking'],
        ];
    }

    /**
     * Referer フィルタ: Referer が無い（ブックマーク・直打ち・コピペ）と X 以外の外部サイトには配らない
     *
     * X のリンクは必ず t.co を経由し、t.co は実ブラウザに HTML＋JS リダイレクトを返す＝ t.co が
     * ドキュメントになるので Referer が付く。よって Referer 無し＝X 由来ではない。
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('deniedRefererProvider')]
    public function testDeniedReferers(?string $referer): void
    {
        $this->assertFalse(AdOptOutService::isAllowedXEntryReferer($referer, 'openchat-review.me'));
    }

    public static function deniedRefererProvider(): array
    {
        return [
            'Referer なし（ブックマーク・直打ち）' => [null],
            'Referer 空文字' => [''],
            'よそのサイト' => ['https://example.com/matome'],
            '検索エンジン' => ['https://www.google.com/'],
            'ホストを詐称した紛らわしいドメイン' => ['https://x.com.evil.example/'],
            'サブドメインに見せかけた文字列' => ['https://notx.com/'],
            'lit.link に似せた別ドメイン' => ['https://lit.link.evil.example/'],
            'ホストの無いURL' => ['not-a-url'],
        ];
    }

    /**
     * 自サイトのホストが分からない場合でも X 系は通り、他所は弾く
     */
    public function testRefererFilterWithoutSelfHost(): void
    {
        $this->assertTrue(AdOptOutService::isAllowedXEntryReferer('https://t.co/abc', null));
        $this->assertFalse(AdOptOutService::isAllowedXEntryReferer('https://openchat-review.me/', null));
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
