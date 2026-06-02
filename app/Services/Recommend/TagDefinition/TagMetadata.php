<?php

declare(strict_types=1);

namespace App\Services\Recommend\TagDefinition;

use App\Config\AppConfig;

/**
 * タグ「メタデータ」を Git管理JSON(data/{lang}.json)から供給する共有ローダ。
 *
 * jsonPath() は ja/th/tw 全ロケールのタグ定義JSONパスを返す唯一の解決点。
 * 読込（本クラス・JsonRecommendUpdaterTags）と書込（管理GUI）・CRONのハッシュ検知が
 * 全てこのメソッドを共有する（パス組み立てが各所に散らばらないようにするため）。
 *
 * 静的メタデータアクセサ(omitPattern/redirects/descriptions 等)は規定ロケール(ja)の
 * data/ja.json を読む（th/tw のメタデータはインスタンスの
 * JsonRecommendUpdaterTags::getMetadata() で参照する）。
 * 供給するメタデータ:
 *   - omitPattern            : { "<ラベル>": "<略称>", .. }   （旧 RecommendUtility::OmitPettern）
 *   - redirects              : { "<旧ラベル>": "<新ラベル>", .. }（旧 RecommendTagFilters::RedirectTags）
 *   - recommendPageTagFilter : [ .. ]（旧 RecommendTagFilters::RecommendPageTagFilter）
 *   - filteredTagSort        : { "<tag>": [ .. ], .. }（旧 RecommendTagFilters::FilteredTagSort）
 *   - topPageTagFilter       : [ .. ]（旧 RecommendTagFilters::TopPageTagFilter）
 *   - descriptions           : { "<ラベル>": "<説明文>", .. }（旧 RecommendTagDescription::DESCRIPTIONS）
 *
 * JSONを一度だけ読んで静的キャッシュする。
 * 読めない/壊れている場合は例外を投げる（デプロイ事故を黙って劣化＝全メタデータ欠落させないため）。
 * 個々のキーが無いのは空配列で可。
 */
class TagMetadata
{
    /** @var array<string,mixed>|null パース済みJSONの静的キャッシュ */
    private static ?array $data = null;

    /**
     * ロケール(urlRoot)に対応するタグ定義JSON(data/{lang}.json)の絶対パスを返す。
     * '' => ja.json, '/th' => th.json, '/tw' => tw.json。
     *
     * ja/th/tw のパス組み立てを一本化するための唯一の解決点。
     */
    public static function jsonPath(string $urlRoot = ''): string
    {
        $lang = $urlRoot === '' ? 'ja' : ltrim($urlRoot, '/');
        return AppConfig::ROOT_PATH . "app/Services/Recommend/TagDefinition/data/{$lang}.json";
    }

    /**
     * JSONを一度だけ読んでキャッシュする。
     *
     * @return array<string,mixed>
     */
    private static function load(): array
    {
        if (self::$data !== null) {
            return self::$data;
        }

        $jsonPath = self::jsonPath();

        if (!is_file($jsonPath) || !is_readable($jsonPath)) {
            throw new \RuntimeException("Tag definition JSON not found or unreadable: {$jsonPath}");
        }

        $raw = file_get_contents($jsonPath);
        if ($raw === false) {
            throw new \RuntimeException("Failed to read tag definition JSON: {$jsonPath}");
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException("Invalid tag definition JSON: {$jsonPath}");
        }

        return self::$data = $decoded;
    }

    /**
     * @return array<string,mixed>
     */
    private static function arrayKey(string $key): array
    {
        $value = self::load()[$key] ?? null;
        return is_array($value) ? $value : [];
    }

    /** @return array<string,string> */
    static function omitPattern(): array
    {
        return self::arrayKey('omitPattern');
    }

    /** @return array<string,string> */
    static function redirects(): array
    {
        return self::arrayKey('redirects');
    }

    /** @return string[] */
    static function recommendPageTagFilter(): array
    {
        return self::arrayKey('recommendPageTagFilter');
    }

    /** @return array<string,string[]> */
    static function filteredTagSort(): array
    {
        return self::arrayKey('filteredTagSort');
    }

    /** @return string[] */
    static function topPageTagFilter(): array
    {
        return self::arrayKey('topPageTagFilter');
    }

    /** @return array<string,string> */
    static function descriptions(): array
    {
        return self::arrayKey('descriptions');
    }
}
