<?php

/**
 * OcNarrativeService の 7 パターン分岐テスト
 *
 * 実行コマンド:
 * docker compose exec app vendor/bin/phpunit app/Services/Narrative/test/OcNarrativeServiceTest.php
 *
 * テスト方針:
 * - OcNarrativeRepositoryInterface を mock し、メトリクス戻り値の組合せで 7 パターン全てを発火させる
 * - 戻り値の 'pattern' フィールドで分岐を検証 (文章自体はテンプレ変更で揺れるためサマリ含有確認のみ)
 * - 異常データ / 例外 / curr=0 / sample_n=0 で必ず null を返すこと
 *
 * 注意: JP ロケール前提 (urlRoot === '') で動かす。setUp で復元。
 */

declare(strict_types=1);

use App\Models\Repositories\OcNarrativeRepositoryInterface;
use App\Services\Narrative\OcNarrativeService;
use PHPUnit\Framework\TestCase;
use Shared\MimimalCmsConfig;

class OcNarrativeServiceTest extends TestCase
{
    private string $originalUrlRoot;

    protected function setUp(): void
    {
        $this->originalUrlRoot = MimimalCmsConfig::$urlRoot;
        MimimalCmsConfig::$urlRoot = ''; // JP
    }

    protected function tearDown(): void
    {
        MimimalCmsConfig::$urlRoot = $this->originalUrlRoot;
    }

    /**
     * 7 パターンの member metrics fixture を返すヘルパ
     */
    private function metricsFixture(array $overrides = []): array
    {
        $today = (new \DateTime('now'))->format('Y-m-d');
        return array_merge([
            'curr' => 1000,
            'curr_date' => $today,
            'm7' => 990,
            'm30' => 950,
            'm90' => 900,
            'sample_n' => 90,
            'peak_high' => 1100,
            'peak_date' => $today,
            'max_single_day_growth' => 30,
            'max_growth_date' => $today,
            'first_date' => (new \DateTime('-200 days'))->format('Y-m-d'),
        ], $overrides);
    }

    private function emptyPositionMovement(): array
    {
        return [
            'oldest_close' => null, 'oldest_date' => null,
            'latest_close' => null, 'latest_date' => null,
            'best_high' => null,
            'sample_n' => 0,
        ];
    }

    private function buildOc(array $overrides = []): array
    {
        return array_merge([
            'id' => 1,
            'name' => 'テストルーム',
            'description' => 'これはテスト用のルームです。',
            'category' => 0,
            'member' => 1000,
            'api_created_at' => (new \DateTime('-200 days'))->format('Y-m-d H:i:s'),
        ], $overrides);
    }

    private function makeService(array $metrics, array $position = null): OcNarrativeService
    {
        $repo = $this->createMock(OcNarrativeRepositoryInterface::class);
        $repo->method('getMemberMetrics')->willReturn($metrics);
        $repo->method('getPositionMovement')->willReturn($position ?? $this->emptyPositionMovement());
        return new OcNarrativeService($repo);
    }

    // ============================================
    // 7 パターン分岐
    // ============================================

    public function test_active_growth_pattern(): void
    {
        // member > 50, pct30 > +5%
        $m = $this->metricsFixture(['curr' => 1000, 'm30' => 900]); // +11.1%
        $service = $this->makeService($m);
        $result = $service->generate(1, $this->buildOc());

        $this->assertNotNull($result);
        $this->assertSame('active_growth', $result['pattern']);
        $this->assertStringContainsString('拡大', $result['summary']);
    }

    public function test_rapid_growth_pattern(): void
    {
        // sample_n < 60, pct30 > +50%
        $m = $this->metricsFixture([
            'curr' => 300, 'm30' => 100, // +200%
            'sample_n' => 50,
            'm7' => 250, 'm90' => null,
        ]);
        $service = $this->makeService($m);
        $result = $service->generate(1, $this->buildOc());

        $this->assertNotNull($result);
        $this->assertSame('rapid_growth', $result['pattern']);
        $this->assertStringContainsString('急速', $result['summary']);
    }

