<?php

declare(strict_types=1);

namespace App\Models\ApiRepositories\Alpha;

use App\Models\ApiRepositories\OpenChatApiArgs;
use App\Models\Repositories\DB;
use App\Services\Storage\FileStorageInterface;

/**
 * Alpha検索・マイリスト統合リポジトリ
 * AlphaSearchApiRepositoryを置き換え
 */
class AlphaOpenChatRepository
{
    private AlphaQueryBuilder $queryBuilder;

    public function __construct(
        FileStorageInterface $fileStorage,
    ) {
        $this->queryBuilder = new AlphaQueryBuilder($fileStorage);
    }

    /**
     * メンバー数または作成日でソート
     */
    public function findByMemberOrCreatedAt(OpenChatApiArgs $args): array
    {
        DB::connect();

        if (!$args->keyword) {
            // キーワードなし検索
            $query = $this->queryBuilder->buildSearchQuery(
                $args->category,
                $args->sort,
                $args->order,
                $args->limit,
                $args->page * $args->limit
            );
        } else {
            // キーワード検索
            $keywords = $this->parseKeywords($args->keyword);
            $query = $this->queryBuilder->buildKeywordSearchQuery(
                $args->category,
                $keywords,
                $args->sort,
                $args->order,
                $args->limit,
                $args->page * $args->limit
            );
        }

        $result = DB::fetchAll($query['sql'], $query['params']);

        // 1ページ目は件数を含める
        if ($result && $args->page === 0) {
            $countQuery = $this->queryBuilder->buildCountQuery(
                $args->category,
                $args->keyword ? $this->parseKeywords($args->keyword) : null
            );
            $result[0]['totalCount'] = DB::fetchColumn($countQuery['sql'], $countQuery['params']);
        }

        return $result;
    }

    /**
     * 1時間・24時間・1週間の増減でソート
     */
    public function findByStatsRanking(OpenChatApiArgs $args, string $tableName): array
    {
        DB::connect();

        if (!$args->keyword) {
            return $this->findByStatsRankingWithoutKeyword($args, $tableName);
        }

        return $this->findByStatsRankingWithKeyword($args, $tableName);
    }

    /**
     * ランキングソート（キーワードなし）
     */
    private function findByStatsRankingWithoutKeyword(OpenChatApiArgs $args, string $tableName): array
    {
        $offset = $args->page * $args->limit;

        // ランキング件数を取得して最終ページ判定
        $countQuery = $this->queryBuilder->buildRankingCountQuery($args->category, $tableName);
        $rankingCount = (int)DB::fetchColumn($countQuery['sql'], $countQuery['params']);
        $isLastPage = ($offset + $args->limit >= $rankingCount);

        if (!$isLastPage) {
            // 最終ページ以外：ランキングデータのみ
            $query = $this->queryBuilder->buildRankingQuery(
                $args->category,
                $tableName,
                $args->order,
                $args->limit,
                $offset
            );
        } else {
            // 最終ページ：UNION補完クエリ
            $query = $this->queryBuilder->buildUnionQuery(
                $args->category,
                $tableName,
                $args->order,
                $args->limit,
                $offset
            );
        }

        $result = DB::fetchAll($query['sql'], $query['params']);

        // 1ページ目は全体件数を含める
        if ($result && $args->page === 0) {
            $countQuery = $this->queryBuilder->buildCountQuery($args->category);
            $result[0]['totalCount'] = DB::fetchColumn($countQuery['sql'], $countQuery['params']);
        }

        return $result;
    }

    /**
     * ランキングソート（キーワード付き）
     */
    private function findByStatsRankingWithKeyword(OpenChatApiArgs $args, string $tableName): array
    {
        $keywords = $this->parseKeywords($args->keyword);
        $offset = $args->page * $args->limit;

        // ランキング件数を取得して最終ページ判定
        $countQuery = $this->queryBuilder->buildRankingCountQuery($args->category, $tableName, $keywords);
        $rankingCount = (int)DB::fetchColumn($countQuery['sql'], $countQuery['params']);
        $isLastPage = ($offset + $args->limit >= $rankingCount);

        if (!$isLastPage) {
            // 最終ページ以外：ランキングデータのみ
            $query = $this->queryBuilder->buildRankingKeywordQuery(
                $args->category,
                $keywords,
                $tableName,
                $args->order,
                $args->limit,
                $offset
            );
        } else {
            // 最終ページ：UNION補完クエリ
            $query = $this->queryBuilder->buildUnionKeywordQuery(
                $args->category,
                $keywords,
                $tableName,
                $args->order,
                $args->limit,
                $offset
            );
        }

        $result = DB::fetchAll($query['sql'], $query['params']);

        // 1ページ目は全体件数を含める
        if ($result && $args->page === 0) {
            $countQuery = $this->queryBuilder->buildCountQuery($args->category, $keywords);
            $result[0]['totalCount'] = DB::fetchColumn($countQuery['sql'], $countQuery['params']);
        }

        return $result;
    }

    /**
     * 現在のメンバー数を取得（部屋が存在しなければ 0）
     */
    public function getCurrentMember(int $openChatId): int
    {
        DB::connect();
        return (int)DB::fetchColumn(
            "SELECT member FROM open_chat WHERE id = :id",
            ['id' => $openChatId]
        );
    }

    /**
     * キーワードをパース（全角スペース→半角、空要素除去）
     */
    private function parseKeywords(string $keyword): array
    {
        $normalized = str_replace('　', ' ', $keyword);
        return array_filter(explode(' ', $normalized), fn($k) => !empty(trim($k)));
    }
}
