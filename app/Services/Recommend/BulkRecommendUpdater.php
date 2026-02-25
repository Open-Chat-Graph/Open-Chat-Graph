<?php

declare(strict_types=1);

namespace App\Services\Recommend;

use App\Models\Repositories\DB;
use Shared\MimimalCmsConfig;

/**
 * RecommendUpdater のバルク最適化版
 *
 * 旧方式: ~2000 SQL INSERT（タグ×対象行の組み合わせ）
 * 新方式: 1 SELECT + PHP一括マッチング + 3 バッチINSERT
 */
class BulkRecommendUpdater extends RecommendUpdater
{
    /**
     * @var array<int, array{id: int, name: string, description: string, category: int}> 対象行
     */
    private array $rows = [];

    /**
     * @var array<int, string> id => tag のmodify_recommendデータ
     */
    private array $modifyTags = [];

    /**
     * マッチング結果: recommend テーブル用
     * @var array<int, string> id => tag
     */
    private array $recommendResults = [];

    /**
     * マッチング結果: oc_tag テーブル用
     * @var array<int, string> id => tag
     */
    private array $ocTagResults = [];

    /**
     * マッチング結果: oc_tag2 テーブル用
     * @var array<int, string> id => tag
     */
    private array $ocTag2Results = [];

    protected function updateRecommendTablesProcess(bool $onlyRecommend = false): void
    {
        $this->rows = $this->fetchTargetRows();
        if (empty($this->rows)) {
            return;
        }

        $this->modifyTags = $this->fetchModifyRecommend();

        if (MimimalCmsConfig::$urlRoot !== '') {
            $this->matchAllRowsNonJa();
        } else {
            $this->matchAllRowsJa($onlyRecommend);
        }

        // recommend テーブルに反映
        $this->bulkInsertViaTemp('recommend', $this->recommendResults);

        if ($onlyRecommend) {
            return;
        }

        // oc_tag テーブルに反映
        $this->bulkInsertViaTemp('oc_tag', $this->ocTagResults);

        // oc_tag2 テーブルに反映
        $this->bulkInsertViaTemp('oc_tag2', $this->ocTag2Results);
    }

    /**
     * 対象行を1 SELECTで取得
     *
     * @return array<int, array{id: int, name: string, description: string, category: int}>
     */
    private function fetchTargetRows(): array
    {
        $query = "SELECT oc.id, oc.name, oc.description, oc.category
                  FROM open_chat AS oc
                  {$this->targetIdJoinClause}
                  WHERE oc.updated_at BETWEEN :start AND :end";

        $stmt = DB::execute($query, ['start' => $this->start, 'end' => $this->end]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $indexed = [];
        foreach ($rows as $row) {
            $row['id'] = (int)$row['id'];
            $row['category'] = (int)$row['category'];
            $indexed[$row['id']] = $row;
        }

        return $indexed;
    }

    /**
     * modify_recommend テーブルの管理者オーバーライドを取得
     *
     * @return array<int, string> id => tag
     */
    private function fetchModifyRecommend(): array
    {
        $ids = array_keys($this->rows);
        if (empty($ids)) {
            return [];
        }

        // IDは自クエリ由来の整数値のため、直接埋め込みで安全
        $idList = implode(',', array_map('intval', $ids));
        $stmt = DB::execute(
            "SELECT id, tag FROM modify_recommend WHERE id IN ({$idList})"
        );

        $result = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $result[(int)$row['id']] = $row['tag'];
        }

        return $result;
    }

