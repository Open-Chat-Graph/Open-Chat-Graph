<?php

declare(strict_types=1);

namespace App\Services\Recommend;

use App\Config\AppConfig;
use App\Services\Recommend\Dto\RecommendListDto;
use App\Services\Recommend\Enum\RecommendListType;
use App\Services\Storage\FileStorageInterface;
use Shared\MimimalCmsConfig;

class BulkRecommendRankingBuilder implements BulkRecommendRankingBuilderInterface
{
    // 関連タグ取得に関する値（台湾・タイのみ）
    private const SORT_AND_UNIQUE_TAGS_LIST_LIMIT = null;
    private const SORT_AND_UNIQUE_ARRAY_MIN_COUNT = 5;

    /** @var array<int, array> 全データ（IDキー） */
    private array $allData = [];

    /** @var array<string, int[]> recommend_tagでグループ化したID一覧 */
    private array $byRecommendTag = [];

    /** @var array<string, int[]> oc_tagでグループ化したID一覧 */
    private array $byOcTag = [];

    /** @var array<int, int[]> categoryでグループ化したID一覧 */
    private array $byCategory = [];

    /** @var array<int, int[]> emblemでグループ化したID一覧 */
    private array $byEmblem = [];

    /** @var array<int, true> recommend_tagを持つID一覧 */
    private array $hasRecommendTag = [];

    public function __construct(
        private FileStorageInterface $fileStorage
    ) {}

    /**
     * 事前取得データを受け取り、インデックスを構築する
     *
     * @param array<int, array> $allData IDをキーとした連想配列
     */
    function init(array $allData): void
    {
        $this->allData = $allData;
        $this->byRecommendTag = [];
        $this->byOcTag = [];
        $this->byCategory = [];
        $this->byEmblem = [];
        $this->hasRecommendTag = [];

        foreach ($allData as $id => $row) {
            if ($row['recommend_tag'] !== null) {
                $this->byRecommendTag[$row['recommend_tag']][] = $id;
                $this->hasRecommendTag[$id] = true;
            }
            if ($row['oc_tag'] !== null) {
                $this->byOcTag[$row['oc_tag']][] = $id;
            }
            $this->byCategory[(int)$row['category']][] = $id;
            if ((int)$row['emblem'] > 0) {
                $this->byEmblem[(int)$row['emblem']][] = $id;
            }
        }
    }

    /**
     * タグ別ランキングを構築する
     * tag1 = oc_tag, tag2 = oc_tag2（RecommendRankingRepositoryと同じ）
     */
    function buildTagRanking(string $tag, string $listName): RecommendListDto
    {
        // recommend.tag = $tag に一致するIDを取得
        // タグランキングはrecommend起点なので全tierで同じ候補セット
        $candidateIds = $this->byRecommendTag[$tag] ?? [];

        return $this->buildRanking(
            RecommendListType::Tag,
            $listName,
            $candidateIds,
            $candidateIds,
            fn(array $row) => $row['oc_tag'],    // tag1
            fn(array $row) => $row['oc_tag2'],   // tag2
        );
    }

    /**
     * カテゴリ別ランキングを構築する
     * tag1 = recommend_tag, tag2 = oc_tag2（CategoryRankingRepositoryと同じ）
     *
     * 旧SQLではtier1-3でrecommend JOINのidを使っていたため、
     * recommendタグを持つルームのみがtier1-3に含まれていた。
     * tier4は全ルームが対象。
     */
    function buildCategoryRanking(int $category, string $listName): RecommendListDto
    {
        $allCandidateIds = $this->byCategory[$category] ?? [];

        // tier1-3: recommendタグを持つIDのみ（旧SQLの意図された動作を再現）
        $statsCandidateIds = array_values(array_filter(
            $allCandidateIds,
            fn(int $id) => isset($this->hasRecommendTag[$id])
        ));

        return $this->buildRanking(
            RecommendListType::Category,
            $listName,
            $statsCandidateIds,
            $allCandidateIds,
            fn(array $row) => $row['recommend_tag'], // tag1
            fn(array $row) => $row['oc_tag2'],       // tag2
        );
    }

    /**
     * 公式ルーム別ランキングを構築する
     * tag1 = recommend_tag, tag2 = oc_tag2（OfficialRoomRankingRepositoryと同じ）
     */
    function buildOfficialRanking(int $emblem, string $listName): RecommendListDto
    {
        $allCandidateIds = $this->byEmblem[$emblem] ?? [];

        // tier1-3: recommendタグを持つIDのみ
        $statsCandidateIds = array_values(array_filter(
            $allCandidateIds,
            fn(int $id) => isset($this->hasRecommendTag[$id])
        ));

        return $this->buildRanking(
            RecommendListType::Official,
            $listName,
            $statsCandidateIds,
            $allCandidateIds,
            fn(array $row) => $row['recommend_tag'], // tag1
            fn(array $row) => $row['oc_tag2'],       // tag2
        );
    }

