<?php

declare(strict_types=1);

use App\Services\Recommend\TagDefinition\TagMetadata;
use App\Services\Recommend\TagDefinition\Ja\RecommendUtility;
use App\Services\Recommend\TagDefinition\Ja\RecommendTagFilters;
use App\Services\Recommend\TagDefinition\Ja\RecommendTagDescription;
use PHPUnit\Framework\TestCase;
use Shared\MimimalCmsConfig;

// docker compose exec -T app vendor/bin/phpunit app/Services/Recommend/test/TagMetadataTest.php
//
// Ja のタグメタデータ（略称/リダイレクト/説明文/フィルタ）が ja.json から供給され、
// 旧ハードコード const と同じ「挙動」になることを検証する。
// ※ タグは GUI で随時編集されるため、件数や特定タグ名をハードコードせず、
//   ja.json の実データから動的に1件取り出して検証する（編集に強い）。
class TagMetadataTest extends TestCase
{
    protected function setUp(): void
    {
        MimimalCmsConfig::$urlRoot = '';
    }

    public function testMetadataShape(): void
    {
        foreach (['omitPattern' => TagMetadata::omitPattern(),
                  'redirects'   => TagMetadata::redirects(),
                  'descriptions' => TagMetadata::descriptions()] as $name => $map) {
            $this->assertIsArray($map, "{$name} は配列であるべき");
            $this->assertNotEmpty($map, "{$name} が空（ja.json の読み込み失敗の疑い）");
            foreach ($map as $k => $v) {
                $this->assertIsString((string)$k);
                $this->assertIsString($v, "{$name} の値は文字列であるべき");
            }
        }
    }

    public function testExtractTagUsesOmitPattern(): void
    {
        // 略称マップから1件取り出し、ラベル→略称に変換されることを確認（データ非依存）
        $omit = TagMetadata::omitPattern();
        $label = array_key_first($omit);
        $this->assertSame($omit[$label], RecommendUtility::extractTag($label));

        // 末尾の全角括弧内を抽出する算術ロジック（略称マップに無い語で確認）
        $this->assertSame('カッコ内テスト', RecommendUtility::extractTag('架空タグ（カッコ内テスト）'));

        // マップにも括弧にも該当しなければそのまま返る
        $this->assertSame('この語は存在しないはず', RecommendUtility::extractTag('この語は存在しないはず'));
    }

    public function testGetValidTagReverseLookup(): void
    {
        // 略称マップのキー（正規ラベル）を渡すと、対応する値が返る
        $omit = TagMetadata::omitPattern();
        $key = array_key_first($omit);
        $this->assertSame($omit[$key], RecommendUtility::getValidTag($key));

        $this->assertFalse(RecommendUtility::getValidTag('この語は存在しないはず'));
    }

    public function testRecommendTagDescriptionGet(): void
    {
        // 説明文マップから1件取り出し、その本文が返ることを確認
        $descs = TagMetadata::descriptions();
        $tag = array_key_first($descs);
        $this->assertSame($descs[$tag], RecommendTagDescription::get($tag));

        $this->assertNull(RecommendTagDescription::get('説明文の無いタグ_存在しない'));
    }

    public function testRedirect(): void
    {
        // リダイレクトマップから1件取り出し、旧→新が引けることを確認
        $redirects = RecommendTagFilters::redirectTags();
        $old = array_key_first($redirects);
        $this->assertSame($redirects[$old], RecommendTagFilters::redirectTags()[$old]);
    }
}
