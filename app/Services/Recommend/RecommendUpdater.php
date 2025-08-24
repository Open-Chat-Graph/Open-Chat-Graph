<?php

declare(strict_types=1);

namespace App\Services\Recommend;

use App\Config\AppConfig;
use App\Services\OpenChat\Utility\OpenChatServicesUtility;
use App\Models\Repositories\DB;
use App\Services\Recommend\TagDefinition\RecommendUpdaterTagsInterface;
use Shared\MimimalCmsConfig;

class RecommendUpdater
{
    private RecommendUpdaterTagsInterface $recommendUpdaterTags;
    public array $tags;
    protected string $start;
    protected string $end;
    protected string $openChatSubCategoriesTagKey = 'openChatSubCategoriesTag';

    function __construct(?RecommendUpdaterTagsInterface $recommendUpdaterTags = null)
    {
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

    function updateRecommendTables(bool $betweenUpdateTime = true, bool $onlyRecommend = false)
    {
        $this->start = $betweenUpdateTime
            ? file_get_contents(AppConfig::getStorageFilePath('tagUpdatedAtDatetime'))
            : '2023-10-16 00:00:00';

        $this->end = $betweenUpdateTime
            ? OpenChatServicesUtility::getModifiedCronTime(strtotime('+1hour'))->format('Y-m-d H:i:s')
            : '2033-10-16 00:00:00';

        $this->updateRecommendTablesProcess($onlyRecommend);

        safeFileRewrite(
            AppConfig::getStorageFilePath('tagUpdatedAtDatetime'),
            (new \DateTime)->format('Y-m-d H:i:s')
        );
    }

    protected function updateRecommendTablesProcess(bool $onlyRecommend = false)
    {
        if (MimimalCmsConfig::$urlRoot !== '') {
            DB::transaction(function () {
                $this->deleteRecommendTags('recommend');
                $this->updateDescription(column: 'oc.name', allowDuplicateEntries: true);
                $this->updateDescription(allowDuplicateEntries: true);
            });

            DB::transaction(function () {
                $this->deleteTags('oc_tag');
                $this->updateName(table: 'oc_tag', allowDuplicateEntries: true);
                $this->updateName('oc.description', table: 'oc_tag', allowDuplicateEntries: true);
            });

            DB::transaction(function () {
                $this->deleteTags('oc_tag2');
            });

            return;
        }

        DB::transaction(function () {
            $this->deleteRecommendTags('recommend');
            $this->updateStrongestTags();
            $this->updateBeforeCategory();
            $this->updateName();
            $this->updateDescription('oc.name', 'recommend');
            $this->updateDescription();
            $this->modifyRecommendTags();
        });

        if ($onlyRecommend) {
            return;
        }

        DB::transaction(function () {
            $this->deleteTags('oc_tag');
            $this->updateBeforeCategory('oc.name', 'oc_tag');
            $this->updateBeforeCategory(table: 'oc_tag');
            $this->updateDescription('oc.name', 'oc_tag');
            $this->updateDescription(table: 'oc_tag');
            $this->updateName(table: 'oc_tag');
        });

        DB::transaction(function () {
            $this->deleteTags('oc_tag2');
            $this->updateDescription2('oc.name');
            $this->updateDescription2();
            $this->updateName2();
            $this->updateName2('oc.description');
        });
    }

    function getAllTagNames(): array
    {
        $tags = array_merge(
            array_merge(...$this->recommendUpdaterTags->getBeforeCategoryNameTags()),
            $this->recommendUpdaterTags->getStrongestTags(),
            $this->recommendUpdaterTags->getNameStrongTags(),
            $this->recommendUpdaterTags->getDescStrongTags(),
            $this->recommendUpdaterTags->getAfterDescStrongTags(),
            array_merge(...json_decode(
                file_get_contents(AppConfig::getStorageFilePath($this->openChatSubCategoriesTagKey)),
                true
            ))
        );

        $tags = array_map(fn($el) => is_array($el) ? $el[0] : $el, $tags);
        $tags = array_map(fn($el) => $this->formatTag($el), $tags);
        return array_unique($tags);
    }

    function replace(string|array $word, string $column): string
    {
        $rep = function ($str) use ($column) {
            $utfbin = mb_strpos($str, 'utfbin_') !== false;
            $collation = preg_match('/[\xF0-\xF7][\x80-\xBF][\x80-\xBF][\x80-\xBF]/', $str) || $utfbin ? 'utf8mb4_bin' : 'utf8mb4_general_ci';

            $like = "{$column} COLLATE {$collation} LIKE";
            if ($utfbin) $str = str_replace('utfbin_', '', $str);
            $str = str_replace('_AND_', "%' AND {$like} '%", $str);
            $str = str_replace('_OR_', "%' OR {$like} '%", $str);
            return "{$like} '%{$str}%'";
        };

        if (is_array($word)) {
            return "(" . implode(") OR (", array_map(fn($str) => $rep($str), $word[1])) . ")";
        }

        return $rep($word);
    }

    /** @return string[] */
    protected function getReplacedTags(string $column): array
    {
        $tags = array_merge(
            $this->recommendUpdaterTags->getNameStrongTags(),
            array_merge(...json_decode(
                file_get_contents(AppConfig::getStorageFilePath($this->openChatSubCategoriesTagKey)),
                true
            ))
        );

        $this->tags = array_map(fn($el) => is_array($el) ? $el[0] : $el, $tags);

        return array_map(fn($str) => $this->replace($str, $column), $tags);
    }

    function formatTag(string $tag): string
    {
        $listName = mb_strstr($tag, '_OR_', true) ?: $tag;
        $listName = str_replace('_AND_', ' ', $listName);
        $listName = str_replace('utfbin_', '', $listName);
        return $listName;
    }

    protected function updateName(
        string $column = 'oc.name',
        string $table = 'recommend',
        bool $allowDuplicateEntries = false
    ) {
        $tags = $this->getReplacedTags($column);

        foreach ($tags as $key => $search) {
            $tag = $this->formatTag($this->tags[$key]);
            $duplicateEntries = $allowDuplicateEntries ? "AND t.tag = '{$tag}'" : '';

            DB::execute(
                "INSERT INTO
                    {$table}
                SELECT
                    oc.id,
                    '{$tag}'
                FROM
                    (
                        SELECT
                            oc.*
                        FROM
                            open_chat AS oc
                            LEFT JOIN {$table} AS t ON t.id = oc.id {$duplicateEntries}
                        WHERE
                            t.id IS NULL
                            AND oc.updated_at BETWEEN :start
                            AND :end
                    ) AS oc
                WHERE
                    {$search}",
                ['start' => $this->start, 'end' => $this->end]
            );
        }
    }

    /** @return array{ string:string[] }  */
    protected function getReplacedTagsDesc(string $column): array
    {
        $this->tags = json_decode((file_get_contents(AppConfig::getStorageFilePath($this->openChatSubCategoriesTagKey))), true);

        return [
            array_map(fn($a) => array_map(fn($str) => $this->replace($str, $column), $a), $this->tags),
            array_map(fn($str) => $this->replace($str, $column), $this->recommendUpdaterTags->getDescStrongTags()),
            array_map(fn($str) => $this->replace($str, $column), $this->recommendUpdaterTags->getAfterDescStrongTags())
        ];
    }

    protected function updateDescription(
        string $column = 'oc.description',
        string $table = 'recommend',
        bool $allowDuplicateEntries = false
    ) {
        [$tags, $strongTags, $afterStrongTags] = $this->getReplacedTagsDesc($column);

        $excute = function ($table, $tag, $search, $category) use ($allowDuplicateEntries) {
            $tag = $this->formatTag($tag);
            $duplicateEntries = $allowDuplicateEntries ? "AND t.tag = '{$tag}'" : '';

            DB::execute(
                "INSERT INTO
                    {$table}
                SELECT
                    oc.id,
                    '{$tag}'
                FROM
                    (
                        SELECT
                            oc.*
                        FROM
                            open_chat AS oc
                            LEFT JOIN {$table} AS t ON t.id = oc.id {$duplicateEntries}
                        WHERE
                            oc.category = {$category}
                            AND (oc.updated_at BETWEEN :start AND :end)
                            AND t.id IS NULL
                    ) AS oc
                WHERE
                    {$search}",
                ['start' => $this->start, 'end' => $this->end]
            );
        };

        foreach ($tags as $category => $array) {
            foreach ($strongTags as $key => $search) {
                $tag = $this->recommendUpdaterTags->getDescStrongTags()[$key];
                $tag = is_array($tag) ? $tag[0] : $tag;
                $excute($table, $tag, $search, $category);
            }

            foreach ($array as $key => $search) {
                $tag = $this->tags[$category][$key];
                $tag = is_array($tag) ? $tag[0] : $tag;
                $excute($table, $tag, $search, $category);
            }

            foreach ($afterStrongTags as $key => $search) {
                $tag = $this->recommendUpdaterTags->getAfterDescStrongTags()[$key];
                $tag = is_array($tag) ? $tag[0] : $tag;
                $excute($table, $tag, $search, $category);
            }
        }
    }

    protected function updateBeforeCategory(string $column = 'oc.name', string $table = 'recommend'): void
    {
        $strongTags = array_map(
            fn($a) => array_map(fn($str) => $this->replace($str, $column), $a),
            $this->recommendUpdaterTags->getBeforeCategoryNameTags()
        );

        $excute = function ($table, $tag, $search, $category) {
            $tag = $this->formatTag($tag);
            DB::execute(
                "INSERT INTO
                    {$table}
                SELECT
                    oc.id,
                    '{$tag}'
                FROM
                    (
                        SELECT
                            oc.*
                        FROM
                            open_chat AS oc
                            LEFT JOIN {$table} AS t ON t.id = oc.id
                        WHERE
                            t.id IS NULL
                            AND oc.category = {$category}
                            AND oc.updated_at BETWEEN :start
                            AND :end
                    ) AS oc
                WHERE
                    {$search}",
                ['start' => $this->start, 'end' => $this->end]
            );
        };

        foreach ($strongTags as $category => $array) {
            foreach ($array as $key => $search) {
                $tag = $this->recommendUpdaterTags->getBeforeCategoryNameTags()[$category][$key];
                $tag = is_array($tag) ? $tag[0] : $tag;
                $excute($table, $tag, $search, $category);
            }
        }
    }

    protected function updateStrongestTags()
    {
        $this->executeUpdateStrongestTags('oc.name');
        $this->executeUpdateStrongestTags('oc.description');
    }

    protected function executeUpdateStrongestTags(
        string $column = 'oc.name',
        string $table = 'recommend',
    ) {
        $tags = $this->getReplacedStrongestTags($column);

        foreach ($tags as $key => $search) {
            $tag = $this->formatTag($this->tags[$key]);

            DB::execute(
                "INSERT INTO
                    {$table}
                SELECT
                    oc.id,
                    '{$tag}'
                FROM
                    (
                        SELECT
                            oc.*
                        FROM
                            open_chat AS oc
                            LEFT JOIN {$table} AS t ON t.id = oc.id
                        WHERE
                            t.id IS NULL
                            AND oc.updated_at BETWEEN :start
                            AND :end
                    ) AS oc
                WHERE
                    {$search}",
                ['start' => $this->start, 'end' => $this->end]
            );
        }
    }

    /** @return string[] */
    protected function getReplacedStrongestTags(string $column): array
    {
        $tags = $this->recommendUpdaterTags->getStrongestTags($column);

        $this->tags = array_map(fn($el) => is_array($el) ? $el[0] : $el, $tags);

        return array_map(fn($str) => $this->replace($str, $column), $tags);
    }

    protected function updateName2(
        string $column = 'oc.name',
        string $table = 'oc_tag2',
        bool $allowDuplicateEntries = false
    ) {
        $tags = $this->getReplacedTags($column);

        foreach ($tags as $key => $search) {
            $tag = $this->formatTag($this->tags[$key]);
            $duplicateEntries = $allowDuplicateEntries ? "AND t.tag = '{$tag}'" : '';

            DB::execute(
                "INSERT INTO
                    {$table}
                SELECT
                    oc.id,
                    '{$tag}'
                FROM
                    (
                        SELECT
                            oc.*
                        FROM
                            open_chat AS oc
                            LEFT JOIN {$table} AS t ON t.id = oc.id {$duplicateEntries}
                            LEFT JOIN oc_tag AS t2 ON t2.id = oc.id
                        WHERE
                            t.id IS NULL
                            AND NOT t2.tag = '{$tag}'
                            AND oc.updated_at BETWEEN :start
                            AND :end
                    ) AS oc
                WHERE
                    ({$search})",
                ['start' => $this->start, 'end' => $this->end]
            );
        }
    }

    protected function updateDescription2(string $column = 'oc.description', string $table = 'oc_tag2')
    {
        [$tags, $strongTags, $afterStrongTags] = $this->getReplacedTagsDesc($column);

        $excute = function ($table, $tag, $search, $category) {
            $tag = $this->formatTag($tag);
            DB::execute(
                "INSERT INTO
                    {$table}
                SELECT
                    oc.id,
                    '{$tag}'
                FROM
                    (
                        SELECT
                            oc.*
                        FROM
                            open_chat AS oc
                            LEFT JOIN {$table} AS t ON t.id = oc.id
                            LEFT JOIN oc_tag AS t2 ON t2.id = oc.id
                        WHERE
                            t.id IS NULL
                            AND NOT t2.tag = '{$tag}'
                            AND oc.category = {$category}
                            AND oc.updated_at BETWEEN :start
                            AND :end
                    ) AS oc
                WHERE
                    ({$search})",
                ['start' => $this->start, 'end' => $this->end]
            );
        };

        foreach ($tags as $category => $array) {
            foreach ($strongTags as $key => $search) {
                $tag = $this->recommendUpdaterTags->getDescStrongTags()[$key];
                $tag = is_array($tag) ? $tag[0] : $tag;
                $excute($table, $tag, $search, $category);
            }

            foreach ($array as $key => $search) {
                $tag = $this->tags[$category][$key];
                $tag = is_array($tag) ? $tag[0] : $tag;
                $excute($table, $tag, $search, $category);
            }

            foreach ($afterStrongTags as $key => $search) {
                $tag = $this->recommendUpdaterTags->getAfterDescStrongTags()[$key];
                $tag = is_array($tag) ? $tag[0] : $tag;
                $excute($table, $tag, $search, $category);
            }
        }
    }

    protected function deleteRecommendTags(string $table)
    {
        DB::execute(
            "DELETE FROM
                {$table}
            WHERE
                id IN (
                    SELECT
                        oc.id
                    FROM
                        open_chat AS oc
                        LEFT JOIN modify_recommend AS mr ON mr.id = oc.id
                    WHERE
                        mr.id IS NULL
                        AND oc.updated_at BETWEEN :start
                        AND :end
                )",
            ['start' => $this->start, 'end' => $this->end]
        );
    }

    protected function deleteTags(string $table)
    {
        DB::execute(
            "DELETE FROM
                {$table}
            WHERE
                id IN (
                    SELECT
                        oc.id
                    FROM
                        open_chat AS oc
                    WHERE
                        oc.updated_at BETWEEN :start
                        AND :end
                )",
            ['start' => $this->start, 'end' => $this->end]
        );
    }

    protected function modifyRecommendTags()
    {
        DB::execute("UPDATE recommend AS t1 JOIN modify_recommend AS t2 ON t1.id = t2.id SET t1.tag = t2.tag");
    }
}
