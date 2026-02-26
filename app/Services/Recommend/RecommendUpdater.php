<?php

declare(strict_types=1);

namespace App\Services\Recommend;

use App\Config\AppConfig;
use App\Services\Cron\Utility\CronUtility;
use App\Services\OpenChat\Utility\OpenChatServicesUtility;
use App\Models\RecommendRepositories\RecommendTagRepositoryInterface;
use App\Services\Recommend\TagDefinition\RecommendUpdaterTagsInterface;
use App\Services\Storage\FileStorageInterface;
use Shared\MimimalCmsConfig;

/**
 * タグマッチングによるレコメンド更新
 *
 * 1 SELECT で全対象行を取得し、PHP側で一括マッチング後、バッチINSERTで反映する方式。
 */
class RecommendUpdater
{
    protected RecommendUpdaterTagsInterface $recommendUpdaterTags;
    protected FileStorageInterface $fileStorage;
    protected RecommendTagRepositoryInterface $repository;
    protected string $start;
    protected string $end;
    protected string $openChatSubCategoriesTagKey = 'openChatSubCategoriesTag';
    protected string $targetIdJoinClause = '';

    /**
     * @var array<int, array{id: int, name: string, description: string, category: int}>
     */
    private array $rows = [];

    /** @var array<int, string> id => tag */
    private array $modifyTags = [];

    /** @var array<int, string> id => tag */
    private array $recommendResults = [];

    /** @var array<int, string> id => tag */
    private array $ocTagResults = [];

    /** @var array<int, string> id => tag */
    private array $ocTag2Results = [];

    function __construct(
        FileStorageInterface $fileStorage,
        RecommendTagRepositoryInterface $repository,
        ?RecommendUpdaterTagsInterface $recommendUpdaterTags = null
    ) {
        $this->fileStorage = $fileStorage;
        $this->repository = $repository;
        if ($recommendUpdaterTags) {
            $this->recommendUpdaterTags = $recommendUpdaterTags;
        } elseif (MimimalCmsConfig::$urlRoot === '/tw') {
            $this->recommendUpdaterTags = app(\App\Services\Recommend\TagDefinition\Tw\RecommendUpdaterTags::class);
            $this->openChatSubCategoriesTagKey = 'openChatSubCategories';
        } else if (MimimalCmsConfig::$urlRoot === '/th') {
            $this->recommendUpdaterTags = app(\App\Services\Recommend\TagDefinition\Th\RecommendUpdaterTags::class);
            $this->openChatSubCategoriesTagKey = 'openChatSubCategories';
        } else {
            $this->recommendUpdaterTags = app(\App\Services\Recommend\TagDefinition\Ja\RecommendUpdaterTags::class);
            $this->openChatSubCategoriesTagKey = 'openChatSubCategoriesTag';
        }
    }

    protected function getOpenChatSubCategoriesTag(): array
    {
        $path = $this->fileStorage->getStorageFilePath($this->openChatSubCategoriesTagKey);
        $data = json_decode(
            file_exists($path)
                ? $this->fileStorage->getContents('@' . $this->openChatSubCategoriesTagKey)
                : '{}',
            true
        );

        return is_array($data) ? $data : [];
    }

    function updateRecommendTables(bool $betweenUpdateTime = true, bool $onlyRecommend = false)
    {
        $this->start = $betweenUpdateTime
            ? $this->fileStorage->getContents('@tagUpdatedAtDatetime')
            : '2023-10-16 00:00:00';

        $this->end = $betweenUpdateTime
            ? OpenChatServicesUtility::getModifiedCronTime(strtotime('+1hour'))->format('Y-m-d H:i:s')
            : '2033-10-16 00:00:00';

        // 開発環境の場合、更新制限をかける
        $isMock = AppConfig::$isMockEnvironment;
        if ($isMock) {
            $limit = 10;
            $this->repository->createTargetIdTable($this->start, $this->end, $limit);
            $this->targetIdJoinClause = 'INNER JOIN target_oc_ids AS tid ON oc.id = tid.id';
            CronUtility::addCronLog("Mock environment. Recommend Update limit: {$limit}");
        }

        $this->updateRecommendTablesProcess($onlyRecommend);

        if ($isMock) {
            $this->repository->dropTargetIdTable();
        }

        $this->fileStorage->safeFileRewrite(
            '@tagUpdatedAtDatetime',
            (new \DateTime)->format('Y-m-d H:i:s')
        );
    }

