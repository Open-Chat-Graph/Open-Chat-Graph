<?php

declare(strict_types=1);

namespace App\Services\Recommend;

use App\Config\AppConfig;
use App\Services\Cron\Utility\CronUtility;
use App\Services\OpenChat\Utility\OpenChatServicesUtility;
use App\Models\Repositories\DB;
use App\Services\Recommend\TagDefinition\RecommendUpdaterTagsInterface;
use App\Services\Storage\FileStorageInterface;
use Shared\MimimalCmsConfig;

class RecommendUpdater
{
    private RecommendUpdaterTagsInterface $recommendUpdaterTags;
    private FileStorageInterface $fileStorage;
    public array $tags;
    protected string $start;
    protected string $end;
    protected string $openChatSubCategoriesTagKey = 'openChatSubCategoriesTag';
    protected string $targetIdJoinClause = '';

    function __construct(
        FileStorageInterface $fileStorage,
        ?RecommendUpdaterTagsInterface $recommendUpdaterTags = null
    ) {
        $this->fileStorage = $fileStorage;
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

    private function getOpenChatSubCategoriesTag(): array
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
            $this->createTargetIdTable($limit);
            $this->targetIdJoinClause = 'INNER JOIN target_oc_ids AS tid ON oc.id = tid.id';
            CronUtility::addCronLog("Mock environment. Recommend Update limit: {$limit}");
        }

        $this->updateRecommendTablesProcess($onlyRecommend);

        if ($isMock) {
            $this->dropTargetIdTable();
        }