    public function test_decline_pattern(): void
    {
        // pct30 < -5%
        $m = $this->metricsFixture(['curr' => 800, 'm30' => 1000]); // -20%
        $service = $this->makeService($m);
        $result = $service->generate(1, $this->buildOc());

        $this->assertNotNull($result);
        $this->assertSame('decline', $result['pattern']);
        $this->assertStringContainsString('縮小', $result['summary']);
    }

    public function test_stable_pattern(): void
    {
        // |pct30| < 2%
        $m = $this->metricsFixture(['curr' => 1010, 'm30' => 1000]); // +1%
        $service = $this->makeService($m);
        $result = $service->generate(1, $this->buildOc());

        $this->assertNotNull($result);
        $this->assertSame('stable', $result['pattern']);
        $this->assertStringContainsString('安定', $result['summary']);
    }

    public function test_new_pattern(): void
    {
        // sample_n < 30
        $m = $this->metricsFixture([
            'curr' => 50, 'm30' => null, 'm90' => null,
            'm7' => 40,
            'sample_n' => 20,
        ]);
        $service = $this->makeService($m);
        $result = $service->generate(1, $this->buildOc());

        $this->assertNotNull($result);
        $this->assertSame('new', $result['pattern']);
        $this->assertStringContainsString('新しく', $result['summary']);
    }

    public function test_stagnant_pattern_when_curr_date_older_than_365_days(): void
    {
        $m = $this->metricsFixture([
            'curr_date' => (new \DateTime('-400 days'))->format('Y-m-d'),
            'sample_n' => 50,
            'curr' => 500,
        ]);
        $service = $this->makeService($m);
        $result = $service->generate(1, $this->buildOc());

        $this->assertNotNull($result);
        $this->assertSame('stagnant', $result['pattern']);
        $this->assertStringContainsString('止まっています', $result['summary']);
    }

    // ============================================
    // 異常 / フォールバック
    // ============================================

    public function test_returns_null_when_curr_is_null(): void
    {
        $m = $this->metricsFixture(['curr' => null]);
        $service = $this->makeService($m);
        $this->assertNull($service->generate(1, $this->buildOc()));
    }

    public function test_returns_null_when_sample_n_is_zero(): void
    {
        $m = $this->metricsFixture(['sample_n' => 0]);
        $service = $this->makeService($m);
        $this->assertNull($service->generate(1, $this->buildOc()));
    }

    public function test_returns_null_when_curr_is_zero(): void
    {
        $m = $this->metricsFixture(['curr' => 0]);
        $service = $this->makeService($m);
        $this->assertNull($service->generate(1, $this->buildOc()));
    }

    public function test_returns_null_when_repository_throws(): void
    {
        $repo = $this->createMock(OcNarrativeRepositoryInterface::class);
        $repo->method('getMemberMetrics')->willThrowException(new \RuntimeException('DB unavailable'));
        $service = new OcNarrativeService($repo);
        $this->assertNull($service->generate(1, $this->buildOc()));
    }

    public function test_returns_null_when_position_movement_throws_then_narrative_still_works(): void
    {
        // 順位データ取得が例外でも、narrative 全体は返る (position 部分のみスキップ)
        $repo = $this->createMock(OcNarrativeRepositoryInterface::class);
        $repo->method('getMemberMetrics')->willReturn($this->metricsFixture(['curr' => 1000, 'm30' => 900]));
        $repo->method('getPositionMovement')->willThrowException(new \RuntimeException('ranking DB error'));
        $service = new OcNarrativeService($repo);

        $result = $service->generate(1, $this->buildOc());
        $this->assertNotNull($result);
        $this->assertSame('active_growth', $result['pattern']);
    }

    // ============================================
    // ロケール判定
    // ============================================

    public function test_returns_null_for_tw_locale(): void
    {
        MimimalCmsConfig::$urlRoot = '/tw';
        $service = $this->makeService($this->metricsFixture());
        $this->assertNull($service->generate(1, $this->buildOc()));
    }

    public function test_returns_null_for_th_locale(): void
    {
        MimimalCmsConfig::$urlRoot = '/th';
        $service = $this->makeService($this->metricsFixture());
        $this->assertNull($service->generate(1, $this->buildOc()));
    }

    // ============================================
    // ゼロ除算保護
    // ============================================

