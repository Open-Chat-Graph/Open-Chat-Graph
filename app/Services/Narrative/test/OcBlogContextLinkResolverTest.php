<?php

/**
 * OcBlogContextLinkResolver（pattern → ブログ記事リンク）のテスト。
 *
 * 実行コマンド:
 * docker compose exec app vendor/bin/phpunit app/Services/Narrative/test/OcBlogContextLinkResolverTest.php
 */

declare(strict_types=1);

use App\Services\Narrative\OcBlogContextLinkResolver;
use PHPUnit\Framework\TestCase;

class OcBlogContextLinkResolverTest extends TestCase
{
    private OcBlogContextLinkResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new OcBlogContextLinkResolver();
    }

    public function test_急成長系は伸びる特徴の記事(): void
    {
        foreach (['surge_up', 'strong_growth', 'recovering'] as $pattern) {
            $this->assertSame('growing-openchat-features', $this->resolver->resolve($pattern)['slug'], $pattern);
        }
    }

    public function test_減少系は検索落ちの記事(): void
    {
        foreach (['surge_down', 'declining', 'stagnant', 'shrinking_from_peak'] as $pattern) {
            $this->assertSame('openchat-kensaku-ranking-ochi', $this->resolver->resolve($pattern)['slug'], $pattern);
        }
    }

    public function test_個別パターンの対応(): void
    {
        $this->assertSame('openchat-sagashikata', $this->resolver->resolve('tiny')['slug']);
        $this->assertSame('openchat-hajimekata', $this->resolver->resolve('new')['slug']);
        $this->assertSame('openchat-ranking-shikumi', $this->resolver->resolve('growing')['slug']);
        $this->assertSame('openchat-ranking-shikumi', $this->resolver->resolve('gradual_up')['slug']);
        $this->assertSame('openchat-kiken-anzen', $this->resolver->resolve('stable')['slug']);
        $this->assertSame('openchat-kiken-anzen', $this->resolver->resolve('gradual_down')['slug']);
    }

    public function test_未知パターンと空文字はnull(): void
    {
        $this->assertNull($this->resolver->resolve(''));
        $this->assertNull($this->resolver->resolve('unknown_pattern'));
    }

    public function test_全パターンでslugとlabelが揃う(): void
    {
        $patterns = [
            'surge_up', 'strong_growth', 'recovering',
            'surge_down', 'declining', 'stagnant', 'shrinking_from_peak',
            'tiny', 'new', 'growing', 'gradual_up', 'stable', 'gradual_down',
        ];
        foreach ($patterns as $pattern) {
            $result = $this->resolver->resolve($pattern);
            $this->assertNotNull($result, $pattern);
            $this->assertArrayHasKey('slug', $result);
            $this->assertArrayHasKey('label', $result);
            $this->assertNotSame('', $result['slug']);
            $this->assertNotSame('', $result['label']);
        }
    }
}