    /**
     * 日本語環境: 全行に対してタグマッチングを実行
     */
    private function matchAllRowsJa(bool $onlyRecommend): void
    {
        $this->recommendResults = [];
        $this->ocTagResults = [];
        $this->ocTag2Results = [];

        // === recommend テーブル ===

        // 全IDでマッチング（modify_recommendも通常マッチングに参加）
        $recommendTargetIds = array_keys($this->rows);

        // 1. Strongest tags: name列
        $strongestNameTags = $this->recommendUpdaterTags->getStrongestTags('oc.name');
        foreach ($strongestNameTags as $tagDef) {
            $tag = is_array($tagDef) ? $tagDef[0] : $tagDef;
            $formattedTag = $this->formatTag($tag);
            foreach ($recommendTargetIds as $id) {
                if (isset($this->recommendResults[$id])) continue;
                if ($this->matchesTagDef($tagDef, $this->rows[$id]['name'])) {
                    $this->recommendResults[$id] = $formattedTag;
                }
            }
        }

        // Strongest tags: description列
        $strongestDescTags = $this->recommendUpdaterTags->getStrongestTags('oc.description');
        foreach ($strongestDescTags as $tagDef) {
            $tag = is_array($tagDef) ? $tagDef[0] : $tagDef;
            $formattedTag = $this->formatTag($tag);
            foreach ($recommendTargetIds as $id) {
                if (isset($this->recommendResults[$id])) continue;
                if ($this->matchesTagDef($tagDef, $this->rows[$id]['description'])) {
                    $this->recommendResults[$id] = $formattedTag;
                }
            }
        }

        // 2. Before-category tags (name)
        $beforeCategoryTags = $this->recommendUpdaterTags->getBeforeCategoryNameTags();
        foreach ($beforeCategoryTags as $category => $tagDefs) {
            foreach ($tagDefs as $tagDef) {
                $tag = is_array($tagDef) ? $tagDef[0] : $tagDef;
                $formattedTag = $this->formatTag($tag);
                foreach ($recommendTargetIds as $id) {
                    if (isset($this->recommendResults[$id])) continue;
                    if ($this->rows[$id]['category'] !== (int)$category) continue;
                    if ($this->matchesTagDef($tagDef, $this->rows[$id]['name'])) {
                        $this->recommendResults[$id] = $formattedTag;
                    }
                }
            }
        }

        // 3. Name tags
        $nameTags = array_merge(
            $this->recommendUpdaterTags->getNameStrongTags(),
            array_merge(...$this->getOpenChatSubCategoriesTag())
        );
        foreach ($nameTags as $tagDef) {
            $tag = is_array($tagDef) ? $tagDef[0] : $tagDef;
            $formattedTag = $this->formatTag($tag);
            foreach ($recommendTargetIds as $id) {
                if (isset($this->recommendResults[$id])) continue;
                if ($this->matchesTagDef($tagDef, $this->rows[$id]['name'])) {
                    $this->recommendResults[$id] = $formattedTag;
                }
            }
        }

        // 4. Description tags (strong → category → afterStrong) - name column
        $this->matchDescriptionTags($recommendTargetIds, 'name', $this->recommendResults);

        // 5. Description tags (strong → category → afterStrong) - description column
        $this->matchDescriptionTags($recommendTargetIds, 'description', $this->recommendResults);

        // 6. Admin override (modifyRecommendTags) - マッチしたIDのみ上書き
        foreach ($this->modifyTags as $id => $tag) {
            if (isset($this->recommendResults[$id])) {
                $this->recommendResults[$id] = $tag;
            }
        }

        if ($onlyRecommend) {
            return;
        }

        // === oc_tag テーブル ===
        $allIds = array_keys($this->rows);

        // 1. Before-category tags (name)
        // 旧SQLでは updateBeforeCategory を name で2回呼ぶが、2回目は PK 制約で no-op
        foreach ($beforeCategoryTags as $category => $tagDefs) {
            foreach ($tagDefs as $tagDef) {
                $tag = is_array($tagDef) ? $tagDef[0] : $tagDef;
                $formattedTag = $this->formatTag($tag);
                foreach ($allIds as $id) {
                    if (isset($this->ocTagResults[$id])) continue;
                    if ($this->rows[$id]['category'] !== (int)$category) continue;
                    if ($this->matchesTagDef($tagDef, $this->rows[$id]['name'])) {
                        $this->ocTagResults[$id] = $formattedTag;
                    }
                }
            }
        }

        // 2. Description tags (name column)
        $this->matchDescriptionTags($allIds, 'name', $this->ocTagResults);

        // 4. Description tags (description column)
        $this->matchDescriptionTags($allIds, 'description', $this->ocTagResults);

        // 5. Name tags
        foreach ($nameTags as $tagDef) {
            $tag = is_array($tagDef) ? $tagDef[0] : $tagDef;
            $formattedTag = $this->formatTag($tag);
            foreach ($allIds as $id) {
                if (isset($this->ocTagResults[$id])) continue;
                if ($this->matchesTagDef($tagDef, $this->rows[$id]['name'])) {
                    $this->ocTagResults[$id] = $formattedTag;
                }
            }
        }

        // === oc_tag2 テーブル ===

        // 1. Description tags (name column)
        $this->matchDescriptionTagsForTag2($allIds, 'name');

        // 2. Description tags (description column)
        $this->matchDescriptionTagsForTag2($allIds, 'description');

        // 3. Name tags (name column)
        foreach ($nameTags as $tagDef) {
            $tag = is_array($tagDef) ? $tagDef[0] : $tagDef;
            $formattedTag = $this->formatTag($tag);
            foreach ($allIds as $id) {
                if (isset($this->ocTag2Results[$id])) continue;
                // oc_tagにエントリがないIDも除外（SQL: NOT t2.tag = 'x' でNULLはFALSE）
                if (!isset($this->ocTagResults[$id]) || $this->ocTagResults[$id] === $formattedTag) continue;
                if ($this->matchesTagDef($tagDef, $this->rows[$id]['name'])) {
                    $this->ocTag2Results[$id] = $formattedTag;
                }
            }
        }

        // 4. Name tags (description column)
        foreach ($nameTags as $tagDef) {
            $tag = is_array($tagDef) ? $tagDef[0] : $tagDef;
            $formattedTag = $this->formatTag($tag);
            foreach ($allIds as $id) {
                if (isset($this->ocTag2Results[$id])) continue;
                if (!isset($this->ocTagResults[$id]) || $this->ocTagResults[$id] === $formattedTag) continue;
                if ($this->matchesTagDef($tagDef, $this->rows[$id]['description'])) {
                    $this->ocTag2Results[$id] = $formattedTag;
                }
            }
        }
    }

