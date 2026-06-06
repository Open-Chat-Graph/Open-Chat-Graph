<?php

declare(strict_types=1);

namespace App\Services\Recommend\TagDefinition;

/**
 * JSONファイルからタグ定義を供給する実装。
 *
 * タグ定義を PHP ハードコード配列から JSON へ移行したもの。
 * マッチングエンジン（RecommendUpdater）は一切変更しないため、
 * 供給される配列構造が移行前と1ビットも違わないことが要件。
 *
 * JSONスキーマ（data/ja.json）:
 *   {
 *     "strongest":        [ {"tag":..,"keywords"?:[..],"nameKeywords"?:[..]}, .. ],
 *     "beforeCategory":   { "<category>": [ {"tag":..,"keywords"?:[..]}, .. ], .. },
 *     "subCategoriesTag": { "<category>": [ {"tag":..,"keywords"?:[..]}, .. ], .. },
 *     "nameStrong":       [ {"tag":..,"keywords"?:[..]}, .. ],
 *     "descStrong":       [ .. ],
 *     "afterDescStrong":  [ .. ]
 *   }
 *
 * 規約:
 *   - 各エントリ e は {tag, keywords?}。
 *     keywords 有り → [e.tag, e.keywords]（array）
 *     keywords 無し → e.tag（string） ＝ 旧実装の「文字列エントリ」
 *   - strongest のみ列差分対応:
 *     keywords      = oc.description / null 列の変種
 *     nameKeywords  = oc.name 列の変種（異なる場合のみ存在）
 */
class JsonRecommendUpdaterTags implements RecommendUpdaterTagsInterface
{
    private string $jsonPath;

    /** @var array<string,mixed>|null パース済みJSONキャッシュ */
    private ?array $data = null;

    public function __construct(?string $jsonPath = null)
    {
        $this->jsonPath = $jsonPath ?? TagMetadata::jsonPath();
    }

    /**
     * urlRoot(言語)に対応する data/{lang}.json を読む実装を生成する。
     * '' => ja.json, '/th' => th.json, '/tw' => tw.json。
     * ja/th/tw を同一の JSON 駆動マッチング機構に統一するためのファクトリ。
     */
    public static function forLocale(string $urlRoot): self
    {
        return new self(TagMetadata::jsonPath($urlRoot));
    }

    /**
     * トップレベルのメタデータキー（redirects / omitPattern / descriptions /
     * recommendPageTagFilter 等）を配列で返す。非ja(th/tw)でも自言語JSONの
     * メタデータを参照できるようにするための公開アクセサ。
     *
     * @return array<string,mixed>
     */
    public function getMetadata(string $key): array
    {
        $v = $this->load()[$key] ?? null;
        return is_array($v) ? $v : [];
    }

    /**
     * JSONを一度だけ読んでキャッシュする。
     * 読めない/壊れている場合は例外を投げる（デプロイ事故を黙って劣化＝全タグ欠落させないため）。
     *
     * @return array<string,mixed>
     */
    private function load(): array
    {
        if ($this->data !== null) {
            return $this->data;
        }

        if (!is_file($this->jsonPath) || !is_readable($this->jsonPath)) {
            throw new \RuntimeException("Tag definition JSON not found or unreadable: {$this->jsonPath}");
        }

        $raw = file_get_contents($this->jsonPath);
        if ($raw === false) {
            throw new \RuntimeException("Failed to read tag definition JSON: {$this->jsonPath}");
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException("Invalid tag definition JSON: {$this->jsonPath}");
        }

        return $this->data = $decoded;
    }

    /**
     * 単一エントリを旧実装と同形に変換する。
     * keywords があれば [tag, keywords]、無ければ tag（string）。
     *
     * @param array<string,mixed> $entry
     * @return string|array{string, string[]}
     */
    private function entryToTagDef(array $entry): string|array
    {
        $tag = (string)($entry['tag'] ?? '');
        if (isset($entry['keywords']) && is_array($entry['keywords'])) {
            return [$tag, array_values(array_map('strval', $entry['keywords']))];
        }
        return $tag;
    }

    /**
     * エントリ配列を旧実装と同形のリストに変換する。
     *
     * @param array<int,array<string,mixed>>|mixed $entries
     * @return (string|array{string, string[]})[]
     */
    private function entriesToTagDefs($entries): array
    {
        if (!is_array($entries)) {
            return [];
        }

        $result = [];
        foreach ($entries as $entry) {
            if (is_array($entry)) {
                $result[] = $this->entryToTagDef($entry);
            }
        }
        return $result;
    }

    function getStrongestTags(?string $column = null): array
    {
        $entries = $this->load()['strongest'] ?? null;
        if (!is_array($entries)) {
            return [];
        }

        $result = [];
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $tag = (string)($entry['tag'] ?? '');

            $kw = $column === 'oc.name'
                ? ($entry['nameKeywords'] ?? $entry['keywords'] ?? null)
                : ($entry['keywords'] ?? null);

            if (is_array($kw)) {
                $result[] = [$tag, array_values(array_map('strval', $kw))];
            } else {
                $result[] = $tag;
            }
        }

        return $result;
    }

    function getBeforeCategoryNameTags(): array
    {
        $byCategory = $this->load()['beforeCategory'] ?? null;
        if (!is_array($byCategory)) {
            return [];
        }

        $result = [];
        foreach ($byCategory as $category => $entries) {
            // JSONオブジェクトのキー順を維持。キーは文字列として扱う。
            $result[(string)$category] = $this->entriesToTagDefs($entries);
        }

        return $result;
    }

    function getNameStrongTags(): array
    {
        return $this->entriesToTagDefs($this->load()['nameStrong'] ?? null);
    }

    function getDescStrongTags(): array
    {
        return $this->entriesToTagDefs($this->load()['descStrong'] ?? null);
    }

    function getAfterDescStrongTags(): array
    {
        return $this->entriesToTagDefs($this->load()['afterDescStrong'] ?? null);
    }

    function getSubCategoriesTag(): array
    {
        $byCategory = $this->load()['subCategoriesTag'] ?? null;
        if (!is_array($byCategory)) {
            return [];
        }

        $result = [];
        foreach ($byCategory as $category => $entries) {
            // JSONオブジェクトのキー順を維持。キーは文字列として扱う。
            $result[(string)$category] = $this->entriesToTagDefs($entries);
        }

        return $result;
    }
}