    protected function updateRecommendTablesProcess(bool $onlyRecommend = false): void
    {
        $this->rows = $this->repository->fetchTargetRows($this->targetIdJoinClause, $this->start, $this->end);
        if (empty($this->rows)) {
            return;
        }

        $this->modifyTags = $this->repository->fetchModifyRecommendByIds(array_keys($this->rows));

        if (MimimalCmsConfig::$urlRoot !== '') {
            $this->matchAllRowsNonJa();
        } else {
            $this->matchAllRowsJa($onlyRecommend);
        }

        // recommend テーブルに反映
        $this->repository->bulkInsertViaTemp('recommend', $this->recommendResults);

        if ($onlyRecommend) {
            return;
        }

        // oc_tag テーブルに反映
        $this->repository->bulkInsertViaTemp('oc_tag', $this->ocTagResults);

        // oc_tag2 テーブルに反映
        $this->repository->bulkInsertViaTemp('oc_tag2', $this->ocTag2Results);
    }

    function getAllTagNames(): array
    {
        $tags = array_merge(
            array_merge(...$this->recommendUpdaterTags->getBeforeCategoryNameTags()),
            $this->recommendUpdaterTags->getStrongestTags(),
            $this->recommendUpdaterTags->getNameStrongTags(),
            $this->recommendUpdaterTags->getDescStrongTags(),
            $this->recommendUpdaterTags->getAfterDescStrongTags(),
            array_merge(...$this->getOpenChatSubCategoriesTag())
        );

        $tags = array_map(fn($el) => is_array($el) ? $el[0] : $el, $tags);
        $tags = array_map(fn($el) => $this->formatTag($el), $tags);
        return array_unique($tags);
    }

    function formatTag(string $tag): string
    {
        $listName = mb_strstr($tag, '_OR_', true) ?: $tag;
        $listName = str_replace('_AND_', ' ', $listName);
        $listName = str_replace('utfbin_', '', $listName);
        return $listName;
    }

    // ========================================================================
    // バルクマッチング: 日本語環境
    // ========================================================================