    /**
     * 台湾・タイ環境: 全行に対してタグマッチングを実行
     */
    private function matchAllRowsNonJa(): void
    {
        $this->recommendResults = [];
        $this->ocTagResults = [];
        $this->ocTag2Results = [];

        $recommendTargetIds = array_keys($this->rows);
        $allIds = array_keys($this->rows);

        // recommend: name + description（allowDuplicateEntries=true）
        $nameTags = array_merge(
            $this->recommendUpdaterTags->getNameStrongTags(),
            array_merge(...$this->getOpenChatSubCategoriesTag())
        );

        // name
        foreach ($nameTags as $tagDef) {
            $tag = is_array($tagDef) ? $tagDef[0] : $tagDef;
            $formattedTag = $this->formatTag($tag);
            foreach ($recommendTargetIds as $id) {
                if (isset($this->recommendResults[$id]) && $this->recommendResults[$id] !== $formattedTag) continue;
                if ($this->matchesTagDef($tagDef, $this->rows[$id]['name'])) {
                    $this->recommendResults[$id] = $formattedTag;
                }
            }
        }
        // description
        foreach ($nameTags as $tagDef) {
            $tag = is_array($tagDef) ? $tagDef[0] : $tagDef;
            $formattedTag = $this->formatTag($tag);
            foreach ($recommendTargetIds as $id) {
                if (isset($this->recommendResults[$id]) && $this->recommendResults[$id] !== $formattedTag) continue;
                if ($this->matchesTagDef($tagDef, $this->rows[$id]['description'])) {
                    $this->recommendResults[$id] = $formattedTag;
                }
            }
        }

        // Admin override - マッチしたIDのみ上書き
        foreach ($this->modifyTags as $id => $tag) {
            if (isset($this->recommendResults[$id])) {
                $this->recommendResults[$id] = $tag;
            }
        }

        // oc_tag: name + description（allowDuplicateEntries=true）
        foreach ($nameTags as $tagDef) {
            $tag = is_array($tagDef) ? $tagDef[0] : $tagDef;
            $formattedTag = $this->formatTag($tag);
            foreach ($allIds as $id) {
                if (isset($this->ocTagResults[$id]) && $this->ocTagResults[$id] !== $formattedTag) continue;
                if ($this->matchesTagDef($tagDef, $this->rows[$id]['name'])) {
                    $this->ocTagResults[$id] = $formattedTag;
                }
            }
        }
        foreach ($nameTags as $tagDef) {
            $tag = is_array($tagDef) ? $tagDef[0] : $tagDef;
            $formattedTag = $this->formatTag($tag);
            foreach ($allIds as $id) {
                if (isset($this->ocTagResults[$id]) && $this->ocTagResults[$id] !== $formattedTag) continue;
                if ($this->matchesTagDef($tagDef, $this->rows[$id]['description'])) {
                    $this->ocTagResults[$id] = $formattedTag;
                }
            }
        }

        // oc_tag2: 全削除のみ（台湾・タイでは再挿入なし）
    }

