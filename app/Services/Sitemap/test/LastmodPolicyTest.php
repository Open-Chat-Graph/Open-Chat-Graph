<?php

/**
 * LastmodPolicy のテスト
 *
 * 実行コマンド:
 * docker compose exec app vendor/bin/phpunit app/Services/Sitemap/test/LastmodPolicyTest.php
 *
 * 内容: sitemap lastmod を bump する人数変化の significant 判定。
 * 閾値 = max(ceil(snapshot * 1%), 5)。増減どちらも ABS で判定。
 * この境界値は OcSitemapLastmodRepository の SQL と一致していなければならない。
 */

declare(strict_types=1);

use App\Services\Sitemap\LastmodPolicy;
use PHPUnit\Framework\TestCase;

class LastmodPolicyTest extends TestCase
{
    public function test_threshold_uses_absolute_floor_for_small_rooms(): void
    {
        // 50人: ceil(0.5)=1 → max(1,5)=5
        $this->assertSame(5, LastmodPolicy::significanceThreshold(50));
        // 0人: max(0,5)=5
        $this->assertSame(5, LastmodPolicy::significanceThreshold(0));
        // 300人: ceil(3)=3 → max(3,5)=5
        $this->assertSame(5, LastmodPolicy::significanceThreshold(300));
    }

    public function test_threshold_uses_relative_ratio_for_large_rooms(): void
    {
        // 600人: ceil(6)=6 → max(6,5)=6
        $this->assertSame(6, LastmodPolicy::significanceThreshold(600));
        // 9000人: ceil(90)=90 → 90
        $this->assertSame(90, LastmodPolicy::significanceThreshold(9000));
        // 9050人: ceil(90.5)=91
        $this->assertSame(91, LastmodPolicy::significanceThreshold(9050));
    }

    public function test_small_room_boundary(): void
    {
        // 閾値 5
        $this->assertFalse(LastmodPolicy::isSignificantMemberChange(50, 54)); // +4
        $this->assertTrue(LastmodPolicy::isSignificantMemberChange(50, 55));  // +5
        $this->assertTrue(LastmodPolicy::isSignificantMemberChange(50, 45));  // -5
        $this->assertFalse(LastmodPolicy::isSignificantMemberChange(50, 46)); // -4
    }

    public function test_large_room_boundary(): void
    {
        // 9000人 → 閾値 90
        $this->assertFalse(LastmodPolicy::isSignificantMemberChange(9000, 9050)); // +50
        $this->assertTrue(LastmodPolicy::isSignificantMemberChange(9000, 9090));  // +90
        $this->assertTrue(LastmodPolicy::isSignificantMemberChange(9000, 8910));  // -90
    }

    public function test_zero_snapshot_room(): void
    {
        // 0人 → 閾値 5
        $this->assertFalse(LastmodPolicy::isSignificantMemberChange(0, 4));
        $this->assertTrue(LastmodPolicy::isSignificantMemberChange(0, 5));
    }

    public function test_no_change_is_not_significant(): void
    {
        $this->assertFalse(LastmodPolicy::isSignificantMemberChange(1234, 1234));
    }
}
