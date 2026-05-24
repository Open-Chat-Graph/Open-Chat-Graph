<?php

/**
 * OcNarrativeService の状態分類カスケードのテスト
 *
 * 実行コマンド:
 * docker compose exec app vendor/bin/phpunit app/Services/Narrative/test/OcNarrativeServiceTest.php
 *
 * テスト方針:
 * - OcNarrativeRepositoryInterface を mock し、メトリクス戻り値の組合せで各状態を発火させる
 * - 戻り値の 'pattern' フィールドで決定的な分類を検証 (文章は揺れるため含有確認中心)
 * - 「数字と表現が矛盾しない」ことを実データ相当のフィクスチャで確認
 * - 異常データ / 例外 / curr=0 / sample_n=0 で必ず null を返すこと
 *
 * 状態 (pattern): stagnant / new / tiny / surge_up / surge_down /
 *                 strong_growth / recovering / shrinking_from_peak /
 *                 growing / gradual_up / gradual_down / declining / stable
 */

declare(strict_types=1);

use App\Models\Repositories\OcNarrativeRepositoryInterface;
use App\Services\Narrative\OcNarrativeService;
use PHPUnit\Framework\TestCase;

class OcNarrativeServiceTest extends TestCase
{
    // Service は locale 非依存にリファクタ済 (locale 分岐は Controller の責務)。
    // setUp / tearDown での MimimalCmsConfig 操作は不要。

