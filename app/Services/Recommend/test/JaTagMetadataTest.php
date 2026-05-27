<?php

declare(strict_types=1);

use App\Services\Recommend\TagDefinition\JaTagMetadata;
use App\Services\Recommend\TagDefinition\Ja\RecommendUtility;
use App\Services\Recommend\TagDefinition\Ja\RecommendTagFilters;
use App\Services\Recommend\TagDefinition\Ja\RecommendTagDescription;
use PHPUnit\Framework\TestCase;
use Shared\MimimalCmsConfig;

// docker compose exec -T app vendor/bin/phpunit app/Services/Recommend/test/JaTagMetadataTest.php
//
// Ja のタグメタデータ（略称/リダイレクト/説明文/フィルタ）が ja.json から供給され、
// 旧ハードコード const と同じ挙動になることを検証する。DB は使わない純粋関数テスト。
class JaTagMetadataTest extends TestCase
{
    protected function setUp(): void
    {
        MimimalCmsConfig::$urlRoot = '';
    }

    public function testMetadataLoadedFromJson(): void
    {
        $this->assertCount(25, JaTagMetadata::omitPattern());
        $this->assertCount(9, JaTagMetadata::redirects());
        $this->assertCount(18, JaTagMetadata::descriptions());
        $this->assertSame('ツムツム', JaTagMetadata::omitPattern()['ディズニー ツムツム'] ?? null);
        $this->assertSame('生成AI・ChatGPT', JaTagMetadata::redirects()['ChatGPT'] ?? null);
    }

    public function testExtractTagUsesOmitPattern(): void
    {
        // 略称マップ直接
        $this->assertSame('ツムツム', RecommendUtility::extractTag('ディズニー ツムツム'));
        // 末尾の全角括弧内を抽出してから略称解決
        $this->assertSame('ポケポケ', RecommendUtility::extractTag('ポケポケ（Pokémon TCG Pocket）'));
        // マップに無いものはそのまま返る
        $this->assertSame('存在しないタグ', RecommendUtility::extractTag('存在しないタグ'));
    }

    public function testGetValidTagReverseLookup(): void
    {
        $this->assertSame('ポケポケ', RecommendUtility::getValidTag('Pokémon TCG Pocket'));
        $this->assertFalse(RecommendUtility::getValidTag('該当なし'));
    }

    public function testRecommendTagDescriptionGet(): void
    {
        $this->assertIsString(RecommendTagDescription::get('スジ公開'));
        $this->assertNull(RecommendTagDescription::get('説明文の無いタグ'));
    }

    public function testRedirectAndTopPageFilter(): void
    {
        $this->assertSame(
            '画像生成AI・AIイラスト',
            RecommendTagFilters::redirectTags()['AI画像・イラスト生成'] ?? null
        );
        // フィルタは現状空（移行前と同じ）
        $this->assertSame([], RecommendTagFilters::getTopPageTagFilter());
    }
}
