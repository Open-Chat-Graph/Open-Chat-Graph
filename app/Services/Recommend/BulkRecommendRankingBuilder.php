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
        );
    }

    /**
     * カテゴリ別ランキングを構築する
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
        );
    }

    /**
     * 公式ルーム別ランキングを構築する
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
     */
    private function buildRanking(
        RecommendListType $type,
        string $listName,
        array $statsCandidateIds,
        array $memberCandidateIds,
    ): RecommendListDto {
        $limit = AppConfig::LIST_LIMIT_RECOMMEND;

        // 24時間の人数増加が閾値以上の部屋を、24時間増の降順で（＝「いま伸びている」）。
        $growing = [];
        foreach ($statsCandidateIds as $id) {
            $row = $this->allData[$id] ?? null;
            if ($row === null) continue;
            if (($row['hour24_diff'] ?? null) === null || (int)$row['hour24_diff'] < AppConfig::RECOMMEND_MIN_MEMBER_DIFF_H24) continue;
            $growing[] = $row;
        }
        usort($growing, fn(array $a, array $b) =>
            ((int)$b['hour24_diff'] <=> (int)$a['hour24_diff'])
            ?: ((int)$a['id'] <=> (int)$b['id'])
        );
        $growing = array_slice($growing, 0, $limit);
        $growingRows = array_map(
            fn(array $row) => RecommendRowFormat::slim($row, AppConfig::RANKING_DAY_TABLE_NAME, (int)$row['hour24_diff']),
            $growing
        );

        // 伸びていない部屋は member 降順で裾を埋める（痩せタグ対策・既存の大型部屋）。
        // 表示は30件のままだが、/oc 関連ルームの人数絞り込み母集団として300件保持する。
        $member = $this->buildMemberTier(
            $memberCandidateIds,
            array_column($growing, 'id'),
            AppConfig::LIST_LIMIT_RECOMMEND_POOL,
        );

        // DTO は先頭(=表示順)に伸び部屋、末尾に裾を渡す（旧 hour/day/week の4段は廃止）。
        $dto = new RecommendListDto(
            $type,
            $listName,
            $growingRows,
            [],
            [],
            $member,
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
     * 伸びていない部屋を member DESC で裾埋めする（id ASC タイブレーク）。
     *
     * SQL条件と同一: (rh.open_chat_id IS NOT NULL OR rh2.open_chat_id IS NOT NULL)
     *   OR oc.member >= AppConfig::RECOMMEND_MIN_MEMBER_TIER4
     * → hour_diffまたはhour24_diffが存在するか、member が下限以上であること
     */
    private function buildMemberTier(
        array $candidateIds,
        array $excludeIds,
        int $limit,
    ): array {
        $excludeMap = array_flip($excludeIds);
        $filtered = [];

        foreach ($candidateIds as $id) {
            if (isset($excludeMap[$id])) continue;
            $row = $this->allData[$id] ?? null;
            if ($row === null) continue;
            // SQLランキング(BulkRankingDataRepository / 各RankingRepository)と同条件:
            //   (hour IS NOT NULL OR hour24 IS NOT NULL) OR member >= RECOMMEND_MIN_MEMBER_TIER4
            $hasHourOrHour24 = $row['hour_diff'] !== null || $row['hour24_diff'] !== null;
            if (!$hasHourOrHour24 && (int)$row['member'] < AppConfig::RECOMMEND_MIN_MEMBER_TIER4) continue;
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
            fn(array $row) => RecommendRowFormat::slim($row, 'open_chat', null),
            $filtered
        );
    }

}