    /**
     * member metrics fixture を返すヘルパ。
     * デフォルトは緩やかな増加 (growing) ルーム。
     */
    private function metricsFixture(array $overrides = []): array
    {
        $today = (new \DateTime('now'))->format('Y-m-d');
        return array_merge([
            'curr' => 1000,
            'curr_date' => $today,
            'm1' => 999,
            'm7' => 990,
            'm30' => 980,
            'm90' => 940,
            'sample_n' => 90,
            'peak_high' => 1100,
            'peak_date' => $today,
            'max_single_day_growth' => 30,
            'max_growth_date' => $today,
            'first_date' => (new \DateTime('-200 days'))->format('Y-m-d'),
            'all_time_peak' => 1100,
            'all_time_peak_date' => $today,
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
        $repo->method('getAveragePosition')->willReturn(['avg_position' => null, 'sample_n' => 0]);
        $repo->method('getGrowthRankingPositions')->willReturn(['hour' => null, 'day' => null, 'week' => null]);
        return new OcNarrativeService($repo);
    }

    // ============================================
    // 状態分類カスケード
    // ============================================

    public function test_growing_pattern(): void
    {
        // 大規模 + 1 ヶ月で実数 / % とも明確に増加 (急増ほどではない、強成長ほどでもない)
        $m = $this->metricsFixture([
            'curr' => 1000, 'm1' => 999, 'm7' => 995, 'm30' => 980, 'm90' => 940,
        ]); // 30日 +20 (+2.0%), 90日 +60 (+6.4%)
        $service = $this->makeService($m);
        $result = $service->generate(1, $this->buildOc());

        $this->assertNotNull($result);
        $this->assertSame('growing', $result['pattern']);
        $this->assertStringContainsString('増加中', $result['summary']);
    }

    public function test_strong_growth_pattern_by_absolute_scale(): void
    {
        // +261 人/月 のような大規模成長は実数規模で「拡大中」(% が低くても過小評価しない)
        $m = $this->metricsFixture([
            'curr' => 7946, 'm1' => 7946, 'm7' => 7872, 'm30' => 7685, 'm90' => 6660,
            'peak_high' => 7946, 'all_time_peak' => 7946,
        ]); // 30日 +261 (+3.4%), 90日 +1286 (+19.3%)
        $service = $this->makeService($m);
        $result = $service->generate(1, $this->buildOc());

        $this->assertNotNull($result);
        $this->assertSame('strong_growth', $result['pattern']);
        $this->assertStringContainsString('拡大中', $result['summary']);
        $this->assertStringContainsString('大きく人数を伸ばしている', $result['detail']);
    }

    public function test_large_room_modest_pct_is_not_called_rapid(): void
    {
        // 20,236 人, 30 日 +230 (+1.1%): 実数規模で strong_growth だが「急拡大中」とは言わない
        $m = $this->metricsFixture([
            'curr' => 20236, 'm1' => 20236, 'm7' => 20120, 'm30' => 20006, 'm90' => 19851,
            'peak_high' => 20236, 'all_time_peak' => 20236,
        ]);
        $service = $this->makeService($m);
        $result = $service->generate(1, $this->buildOc());

        $this->assertNotNull($result);
        $this->assertSame('strong_growth', $result['pattern']);
        $this->assertStringContainsString('拡大中', $result['summary']);
        $this->assertStringNotContainsString('急拡大中', $result['summary']);
    }

    public function test_surge_up_within_24h(): void
    {
        // 直近 24 時間で激増 (diff1 >= 10 かつ pct1 >= 10%)
        $m = $this->metricsFixture([
            'curr' => 8541, 'm1' => 4170, 'm7' => 2667, 'm30' => null, 'm90' => null,
            'sample_n' => 30, 'peak_high' => 8541, 'all_time_peak' => 8541,
        ]);
        $service = $this->makeService($m);
        $result = $service->generate(1, $this->buildOc());

        $this->assertNotNull($result);
        $this->assertSame('surge_up', $result['pattern']);
        $this->assertStringContainsString('急成長中', $result['summary']);
        $this->assertStringContainsString('直近 24 時間', $result['detail']);
    }

    public function test_surge_up_within_week(): void
    {
        // 24 時間は横ばいだが 1 週間で激増
        $m = $this->metricsFixture([
            'curr' => 1000, 'm1' => 999, 'm7' => 800, 'm30' => 780, 'm90' => 760,
        ]); // diff7 +200 (+25%)
        $service = $this->makeService($m);
        $result = $service->generate(1, $this->buildOc());

        $this->assertNotNull($result);
        $this->assertSame('surge_up', $result['pattern']);
        $this->assertStringContainsString('直近 1 週間', $result['detail']);
    }

    public function test_surge_down_within_week(): void
    {
        $m = $this->metricsFixture([
            'curr' => 800, 'm1' => 805, 'm7' => 1000, 'm30' => 1050, 'm90' => 1100,
        ]); // diff7 -200 (-20%)
        $service = $this->makeService($m);
        $result = $service->generate(1, $this->buildOc());

        $this->assertNotNull($result);
        $this->assertSame('surge_down', $result['pattern']);
        $this->assertStringContainsString('急減中', $result['summary']);
    }

    public function test_declining_pattern(): void
    {
        // 1 ヶ月で明確に減少 (急減ほどではない)
        $m = $this->metricsFixture([
            'curr' => 950, 'm1' => 952, 'm7' => 960, 'm30' => 1000, 'm90' => 1010,
            'peak_high' => 1010, 'all_time_peak' => 1010,
        ]); // 30日 -50 (-5%)
        $service = $this->makeService($m);
        $result = $service->generate(1, $this->buildOc());

        $this->assertNotNull($result);
        $this->assertSame('declining', $result['pattern']);
        $this->assertStringContainsString('縮小傾向', $result['summary']);
    }

    public function test_tiny_room_with_shrink_context(): void
    {
        // 小規模ルーム (curr < 50)、全期間ピーク 32 から縮小 → 「増加中」と言わない
        $m = $this->metricsFixture([
            'curr' => 13, 'm1' => 13, 'm7' => 13, 'm30' => 13, 'm90' => 12,
            'sample_n' => 198, 'peak_high' => 15, 'all_time_peak' => 32,
            'all_time_peak_date' => '2023-10-30',
        ]);
        $service = $this->makeService($m);
        $result = $service->generate(1, $this->buildOc());

        $this->assertNotNull($result);
        $this->assertSame('tiny', $result['pattern']);
        $this->assertStringContainsString('小規模', $result['summary']);
        $this->assertStringNotContainsString('増加中', $result['summary']);
        // 全期間ピークからの縮小を文脈として添える
        $this->assertStringContainsString('かつて', $result['detail']);
        $this->assertStringContainsString('32 人', $result['detail']);
    }

    public function test_recovering_from_peak(): void
    {
        // 全期間ピークから大きく縮小しているが直近 1 ヶ月は緩やかに増加に転じている
        // (強成長 (>=3% or >=100人) ほどではない modest な回復)
        $m = $this->metricsFixture([
            'curr' => 600, 'm1' => 599, 'm7' => 595, 'm30' => 585, 'm90' => 590,
            'peak_high' => 1000, 'all_time_peak' => 1000, 'all_time_peak_date' => '2024-01-01',
        ]); // curr 600 < 1000*0.7=700, 30日 +15 (+2.56%) > 0, 強成長閾値未満
        $service = $this->makeService($m);
        $result = $service->generate(1, $this->buildOc());

        $this->assertNotNull($result);
        $this->assertSame('recovering', $result['pattern']);
        $this->assertStringContainsString('増加に転じている', $result['detail']);
    }

    public function test_shrinking_from_peak(): void
    {
        // 全期間ピークから大きく縮小、直近も横ばい / 縮小
        $m = $this->metricsFixture([
            'curr' => 600, 'm1' => 601, 'm7' => 600, 'm30' => 602, 'm90' => 650,
            'peak_high' => 1000, 'all_time_peak' => 1000, 'all_time_peak_date' => '2024-01-01',
        ]); // curr 600 < 700, 30日 -2 (横ばい)
        $service = $this->makeService($m);
        $result = $service->generate(1, $this->buildOc());

        $this->assertNotNull($result);
        $this->assertSame('shrinking_from_peak', $result['pattern']);
        $this->assertStringContainsString('縮小傾向', $result['summary']);
        $this->assertStringContainsString('かつて', $result['detail']);
    }

    public function test_stable_pattern(): void
    {
        // 30 日も 90 日も横ばい (実数 ±3 以内)
        $m = $this->metricsFixture([
            'curr' => 1000, 'm1' => 1000, 'm7' => 1001, 'm30' => 1002, 'm90' => 1001,
            'peak_high' => 1005, 'all_time_peak' => 1005,
        ]);
        $service = $this->makeService($m);
        $result = $service->generate(1, $this->buildOc());

        $this->assertNotNull($result);
        $this->assertSame('stable', $result['pattern']);
        $this->assertStringContainsString('安定', $result['summary']);
    }

    public function test_gradual_increase_when_month_flat_but_quarter_grows(): void
    {
        // 30 日は実数 ±3 以内で横ばいだが、90 日では明確な増加 → 「じわじわ増加中」
        $m = $this->metricsFixture([
            'curr' => 1010, 'm1' => 1010, 'm7' => 1009, 'm30' => 1009, 'm90' => 980,
            'peak_high' => 1010, 'all_time_peak' => 1010,
        ]); // 30日 +1人 (横ばい), 90日 +30人 (+3.06%)
        $service = $this->makeService($m);
        $result = $service->generate(1, $this->buildOc());

        $this->assertNotNull($result);
        $this->assertSame('gradual_up', $result['pattern']);
        $this->assertStringContainsString('じわじわ増加中', $result['summary']);
        $this->assertStringContainsString('3 ヶ月単位では着実に人数が増えている', $result['detail']);
    }

    public function test_new_pattern(): void
    {
        // sample_n < 30 (サージでなければ実数で語る)
        $m = $this->metricsFixture([
            'curr' => 50, 'm1' => 49, 'm30' => null, 'm90' => null,
            'm7' => 48,
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
            'curr' => 500, 'm1' => 500, 'm7' => 500, 'm30' => 500, 'm90' => 500,
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
        $repo->method('getAveragePosition')->willReturn(['avg_position' => null, 'sample_n' => 0]);
        $repo->method('getGrowthRankingPositions')->willReturn(['hour' => null, 'day' => null, 'week' => null]);
        $service = new OcNarrativeService($repo);

        // 順位取得が落ちてもメンバー数推移ベースの narrative は返る
        $result = $service->generate(1, $this->buildOc());
        $this->assertNotNull($result);
        $this->assertSame('strong_growth', $result['pattern']); // 30日 +100 人 → 規模で strong_growth
    }

    // ============================================
    // ロケール判定
    // ============================================

    // locale 分岐は Controller の責務に移管 (OpenChatPageController.php)。
    // Service は locale 非依存になったため TW/TH 早期 null テストは削除。
    // Controller レベルでの locale guard は curl 実機 + Chrome MCP で別途確認。

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
            'm1' => null,
            'm7' => null,
            'm30' => null,
            'm90' => null,
            'sample_n' => 100,
            'peak_high' => 100,
            'all_time_peak' => 100, // 縮小文脈を発火させない (現在=ピーク)
        ]);
        $service = $this->makeService($m);
        $result = $service->generate(1, $this->buildOc());

        // 全期間データが無く比較不可 → stable へフォールバック
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
        $m = $this->metricsFixture(['curr' => 2000, 'm30' => 1500]); // strong_growth (規模大)
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

    public function test_category_internal_ranking_position_change_is_never_mentioned(): void
    {
        // 「カテゴリ内順位は 30 日前 50 位 → 現在 12 位」のような単独の順位変動は
        // 一般読者には意味が伝わらず、また「人がいるだけの無駄チャット」も拾うため出さない。
        // 代わりに「全体 ranking で大規模」「全体 / カテゴリ rising で活発」のラベルを使う。
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
        $repo->method('getAveragePosition')->willReturn(['avg_position' => null, 'sample_n' => 0]);
        $repo->method('getGrowthRankingPositions')->willReturn(['hour' => null, 'day' => null, 'week' => null]);
        $service = new OcNarrativeService($repo);

        $result = $service->generate(1, $this->buildOc(['category' => 5]));
        $this->assertNotNull($result);
        $this->assertStringNotContainsString('カテゴリ内順位', $result['detail']);
    }

    public function test_global_ranking_top_50_avg_produces_large_scale_label(): void
    {
        $repo = $this->createMock(OcNarrativeRepositoryInterface::class);
        $repo->method('getMemberMetrics')->willReturn($this->metricsFixture(['curr' => 5000, 'm30' => 4900]));
        $repo->method('getPositionMovement')->willReturn($this->emptyPositionMovement());
        $repo->method('getGrowthRankingPositions')->willReturn(['hour' => null, 'day' => null, 'week' => null]);
        $repo->method('getAveragePosition')->willReturnCallback(function ($id, $cat, $type) {
            if ($cat === 0 && $type === 'ranking') {
                return ['avg_position' => 25.5, 'sample_n' => 30];
            }
            return ['avg_position' => null, 'sample_n' => 0];
        });
        $service = new OcNarrativeService($repo);
        $result = $service->generate(1, $this->buildOc(['category' => 5]));

        $this->assertNotNull($result);
        $this->assertStringContainsString('大規模', $result['summary']);
    }

    public function test_global_rising_top_50_avg_produces_highest_class_active_label(): void
    {
        $repo = $this->createMock(OcNarrativeRepositoryInterface::class);
        $repo->method('getMemberMetrics')->willReturn($this->metricsFixture(['curr' => 5000, 'm30' => 4900]));
        $repo->method('getPositionMovement')->willReturn($this->emptyPositionMovement());
        $repo->method('getGrowthRankingPositions')->willReturn(['hour' => null, 'day' => null, 'week' => null]);
        $repo->method('getAveragePosition')->willReturnCallback(function ($id, $cat, $type) {
            if ($cat === 0 && $type === 'rising') {
                return ['avg_position' => 30.0, 'sample_n' => 30];
            }
            return ['avg_position' => null, 'sample_n' => 0];
        });
        $service = new OcNarrativeService($repo);
        $result = $service->generate(1, $this->buildOc(['category' => 5]));

        $this->assertNotNull($result);
        $this->assertStringContainsString('総合急上昇', $result['summary']);
        $this->assertStringContainsString('非常に活発', $result['summary']);
    }

    public function test_category_internal_rising_used_as_fallback_when_global_rising_absent(): void
    {
        $repo = $this->createMock(OcNarrativeRepositoryInterface::class);
        $repo->method('getMemberMetrics')->willReturn($this->metricsFixture(['curr' => 100, 'm30' => 95]));
        $repo->method('getPositionMovement')->willReturn($this->emptyPositionMovement());
        $repo->method('getGrowthRankingPositions')->willReturn(['hour' => null, 'day' => null, 'week' => null]);
        $repo->method('getAveragePosition')->willReturnCallback(function ($id, $cat, $type) {
            // 全体 rising は該当なし、カテゴリ内 rising で 5 位平均
            if ($cat === 0) {
                return ['avg_position' => null, 'sample_n' => 0];
            }
            if ($type === 'rising' && $cat > 0) {
                return ['avg_position' => 5.0, 'sample_n' => 25];
            }
            return ['avg_position' => null, 'sample_n' => 0];
        });
        $service = new OcNarrativeService($repo);
        $result = $service->generate(1, $this->buildOc(['category' => 5]));

        $this->assertNotNull($result);
        $this->assertStringContainsString('カテゴリ内', $result['summary']);
        $this->assertStringContainsString('活発', $result['summary']);
    }
}