        $this->fileStorage->safeFileRewrite(
            '@tagUpdatedAtDatetime',
            (new \DateTime)->format('Y-m-d H:i:s')
        );
    }

    protected function updateRecommendTablesProcess(bool $onlyRecommend = false)
    {
        if (MimimalCmsConfig::$urlRoot !== '') {
            // 一時テーブルで処理を実行（トランザクション外で高速処理）
            $this->processWithTemporaryTable('recommend', function () {
                $this->deleteRecommendTags('recommend_temp');
                $this->updateDescription(column: 'oc.name', table: 'recommend_temp', allowDuplicateEntries: true);
                $this->updateDescription(table: 'recommend_temp', allowDuplicateEntries: true);
            });

            $this->processWithTemporaryTable('oc_tag', function () {
                $this->deleteTags('oc_tag_temp');
                $this->updateName(table: 'oc_tag_temp', allowDuplicateEntries: true);
                $this->updateName('oc.description', table: 'oc_tag_temp', allowDuplicateEntries: true);
            });

            $this->processWithTemporaryTable('oc_tag2', function () {
                $this->deleteTags('oc_tag2_temp');
            });

            return;
        }

        // 一時テーブルで処理を実行（トランザクション外で高速処理）
        $this->processWithTemporaryTable('recommend', function () {
            $this->deleteRecommendTags('recommend_temp');
            $this->updateStrongestTags('recommend_temp');
            $this->updateBeforeCategory('oc.name', 'recommend_temp');
            $this->updateName(table: 'recommend_temp');
            $this->updateDescription('oc.name', 'recommend_temp');
            $this->updateDescription(table: 'recommend_temp');
            $this->modifyRecommendTags('recommend_temp');
        });

        if ($onlyRecommend) {
            return;
        }

        $this->processWithTemporaryTable('oc_tag', function () {
            $this->deleteTags('oc_tag_temp');
            $this->updateBeforeCategory('oc.name', 'oc_tag_temp');
            $this->updateBeforeCategory(table: 'oc_tag_temp');
            $this->updateDescription('oc.name', 'oc_tag_temp');
            $this->updateDescription(table: 'oc_tag_temp');
            $this->updateName(table: 'oc_tag_temp');
        });

        $this->processWithTemporaryTable('oc_tag2', function () {
            $this->deleteTags('oc_tag2_temp');
            $this->updateDescription2('oc.name', 'oc_tag2_temp');
            $this->updateDescription2(table: 'oc_tag2_temp');
            $this->updateName2(table: 'oc_tag2_temp');
            $this->updateName2('oc.description', table: 'oc_tag2_temp');
        });
    }

    /**
     * 一時テーブルを使用してデータを処理し、トランザクション内で本テーブルに反映
     * トランザクション時間を数ミリ秒に短縮することでデッドロックリスクを削減
     *
     * @param string $targetTable 対象テーブル名
     * @param callable $processCallback 一時テーブルに対する処理
     */
    private function processWithTemporaryTable(string $targetTable, callable $processCallback): void
    {
        $tempTable = $targetTable . '_temp';

        // 1. 一時テーブルを作成（トランザクション外）
        DB::execute("CREATE TEMPORARY TABLE {$tempTable} LIKE {$targetTable}");

        // 2. 一時テーブルに対して処理を実行（トランザクション外で高速処理）
        $processCallback();

        // 3. 本テーブルに反映（トランザクション内、数ミリ秒で完了）
        DB::transaction(function () use ($targetTable, $tempTable) {
            // 更新対象のレコードを削除
            DB::execute("DELETE FROM {$targetTable}
                         WHERE id IN (SELECT id FROM {$tempTable})");

            // 一時テーブルから本テーブルにコピー
            DB::execute("INSERT INTO {$targetTable} SELECT * FROM {$tempTable}");
        });

        // 4. 一時テーブルを削除
        DB::execute("DROP TEMPORARY TABLE {$tempTable}");
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
            array_merge(...$this->getOpenChatSubCategoriesTag())
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
                            {$this->targetIdJoinClause}
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
        $this->tags = $this->getOpenChatSubCategoriesTag();

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

        $excute = function ($targetTable, $tag, $search, $category) use ($allowDuplicateEntries) {
            $tag = $this->formatTag($tag);
            $duplicateEntries = $allowDuplicateEntries ? "AND t.tag = '{$tag}'" : '';

            DB::execute(
                "INSERT INTO
                    {$targetTable}
                SELECT
                    oc.id,
                    '{$tag}'
                FROM
                    (
                        SELECT
                            oc.*
                        FROM
                            open_chat AS oc
                            {$this->targetIdJoinClause}
                            LEFT JOIN {$targetTable} AS t ON t.id = oc.id {$duplicateEntries}
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

        $excute = function ($targetTable, $tag, $search, $category) {
            $tag = $this->formatTag($tag);
            DB::execute(
                "INSERT INTO
                    {$targetTable}
                SELECT
                    oc.id,
                    '{$tag}'
                FROM
                    (
                        SELECT
                            oc.*
                        FROM
                            open_chat AS oc
                            {$this->targetIdJoinClause}
                            LEFT JOIN {$targetTable} AS t ON t.id = oc.id
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

    protected function updateStrongestTags(string $table = 'recommend')
    {
        $this->executeUpdateStrongestTags('oc.name', $table);
        $this->executeUpdateStrongestTags('oc.description', $table);
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
                            {$this->targetIdJoinClause}
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
                            {$this->targetIdJoinClause}
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

        $excute = function ($targetTable, $tag, $search, $category) {
            $tag = $this->formatTag($tag);
            DB::execute(
                "INSERT INTO
                    {$targetTable}
                SELECT
                    oc.id,
                    '{$tag}'
                FROM
                    (
                        SELECT
                            oc.*
                        FROM
                            open_chat AS oc
                            {$this->targetIdJoinClause}
                            LEFT JOIN {$targetTable} AS t ON t.id = oc.id
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
        if ($this->targetIdJoinClause) {
            // Mock環境：一時テーブルを使用
            DB::execute(
                "DELETE t FROM {$table} t
                INNER JOIN (
                    SELECT oc.id
                    FROM open_chat AS oc
                    INNER JOIN target_oc_ids AS tid ON oc.id = tid.id
                    LEFT JOIN modify_recommend AS mr ON mr.id = oc.id
                    WHERE mr.id IS NULL
                        AND oc.updated_at BETWEEN :start AND :end
                ) AS target ON t.id = target.id",
                ['start' => $this->start, 'end' => $this->end]
            );
        } else {
            // 本番環境：従来の方法
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
    }

    protected function deleteTags(string $table)
    {
        if ($this->targetIdJoinClause) {
            // Mock環境：一時テーブルを使用
            DB::execute(
                "DELETE t FROM {$table} t
                INNER JOIN (
                    SELECT oc.id
                    FROM open_chat AS oc
                    INNER JOIN target_oc_ids AS tid ON oc.id = tid.id
                    WHERE oc.updated_at BETWEEN :start AND :end
                ) AS target ON t.id = target.id",
                ['start' => $this->start, 'end' => $this->end]
            );
        } else {
            // 本番環境：従来の方法
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
    }

    protected function modifyRecommendTags(string $table = 'recommend')
    {
        DB::execute("UPDATE {$table} AS t1 JOIN modify_recommend AS t2 ON t1.id = t2.id SET t1.tag = t2.tag");
    }

    /**
     * Mock環境用：処理対象IDを制限する一時テーブルを作成
     */
    private function createTargetIdTable(int $limit): void
    {
        DB::execute("CREATE TEMPORARY TABLE IF NOT EXISTS target_oc_ids (id INT PRIMARY KEY)");
        DB::execute(
            "INSERT INTO target_oc_ids
             SELECT id FROM open_chat
             WHERE updated_at BETWEEN :start AND :end
             LIMIT :limit",
            ['start' => $this->start, 'end' => $this->end, 'limit' => $limit]
        );
    }

    /**
     * Mock環境用：一時テーブルを削除
     */
    private function dropTargetIdTable(): void
    {
        DB::execute("DROP TEMPORARY TABLE IF EXISTS target_oc_ids");
    }
}