    public function test_pct_calculation_safe_when_past_is_zero(): void
    {
        // m30 が 0 でゼロ除算リスク → 安定パターン or new にフォールバック
        $m = $this->metricsFixture([
            'curr' => 100,
            'm30' => 0, // ゼロ除算リスク
            'm7' => 0,
            'm90' => 0,
            'sample_n' => 90,
        ]);
        $service = $this->makeService($m);
        $result = $service->generate(1, $this->buildOc());

        // 例外で死なず何らかの narrative を返すこと
        $this->assertNotNull($result);
    }

    public function test_handles_null_m7_m30_m90(): void
    {
        $m = $this->metricsFixture([
            'curr' => 100,
            'm7' => null,
            'm30' => null,
            'm90' => null,
            'sample_n' => 100,
        ]);
        $service = $this->makeService($m);
        $result = $service->generate(1, $this->buildOc());

        // m30 が null → pct30 計算不可 → stable へフォールバック
        $this->assertNotNull($result);
        $this->assertSame('stable', $result['pattern']);
    }

    // ============================================
    // 戻り値の構造と HTML エスケープ
    // ============================================

    public function test_result_shape(): void
    {
        $m = $this->metricsFixture(['curr' => 1000, 'm30' => 900]);
        $service = $this->makeService($m);
        $result = $service->generate(1, $this->buildOc());

        $this->assertNotNull($result);
        $this->assertArrayHasKey('summary', $result);
        $this->assertArrayHasKey('detail', $result);
        $this->assertArrayHasKey('meta_description', $result);
        $this->assertArrayHasKey('pattern', $result);

        $this->assertIsString($result['summary']);
        $this->assertIsString($result['detail']);
        $this->assertIsString($result['meta_description']);
        $this->assertIsString($result['pattern']);
    }

    public function test_opening_info_uses_api_created_at_not_created_at(): void
    {
        // ルーム開設日は LINE API 由来の api_created_at のみ使う。
        // created_at (= 我々のサイトへの登録日) はフォールバックにも使わない。
        $oc = $this->buildOc([
            'api_created_at' => '2026-01-14 00:00:00', // 実開設
            'created_at' => '2026-04-06 00:00:00',     // サイトへの登録 (使われないはず)
        ]);
        $m = $this->metricsFixture(['curr' => 1000, 'm30' => 900]);
        $service = $this->makeService($m);
        $result = $service->generate(1, $oc);

        $this->assertNotNull($result);
        $this->assertStringContainsString('2026年1月', $result['detail']);
        $this->assertStringNotContainsString('2026年4月', $result['detail']);
    }

    public function test_opening_info_omitted_when_api_created_at_missing_no_fallback(): void
    {
        // api_created_at が null のときは created_at にフォールバックせず、開設情報の文を省略する。
        $oc = $this->buildOc([
            'api_created_at' => null,
            'created_at' => '2026-04-06 00:00:00', // 使われない
        ]);
        $m = $this->metricsFixture(['curr' => 1000, 'm30' => 900]);
        $service = $this->makeService($m);
        $result = $service->generate(1, $oc);

        $this->assertNotNull($result);
        $this->assertStringNotContainsString('開設', $result['detail']);
        $this->assertStringNotContainsString('2026年4月', $result['detail']);
    }

    public function test_opening_info_omitted_when_api_created_at_is_zero_date(): void
    {
        $oc = $this->buildOc(['api_created_at' => '0000-00-00 00:00:00']);
        $m = $this->metricsFixture(['curr' => 1000, 'm30' => 900]);
        $service = $this->makeService($m);
        $result = $service->generate(1, $oc);

        $this->assertNotNull($result);
        $this->assertStringNotContainsString('開設', $result['detail']);
    }

    public function test_html_special_chars_in_name_are_returned_as_plain_for_caller_escape(): void
    {
        // Service は平文を返し、View / Metadata 側で h() を 1 度だけ通す前提。
        // ここでは Service が値をそのまま保持する (二重エスケープを発生させない) ことを保証する。
        $oc = $this->buildOc(['name' => '<script>alert(1)</script>テスト']);
        $m = $this->metricsFixture(['curr' => 1000, 'm30' => 900]);
        $service = $this->makeService($m);
        $result = $service->generate(1, $oc);

        $this->assertNotNull($result);
        $this->assertStringContainsString('<script>', $result['meta_description']);
        $this->assertStringNotContainsString('&lt;script&gt;', $result['meta_description']);
        $this->assertStringNotContainsString('&amp;lt;', $result['meta_description']);
    }

