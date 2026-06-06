<?php

/**
 * AlphaPagePathNormalizer のテスト (純粋・DB不要)
 *
 * 実行コマンド:
 * docker compose exec app vendor/bin/phpunit app/Services/Alpha/test/AlphaPagePathNormalizerTest.php
 */

declare(strict_types=1);

use App\Services\Alpha\AlphaPagePathNormalizer;
use PHPUnit\Framework\TestCase;

class AlphaPagePathNormalizerTest extends TestCase
{
    public function test_top_page_variants(): void
    {
        $top = ['path' => '/', 'label' => 'トップ'];
        $this->assertSame($top, AlphaPagePathNormalizer::normalize('/'));
        $this->assertSame($top, AlphaPagePathNormalizer::normalize('/index.html'));
        $this->assertSame($top, AlphaPagePathNormalizer::normalize('https://openchat-review.me'));
        $this->assertSame($top, AlphaPagePathNormalizer::normalize('https://openchat-review.me/'));
        $this->assertSame($top, AlphaPagePathNormalizer::normalize('/?utm_source=x'));
    }

    public function test_recommend_tag(): void
    {
        $this->assertSame(
            ['path' => '/recommend/雑談', 'label' => '雑談'],
            AlphaPagePathNormalizer::normalize('/recommend/雑談')
        );
        // URLエンコード済みタグ・完全URL・末尾スラッシュ・クエリ付き
        $this->assertSame(
            ['path' => '/recommend/雑談', 'label' => '雑談'],
            AlphaPagePathNormalizer::normalize('https://openchat-review.me/recommend/%E9%9B%91%E8%AB%87/?p=2')
        );
    }

    public function test_room_pages_are_excluded(): void
    {
        $this->assertNull(AlphaPagePathNormalizer::normalize('/oc/123456'));
        $this->assertNull(AlphaPagePathNormalizer::normalize('https://openchat-review.me/oc/123456?from=top'));
        $this->assertNull(AlphaPagePathNormalizer::normalize('/openchat/9876'));
    }

    public function test_other_locales_are_excluded(): void
    {
        $this->assertNull(AlphaPagePathNormalizer::normalize('/tw'));
        $this->assertNull(AlphaPagePathNormalizer::normalize('/tw/recommend/閒聊'));
        $this->assertNull(AlphaPagePathNormalizer::normalize('https://openchat-review.me/th/'));
    }

    public function test_empty_and_direct_are_excluded(): void
    {
        // 空文字は null（旧 AlphaGaClient 版はトップ扱いだったが、欠損行の混入を防ぐため脱落に統一）
        $this->assertNull(AlphaPagePathNormalizer::normalize(''));
        $this->assertNull(AlphaPagePathNormalizer::normalize('(direct)'));
    }

    public function test_other_internal_and_external_pages_are_excluded(): void
    {
        $this->assertNull(AlphaPagePathNormalizer::normalize('/ranking'));
        $this->assertNull(AlphaPagePathNormalizer::normalize('/recommend')); // tag なしは対象外
        $this->assertNull(AlphaPagePathNormalizer::normalize('https://www.google.com/search?q=x'));
    }
}
