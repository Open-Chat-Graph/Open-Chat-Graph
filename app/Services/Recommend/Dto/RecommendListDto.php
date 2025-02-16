<?php

declare(strict_types=1);

namespace App\Services\Recommend\Dto;

use App\Config\AppConfig;
use App\Services\Recommend\Enum\RecommendListType;
use App\Services\Recommend\TagDefinition\Ja\RecommendUtility;
use App\Services\Recommend\TagDefinition\Ja\RecommendTagFilters;
use Shared\MimimalCmsConfig;

class RecommendListDto
{
    const TAG_LIMIT = 30;

    public int $maxMemberCount;
    public array $mergedElements;
    public ?array $shuffledMergedElements = null;
    public array $sortAndUniqueTags = [];

    /** @var array{ id:int,name:string,img_url:string,member:int,table_name:string,emblem:int } $list */
    function __construct(
        public RecommendListType $type,
        public string $listName,
        public array $hour,
        public array $day,
        public array $week,
        public array $member,
        public string $hourlyUpdatedAt
    ) {
        $this->mergedElements = array_merge($hour, $day, $week, $member);
        if (count($this->mergedElements) > AppConfig::LIST_LIMIT_RECOMMEND) {
            $this->mergedElements = array_slice($this->mergedElements, 0, AppConfig::LIST_LIMIT_RECOMMEND);
        }

        $elements = array_column($this->mergedElements, 'member');
        $this->maxMemberCount = $elements ? max($elements) : 0;
    }

    function getList(bool $shuffle = true, ?int $limit = 0, int $excludeId = 0): array
    {
        $limit = $limit === 0 ? AppConfig::$listLimitTopRanking : $limit;

        $elements = $shuffle ? $this->buildShuffledList() : $this->mergedElements;
        if ($excludeId) $elements = array_filter($elements, fn($el) => $el['id'] !== $excludeId);

        $result = $limit ? array_slice($elements, 0, $limit) : $elements;
        return $result;
    }

    /** @return array{ id:int,name:string,img_url:string,member:int,table_name:string,emblem:int }[] */
    private function buildShuffledList(): array
    {
        if (is_array($this->shuffledMergedElements) && $this->shuffledMergedElements)
            return $this->shuffledMergedElements;

        $hour = $this->hour;
        shuffle($hour);
        $length = $this->getSliceLength(count($hour));
        if (!$length) {
            $this->shuffledMergedElements = $hour;
            return $this->shuffledMergedElements;
        }

        $day = array_slice($this->day, 0, $length);
        shuffle($day);
        $length = $this->getSliceLength(count($hour) + count($day));
        if (!$length) {
            $this->shuffledMergedElements = array_merge($hour, $day);
            return $this->shuffledMergedElements;
        };

        $week = array_slice($this->week, 0, $length);
        shuffle($week);
        $length = $this->getSliceLength(count($hour) + count($day) + count($week));
        if (!$length) {
            $result = array_merge($day, $week);
            shuffle($result);
            $this->shuffledMergedElements = array_merge($hour, $result);
            return $this->shuffledMergedElements;
        };

        $member = array_slice($this->member, 0, $length);
        shuffle($member);

        $result = array_merge($day, $week);
        shuffle($result);

        $this->shuffledMergedElements = array_merge($hour, $result, $member);
        return $this->shuffledMergedElements;
    }

    private function getSliceLength(int $count)
    {
        $count = AppConfig::LIST_LIMIT_RECOMMEND - $count;
        return max($count, 0);
    }

    /** @return array{ id:int,name:string,img_url:string,member:int,table_name:string,emblem:int }[] */
    function getPreviewList(int $len): array
    {
        return array_slice($this->mergedElements, 0, $len);
    }

    function getCount(): int
    {
        return count($this->mergedElements);
    }

    /** @return string[] */
    function getFilterdTags(bool $shuffle = true, ?int $limit = 0): array
    {
        // 日本以外は取得済みの関連タグを返す
        if (MimimalCmsConfig::$urlRoot !== '') {
            $result = $this->type === RecommendListType::Tag
                ? array_filter($this->sortAndUniqueTags, fn($e) => $e !== $this->listName)
                : $this->sortAndUniqueTags;
        } else {
            $result = $this->buildFilterdTags($this->getList($shuffle, $limit), $shuffle);
        }

        return array_slice($result, 0, self::TAG_LIMIT);
    }

    /** @return string[] */
    function buildFilterdTags(
        array $mergedElements,
        bool $shuffle = false,
        array $filteredTagSort = RecommendTagFilters::FilteredTagSort
    ): array {
        $tag = $this->type === RecommendListType::Tag ? $this->listName : '';
        $tagName = $this->type === RecommendListType::Tag ? $this->listName : '';
        $tagStr = RecommendUtility::extractTag($tag);

        $sortAndUniqueTags = sortAndUniqueArray(
            array_merge(
                array_column($mergedElements, 'tag1'),
                array_column($mergedElements, 'tag2'),
                $filteredTagSort[$tag] ?? []
            ),
            1
        );

        $tags = array_filter(
            $sortAndUniqueTags,
            fn($e) => (
                !in_array($e, RecommendTagFilters::RecommendPageTagFilter)
                || (
                    isset($filteredTagSort[$tag])
                    && in_array($e, $filteredTagSort[$tag])
                )
            ) && $e !== $tagName
        );

        $tagsStr = array_map(fn($t) => RecommendUtility::extractTag($t), $tags);

        uksort($tags, function ($a) use ($tagStr, $tagsStr, $tag, $tags) {
            return str_contains(
                $tagsStr[$a],
                $tagStr
            ) || (
                isset($filteredTagSort[$tag])
                && in_array($tags[$a], $filteredTagSort[$tag])
            ) ? -1 : 1;
        });

        return $tags;
    }
}