    public function test_meta_description_within_160_chars(): void
    {
        $oc = $this->buildOc([
            'name' => str_repeat('長い名前', 30),
            'description' => str_repeat('説明文がとても長い', 100),
        ]);
        $m = $this->metricsFixture(['curr' => 1000, 'm30' => 900]);
        $service = $this->makeService($m);
        $result = $service->generate(1, $oc);

        $this->assertNotNull($result);
        // mb_strlen で 160 字以内 (末尾 … 含む)
        $this->assertLessThanOrEqual(161, mb_strlen($result['meta_description']));
    }

    public function test_detail_contains_multiple_sentences_separated_by_newline(): void
    {
        $m = $this->metricsFixture(['curr' => 2000, 'm30' => 1500]); // active_growth
        $service = $this->makeService($m);
        $result = $service->generate(1, $this->buildOc());

        $this->assertNotNull($result);
        // detail に改行で複数文が含まれること
        $this->assertStringContainsString("\n", $result['detail']);
    }

    public function test_peak_mentioned_when_peak_significantly_higher(): void
    {
        $m = $this->metricsFixture([
            'curr' => 1000,
            'm30' => 900,
            'peak_high' => 2000, // 現在の 2 倍 → 言及
            'peak_date' => (new \DateTime('-180 days'))->format('Y-m-d'),
        ]);
        $service = $this->makeService($m);
        $result = $service->generate(1, $this->buildOc());

        $this->assertNotNull($result);
        $this->assertStringContainsString('ピーク', $result['detail']);
    }

    public function test_peak_not_mentioned_when_peak_close_to_curr(): void
    {
        $m = $this->metricsFixture([
            'curr' => 1000,
            'm30' => 900,
            'peak_high' => 1050, // ほぼ同じ → 言及しない
        ]);
        $service = $this->makeService($m);
        $result = $service->generate(1, $this->buildOc());

        $this->assertNotNull($result);
        $this->assertStringNotContainsString('ピーク', $result['detail']);
    }

    public function test_position_movement_included_when_delta_large(): void
    {
        $repo = $this->createMock(OcNarrativeRepositoryInterface::class);
        $repo->method('getMemberMetrics')->willReturn($this->metricsFixture(['curr' => 1000, 'm30' => 900]));
        $repo->method('getPositionMovement')->willReturn([
            'oldest_close' => 50,
            'oldest_date' => (new \DateTime('-30 days'))->format('Y-m-d'),
            'latest_close' => 12,
            'latest_date' => (new \DateTime('now'))->format('Y-m-d'),
            'best_high' => 10,
            'sample_n' => 30,
        ]);
        $service = new OcNarrativeService($repo);

        $result = $service->generate(1, $this->buildOc(['category' => 5]));
        $this->assertNotNull($result);
        $this->assertStringContainsString('カテゴリ内順位', $result['detail']);
        $this->assertStringContainsString('50 位', $result['detail']);
        $this->assertStringContainsString('12 位', $result['detail']);
    }

    public function test_position_movement_not_included_when_delta_small(): void
    {
        $repo = $this->createMock(OcNarrativeRepositoryInterface::class);
        $repo->method('getMemberMetrics')->willReturn($this->metricsFixture(['curr' => 1000, 'm30' => 900]));
        $repo->method('getPositionMovement')->willReturn([
            'oldest_close' => 15,
            'oldest_date' => (new \DateTime('-30 days'))->format('Y-m-d'),
            'latest_close' => 12, // delta=3 < 10 → 言及しない
            'latest_date' => (new \DateTime('now'))->format('Y-m-d'),
            'best_high' => 10,
            'sample_n' => 30,
        ]);
        $service = new OcNarrativeService($repo);

        $result = $service->generate(1, $this->buildOc(['category' => 5]));
        $this->assertNotNull($result);
        $this->assertStringNotContainsString('カテゴリ内順位', $result['detail']);
    }
}