    /**
     * description系タグのマッチング（strong → category → afterStrong の順）
     *
     * @param int[] $targetIds
     * @param string $column 'name' or 'description'
     * @param array<int, string> &$results
     */
    private function matchDescriptionTags(array $targetIds, string $column, array &$results): void
    {
        $subCategoriesTags = $this->getOpenChatSubCategoriesTag();
        $descStrongTags = $this->recommendUpdaterTags->getDescStrongTags();
        $afterDescStrongTags = $this->recommendUpdaterTags->getAfterDescStrongTags();

        foreach ($subCategoriesTags as $category => $categoryTagDefs) {
            // strong tags first
            foreach ($descStrongTags as $tagDef) {
                $tag = is_array($tagDef) ? $tagDef[0] : $tagDef;
                $formattedTag = $this->formatTag($tag);
                foreach ($targetIds as $id) {
                    if (isset($results[$id])) continue;
                    if ($this->rows[$id]['category'] !== (int)$category) continue;
                    if ($this->matchesTagDef($tagDef, $this->rows[$id][$column])) {
                        $results[$id] = $formattedTag;
                    }
                }
            }

            // category tags
            foreach ($categoryTagDefs as $tagDef) {
                $tag = is_array($tagDef) ? $tagDef[0] : $tagDef;
                $formattedTag = $this->formatTag($tag);
                foreach ($targetIds as $id) {
                    if (isset($results[$id])) continue;
                    if ($this->rows[$id]['category'] !== (int)$category) continue;
                    if ($this->matchesTagDef($tagDef, $this->rows[$id][$column])) {
                        $results[$id] = $formattedTag;
                    }
                }
            }

            // after strong tags
            foreach ($afterDescStrongTags as $tagDef) {
                $tag = is_array($tagDef) ? $tagDef[0] : $tagDef;
                $formattedTag = $this->formatTag($tag);
                foreach ($targetIds as $id) {
                    if (isset($results[$id])) continue;
                    if ($this->rows[$id]['category'] !== (int)$category) continue;
                    if ($this->matchesTagDef($tagDef, $this->rows[$id][$column])) {
                        $results[$id] = $formattedTag;
                    }
                }
            }
        }
    }

    /**
     * oc_tag2用 description系タグのマッチング
     * oc_tagと同じタグの場合はスキップする特殊条件付き
     *
     * @param int[] $targetIds
     * @param string $column 'name' or 'description'
     */
    private function matchDescriptionTagsForTag2(array $targetIds, string $column): void
    {
        $subCategoriesTags = $this->getOpenChatSubCategoriesTag();
        $descStrongTags = $this->recommendUpdaterTags->getDescStrongTags();
        $afterDescStrongTags = $this->recommendUpdaterTags->getAfterDescStrongTags();

        foreach ($subCategoriesTags as $category => $categoryTagDefs) {
            foreach ($descStrongTags as $tagDef) {
                $tag = is_array($tagDef) ? $tagDef[0] : $tagDef;
                $formattedTag = $this->formatTag($tag);
                foreach ($targetIds as $id) {
                    if (isset($this->ocTag2Results[$id])) continue;
                    // oc_tagにエントリがないIDも除外（SQL: NOT t2.tag = 'x' でNULLはFALSE）
                    if (!isset($this->ocTagResults[$id]) || $this->ocTagResults[$id] === $formattedTag) continue;
                    if ($this->rows[$id]['category'] !== (int)$category) continue;
                    if ($this->matchesTagDef($tagDef, $this->rows[$id][$column])) {
                        $this->ocTag2Results[$id] = $formattedTag;
                    }
                }
            }

            foreach ($categoryTagDefs as $tagDef) {
                $tag = is_array($tagDef) ? $tagDef[0] : $tagDef;
                $formattedTag = $this->formatTag($tag);
                foreach ($targetIds as $id) {
                    if (isset($this->ocTag2Results[$id])) continue;
                    if (!isset($this->ocTagResults[$id]) || $this->ocTagResults[$id] === $formattedTag) continue;
                    if ($this->rows[$id]['category'] !== (int)$category) continue;
                    if ($this->matchesTagDef($tagDef, $this->rows[$id][$column])) {
                        $this->ocTag2Results[$id] = $formattedTag;
                    }
                }
            }

            foreach ($afterDescStrongTags as $tagDef) {
                $tag = is_array($tagDef) ? $tagDef[0] : $tagDef;
                $formattedTag = $this->formatTag($tag);
                foreach ($targetIds as $id) {
                    if (isset($this->ocTag2Results[$id])) continue;
                    if (!isset($this->ocTagResults[$id]) || $this->ocTagResults[$id] === $formattedTag) continue;
                    if ($this->rows[$id]['category'] !== (int)$category) continue;
                    if ($this->matchesTagDef($tagDef, $this->rows[$id][$column])) {
                        $this->ocTag2Results[$id] = $formattedTag;
                    }
                }
            }
        }
    }