    /**
     * 日本語環境: 全行に対してタグマッチングを実行
     */
    private function matchAllRowsJa(bool $onlyRecommend): void
    {
        $this->recommendResults = [];
        $this->ocTagResults = [];
        $this->ocTag2Results = [];

        $allIds = array_keys($this->rows);

        // === recommend テーブル ===

        // 1. Strongest tags: name列
        $strongestNameTags = $this->recommendUpdaterTags->getStrongestTags('oc.name');
        foreach ($strongestNameTags as $tagDef) {
            $formattedTag = $this->extractFormattedTag($tagDef);
            foreach ($allIds as $id) {
                if (isset($this->recommendResults[$id])) continue;
                if ($this->matchesTagDef($tagDef, $this->rows[$id]['name'])) {
                    $this->recommendResults[$id] = $formattedTag;
                }
            }
        }

        // Strongest tags: description列
        $strongestDescTags = $this->recommendUpdaterTags->getStrongestTags('oc.description');
        foreach ($strongestDescTags as $tagDef) {
            $formattedTag = $this->extractFormattedTag($tagDef);
            foreach ($allIds as $id) {
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
                $formattedTag = $this->extractFormattedTag($tagDef);
                foreach ($allIds as $id) {
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
            $formattedTag = $this->extractFormattedTag($tagDef);
            foreach ($allIds as $id) {
                if (isset($this->recommendResults[$id])) continue;
                if ($this->matchesTagDef($tagDef, $this->rows[$id]['name'])) {
                    $this->recommendResults[$id] = $formattedTag;
                }
            }
        }

        // 4. Description tags (strong → category → afterStrong) - name column
        $this->matchDescriptionTags($allIds, 'name', $this->recommendResults);

        // 5. Description tags (strong → category → afterStrong) - description column
        $this->matchDescriptionTags($allIds, 'description', $this->recommendResults);

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

        // 1. Before-category tags (name)
        foreach ($beforeCategoryTags as $category => $tagDefs) {
            foreach ($tagDefs as $tagDef) {
                $formattedTag = $this->extractFormattedTag($tagDef);
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

        // 3. Description tags (description column)
        $this->matchDescriptionTags($allIds, 'description', $this->ocTagResults);

        // 4. Name tags
        foreach ($nameTags as $tagDef) {
            $formattedTag = $this->extractFormattedTag($tagDef);
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
            $formattedTag = $this->extractFormattedTag($tagDef);
            foreach ($allIds as $id) {
                if (isset($this->ocTag2Results[$id])) continue;
                if (!isset($this->ocTagResults[$id]) || $this->ocTagResults[$id] === $formattedTag) continue;
                if ($this->matchesTagDef($tagDef, $this->rows[$id]['name'])) {
                    $this->ocTag2Results[$id] = $formattedTag;
                }
            }
        }

        // 4. Name tags (description column)
        foreach ($nameTags as $tagDef) {
            $formattedTag = $this->extractFormattedTag($tagDef);
            foreach ($allIds as $id) {
                if (isset($this->ocTag2Results[$id])) continue;
                if (!isset($this->ocTagResults[$id]) || $this->ocTagResults[$id] === $formattedTag) continue;
                if ($this->matchesTagDef($tagDef, $this->rows[$id]['description'])) {
                    $this->ocTag2Results[$id] = $formattedTag;
                }
            }
        }
    }

    // ========================================================================
    // バルクマッチング: 台湾・タイ環境
    // ========================================================================

    /**
     * 台湾・タイ環境: 全行に対してタグマッチングを実行
     */
    private function matchAllRowsNonJa(): void
    {
        $this->recommendResults = [];
        $this->ocTagResults = [];
        $this->ocTag2Results = [];

        $allIds = array_keys($this->rows);

        $nameTags = array_merge(
            $this->recommendUpdaterTags->getNameStrongTags(),
            array_merge(...$this->getOpenChatSubCategoriesTag())
        );

        // recommend: name + description（allowDuplicateEntries=true）
        foreach ($nameTags as $tagDef) {
            $formattedTag = $this->extractFormattedTag($tagDef);
            foreach ($allIds as $id) {
                if (isset($this->recommendResults[$id]) && $this->recommendResults[$id] !== $formattedTag) continue;
                if ($this->matchesTagDef($tagDef, $this->rows[$id]['name'])) {
                    $this->recommendResults[$id] = $formattedTag;
                }
            }
        }
        foreach ($nameTags as $tagDef) {
            $formattedTag = $this->extractFormattedTag($tagDef);
            foreach ($allIds as $id) {
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
            $formattedTag = $this->extractFormattedTag($tagDef);
            foreach ($allIds as $id) {
                if (isset($this->ocTagResults[$id]) && $this->ocTagResults[$id] !== $formattedTag) continue;
                if ($this->matchesTagDef($tagDef, $this->rows[$id]['name'])) {
                    $this->ocTagResults[$id] = $formattedTag;
                }
            }
        }
        foreach ($nameTags as $tagDef) {
            $formattedTag = $this->extractFormattedTag($tagDef);
            foreach ($allIds as $id) {
                if (isset($this->ocTagResults[$id]) && $this->ocTagResults[$id] !== $formattedTag) continue;
                if ($this->matchesTagDef($tagDef, $this->rows[$id]['description'])) {
                    $this->ocTagResults[$id] = $formattedTag;
                }
            }
        }

        // oc_tag2: 全削除のみ（台湾・タイでは再挿入なし）
    }

    // ========================================================================
    // バルクマッチング: ヘルパーメソッド
    // ========================================================================

    /**
     * タグ定義からフォーマット済みタグ名を抽出する
     */
    private function extractFormattedTag(string|array $tagDef): string
    {
        $tag = is_array($tagDef) ? $tagDef[0] : $tagDef;
        return $this->formatTag($tag);
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
            foreach ($descStrongTags as $tagDef) {
                $formattedTag = $this->extractFormattedTag($tagDef);
                foreach ($targetIds as $id) {
                    if (isset($results[$id])) continue;
                    if ($this->rows[$id]['category'] !== (int)$category) continue;
                    if ($this->matchesTagDef($tagDef, $this->rows[$id][$column])) {
                        $results[$id] = $formattedTag;
                    }
                }
            }

            foreach ($categoryTagDefs as $tagDef) {
                $formattedTag = $this->extractFormattedTag($tagDef);
                foreach ($targetIds as $id) {
                    if (isset($results[$id])) continue;
                    if ($this->rows[$id]['category'] !== (int)$category) continue;
                    if ($this->matchesTagDef($tagDef, $this->rows[$id][$column])) {
                        $results[$id] = $formattedTag;
                    }
                }
            }

            foreach ($afterDescStrongTags as $tagDef) {
                $formattedTag = $this->extractFormattedTag($tagDef);
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
                $formattedTag = $this->extractFormattedTag($tagDef);
                foreach ($targetIds as $id) {
                    if (isset($this->ocTag2Results[$id])) continue;
                    if (!isset($this->ocTagResults[$id]) || $this->ocTagResults[$id] === $formattedTag) continue;
                    if ($this->rows[$id]['category'] !== (int)$category) continue;
                    if ($this->matchesTagDef($tagDef, $this->rows[$id][$column])) {
                        $this->ocTag2Results[$id] = $formattedTag;
                    }
                }
            }

            foreach ($categoryTagDefs as $tagDef) {
                $formattedTag = $this->extractFormattedTag($tagDef);
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
                $formattedTag = $this->extractFormattedTag($tagDef);
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

    // ========================================================================
    // バルクマッチング: テキストマッチング
    // ========================================================================

    /**
     * タグ定義に対してテキストがマッチするかPHPで判定する
     *
     * SQL LIKE をPHPで再現:
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
            foreach ($tagDef[1] as $keyword) {
                if ($this->matchesSingleKeyword($keyword, $text)) {
                    return true;
                }
            }
            return false;
        }

        return $this->matchesSingleKeyword($tagDef, $text);
    }

    /**
     * 単一キーワードのマッチング
     *
     * _AND_ が先に展開され、_OR_ が後に展開される
     * 結果として OR(AND(...), AND(...)) の構造になる（ANDがORより優先）
     */
    private function matchesSingleKeyword(string $keyword, string $text): bool
    {
        $utfbin = mb_strpos($keyword, 'utfbin_') !== false;
        $has4byte = (bool)preg_match('/[\xF0-\xF7][\x80-\xBF][\x80-\xBF][\x80-\xBF]/', $keyword);
        $useBinary = $utfbin || $has4byte;

        if ($utfbin) {
            $keyword = str_replace('utfbin_', '', $keyword);
        }

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
     * @param bool $binary true: バイナリ比較（str_contains）、false: 大文字小文字無視（mb_stripos）
     */
    private function containsText(string $keyword, string $text, bool $binary): bool
    {
        if ($binary) {
            return str_contains($text, $keyword);
        }
        return mb_stripos($text, $keyword) !== false;
    }
}