    /**
     * MySQLのDESCソートと同じNULL処理（NULLは末尾）
     */
    private static function nullLastDesc($a, $b): int
    {
        if ($a === null && $b === null) return 0;
        if ($a === null) return 1;   // NULLは末尾
        if ($b === null) return -1;
        return (int)$b <=> (int)$a;
    }

    /**
     * 4段階ランキングを構築する
     *
     * @param int[] $statsCandidateIds tier1-3の対象ID群
     * @param int[] $memberCandidateIds tier4の対象ID群
     * @param \Closure $getTag1 行からtag1を取得するクロージャ
     * @param \Closure $getTag2 行からtag2を取得するクロージャ
     */
    private function buildRanking(
        RecommendListType $type,
        string $listName,
        array $statsCandidateIds,
        array $memberCandidateIds,
        \Closure $getTag1,
        \Closure $getTag2,
    ): RecommendListDto {
        $limit = AppConfig::LIST_LIMIT_RECOMMEND;

        // Tier 1: hour_diff >= 3, hour_diff DESCでソート
        $tier1 = $this->filterAndSort(
            $statsCandidateIds,
            [],
            'hour_diff',
            AppConfig::RECOMMEND_MIN_MEMBER_DIFF_HOUR,
            fn(array $a, array $b) =>
                self::nullLastDesc($b['hour_diff'] ?? null, $a['hour_diff'] ?? null)
                ?: ((int)$a['id'] <=> (int)$b['id']),
            $limit,
            $getTag1,
            $getTag2,
            AppConfig::RANKING_HOUR_TABLE_NAME,
        );

        // Tier 2: hour24_diff >= 8, hour_diff DESC → hour24_diff DESC
        $excludeIds = array_column($tier1, 'id');
        $tier2 = $this->filterAndSort(
            $statsCandidateIds,
            $excludeIds,
            'hour24_diff',
            AppConfig::RECOMMEND_MIN_MEMBER_DIFF_H24,
            fn(array $a, array $b) =>
                self::nullLastDesc($b['hour_diff'] ?? null, $a['hour_diff'] ?? null)
                ?: (self::nullLastDesc($b['hour24_diff'] ?? null, $a['hour24_diff'] ?? null)
                    ?: ((int)$a['id'] <=> (int)$b['id'])),
            $limit,
            $getTag1,
            $getTag2,
            AppConfig::RANKING_DAY_TABLE_NAME,
        );

        // Tier 3: week_diff >= 10, hour_diff DESC → week_diff DESC
        $excludeIds = array_merge($excludeIds, array_column($tier2, 'id'));
        $tier3 = $this->filterAndSort(
            $statsCandidateIds,
            $excludeIds,
            'week_diff',
            AppConfig::RECOMMEND_MIN_MEMBER_DIFF_WEEK,
            fn(array $a, array $b) =>
                self::nullLastDesc($b['hour_diff'] ?? null, $a['hour_diff'] ?? null)
                ?: (self::nullLastDesc($b['week_diff'] ?? null, $a['week_diff'] ?? null)
                    ?: ((int)$a['id'] <=> (int)$b['id'])),
            $limit,
            $getTag1,
            $getTag2,
            AppConfig::RANKING_WEEK_TABLE_NAME,
        );

        // Tier 4: member DESC → hour_diff DESC, member DESC
        $count = count($tier1) + count($tier2) + count($tier3);
        $excludeIds = array_merge($excludeIds, array_column($tier3, 'id'));
        $tier4Limit = $count < AppConfig::LIST_LIMIT_RECOMMEND
            ? ($count < (int)floor(AppConfig::LIST_LIMIT_RECOMMEND)
                ? (int)floor(AppConfig::LIST_LIMIT_RECOMMEND) - $count
                : 5)
            : 3;

        $tier4 = $this->buildMemberTier(
            $memberCandidateIds,
            $excludeIds,
            $tier4Limit,
            $getTag1,
            $getTag2,
        );

        $dto = new RecommendListDto(
            $type,
            $listName,
            $tier1,
            $tier2,
            $tier3,
            $tier4,
            $this->fileStorage->getContents('@hourlyCronUpdatedAtDatetime')
        );

        // 日本以外では関連タグを事前に取得しておく
        if (MimimalCmsConfig::$urlRoot !== '') {
            $list = array_column(
                $dto->getList(false, self::SORT_AND_UNIQUE_TAGS_LIST_LIMIT),
                'id'
            );

            $recommendTags = [];
            $ocTags = [];
            foreach ($list as $id) {
                if (isset($this->allData[$id])) {
                    $row = $this->allData[$id];
                    if ($row['recommend_tag'] !== null) {
                        $recommendTags[] = $row['recommend_tag'];
                    }
                    if ($row['oc_tag'] !== null) {
                        $ocTags[] = $row['oc_tag'];
                    }
                }
            }

            $dto->sortAndUniqueTags = sortAndUniqueArray(
                array_merge($recommendTags, $ocTags),
                self::SORT_AND_UNIQUE_ARRAY_MIN_COUNT
            );
        }

        return $dto;
    }