    /**
     * タグ定義に対してテキストがマッチするかPHPで判定する
     *
     * replace() が生成する SQL LIKE をPHPで再現:
     * - utf8mb4_general_ci LIKE → mb_stripos()
     * - utf8mb4_bin LIKE → str_contains()
     * - _AND_ → 全サブストリングが存在
     * - _OR_ → いずれかが存在
     * - utfbin_ プレフィックス → str_contains() を強制
     * - 4バイトUTF-8文字含有 → str_contains() を強制
     *
     * @param string|array{string, string[]} $tagDef タグ定義
     * @param string $text マッチ対象テキスト
     */
    private function matchesTagDef(string|array $tagDef, string $text): bool
    {
        if (is_array($tagDef)) {
            // array形式: [tagName, [keyword1, keyword2, ...]] → いずれかがマッチすればtrue
            foreach ($tagDef[1] as $keyword) {
                if ($this->matchesSingleKeyword($keyword, $text)) {
                    return true;
                }
            }
            return false;
        }

        // string形式: 単一キーワード
        return $this->matchesSingleKeyword($tagDef, $text);
    }

    /**
     * 単一キーワードのマッチング
     *
     * SQL の replace() と同じセマンティクス:
     * - _AND_ が先に展開され、_OR_ が後に展開される
     * - 結果として OR(AND(...), AND(...)) の構造になる（ANDがORより優先）
     *
     * @param string $keyword _AND_ / _OR_ / utfbin_ を含む可能性のあるキーワード
     * @param string $text マッチ対象テキスト
     */
    private function matchesSingleKeyword(string $keyword, string $text): bool
    {
        // utfbin_ プレフィックスの判定
        $utfbin = mb_strpos($keyword, 'utfbin_') !== false;

        // 4バイトUTF-8文字を含むか判定
        $has4byte = (bool)preg_match('/[\xF0-\xF7][\x80-\xBF][\x80-\xBF][\x80-\xBF]/', $keyword);

        // バイナリ照合が必要か（大文字小文字区別あり）
        $useBinary = $utfbin || $has4byte;

        // utfbin_ プレフィックスを除去
        if ($utfbin) {
            $keyword = str_replace('utfbin_', '', $keyword);
        }

        // _OR_ で分割（低優先度）→ 各パートを _AND_ で分割（高優先度）
        // SQL: AND が OR より先に展開されるため、OR(AND(...), AND(...)) の構造
        $orParts = explode('_OR_', $keyword);
        foreach ($orParts as $orPart) {
            $andParts = explode('_AND_', $orPart);
            $allMatch = true;
            foreach ($andParts as $andPart) {
                if (!$this->containsText($andPart, $text, $useBinary)) {
                    $allMatch = false;
                    break;
                }
            }
            if ($allMatch) {
                return true;
            }
        }

        return false;
    }

    /**
     * テキスト中にキーワードが含まれるかを判定
     *
     * @param string $keyword 検索キーワード
     * @param string $text 検索対象テキスト
     * @param bool $binary true: バイナリ比較（str_contains）、false: 大文字小文字無視（mb_stripos）
     */
    private function containsText(string $keyword, string $text, bool $binary): bool
    {
        if ($binary) {
            return str_contains($text, $keyword);
        }
        return mb_stripos($text, $keyword) !== false;
    }

    /**
     * 一時テーブル経由でバッチINSERT + アトミックスワップ
     *
     * @param string $targetTable 対象テーブル名（recommend, oc_tag, oc_tag2）
     * @param array<int, string> $data id => tag のマッピング
     */
    private function bulkInsertViaTemp(string $targetTable, array $data): void
    {
        $tempTable = $targetTable . '_temp';

        // 1. 一時テーブル作成
        DB::execute("CREATE TEMPORARY TABLE {$tempTable} LIKE {$targetTable}");

        // 2. チャンクごとにバッチINSERT
        $chunks = array_chunk($data, 1000, true);
        foreach ($chunks as $chunk) {
            $placeholders = [];
            $params = [];
            $i = 0;
            foreach ($chunk as $id => $tag) {
                $placeholders[] = "(:id{$i}, :tag{$i})";
                $params["id{$i}"] = $id;
                $params["tag{$i}"] = $tag;
                $i++;
            }
            if ($placeholders) {
                $sql = "INSERT INTO {$tempTable} (id, tag) VALUES " . implode(', ', $placeholders);
                DB::execute($sql, $params);
            }
        }

        // 3. 本テーブルに反映（アトミックスワップ）
        DB::transaction(function () use ($targetTable, $tempTable) {
            DB::execute("DELETE FROM {$targetTable} WHERE id IN (SELECT id FROM {$tempTable})");
            DB::execute("INSERT INTO {$targetTable} SELECT * FROM {$tempTable}");
        });

        // 4. 一時テーブル削除
        DB::execute("DROP TEMPORARY TABLE {$tempTable}");
    }
}