    /**
     * 指定条件でフィルタリング・ソートして行フォーマットを適用する
     *
     * @param int[] $candidateIds 対象ID群
     * @param int[] $excludeIds 除外ID群
     * @param string $diffColumn diff_memberのカラム名（hour_diff/hour24_diff/week_diff）
     * @param int $minDiff 最小差分値
     * @param \Closure $sortFn usort比較関数
     * @param int $limit 最大件数
     * @param \Closure $getTag1 tag1取得クロージャ
     * @param \Closure $getTag2 tag2取得クロージャ
     * @param string $tableName table_nameに設定する値
     * @return array フォーマット済み行の配列
     */
    private function filterAndSort(
        array $candidateIds,
        array $excludeIds,
        string $diffColumn,
        int $minDiff,
        \Closure $sortFn,
        int $limit,
        \Closure $getTag1,
        \Closure $getTag2,
        string $tableName,
    ): array {
        $excludeMap = array_flip($excludeIds);
        $filtered = [];

        foreach ($candidateIds as $id) {
            if (isset($excludeMap[$id])) continue;
            $row = $this->allData[$id] ?? null;
            if ($row === null) continue;
            if (($row[$diffColumn] ?? null) === null || (int)$row[$diffColumn] < $minDiff) continue;
            $filtered[] = $row;
        }

        usort($filtered, $sortFn);

        if (count($filtered) > $limit) {
            $filtered = array_slice($filtered, 0, $limit);
        }

        return array_map(
            fn(array $row) => $this->formatRow($row, $getTag1, $getTag2, $tableName),
            $filtered
        );
    }

    /**
     * Tier 4: member DESCで取得後、hour_diff DESC, member DESCで再ソート
     *
     * 旧SQLの条件: (rh.open_chat_id IS NOT NULL OR rh2.open_chat_id IS NOT NULL) OR oc.member >= 15
     * → hour_diffまたはhour24_diffが存在するか、member >= 15であること
     */
    private function buildMemberTier(
        array $candidateIds,
        array $excludeIds,
        int $limit,
        \Closure $getTag1,
        \Closure $getTag2,
    ): array {
        $excludeMap = array_flip($excludeIds);
        $filtered = [];

        foreach ($candidateIds as $id) {
            if (isset($excludeMap[$id])) continue;
            $row = $this->allData[$id] ?? null;
            if ($row === null) continue;
            // 旧SQLの条件を再現: (hour IS NOT NULL OR hour24 IS NOT NULL) OR member >= 15
            $hasHourOrHour24 = $row['hour_diff'] !== null || $row['hour24_diff'] !== null;
            if (!$hasHourOrHour24 && (int)$row['member'] < 15) continue;
            $filtered[] = $row;
        }

        // まずmember DESCでソートしてtopを取得（id ASCでタイブレーク）
        usort($filtered, fn(array $a, array $b) =>
            ((int)$b['member'] <=> (int)$a['member'])
            ?: ((int)$a['id'] <=> (int)$b['id'])
        );

        if (count($filtered) > $limit) {
            $filtered = array_slice($filtered, 0, $limit);
        }

        // 次にhour_diff DESC（NULL末尾）, member DESC, id ASCで再ソート
        usort($filtered, fn(array $a, array $b) =>
            self::nullLastDesc($b['hour_diff'] ?? null, $a['hour_diff'] ?? null)
            ?: (((int)$b['member'] <=> (int)$a['member'])
                ?: ((int)$a['id'] <=> (int)$b['id']))
        );

        return array_map(
            fn(array $row) => $this->formatRow($row, $getTag1, $getTag2, 'open_chat'),
            $filtered
        );
    }

    /**
     * 行データをAbstractRecommendRankingRepository::SelectPageと同じ形式にフォーマットする
     */
    private function formatRow(
        array $row,
        \Closure $getTag1,
        \Closure $getTag2,
        string $tableName,
    ): array {
        return [
            'id' => $row['id'],
            'name' => $row['name'],
            'img_url' => $row['img_url'],
            'api_img_url' => $row['api_img_url'],
            'member' => $row['member'],
            'description' => $row['description'],
            'emblem' => $row['emblem'],
            'category' => $row['category'],
            'emid' => $row['emid'],
            'url' => $row['url'],
            'api_created_at' => $row['api_created_at'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'join_method_type' => $row['join_method_type'],
            'tag1' => $getTag1($row),
            'tag2' => $getTag2($row),
            'table_name' => $tableName,
        ];
    }
}
