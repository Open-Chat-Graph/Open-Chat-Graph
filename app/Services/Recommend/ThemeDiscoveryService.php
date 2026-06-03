<?php

declare(strict_types=1);

namespace App\Services\Recommend;

use App\Config\AppConfig;
use App\Services\Recommend\Dto\ThemeDiscoveryDto;
use App\Services\Recommend\TagDefinition\Ja\RecommendUtility;
use App\Services\StaticData\Dto\StaticTopPageDto;
use Shared\MimimalCmsConfig;

/**
 * テーマ発見セクション（/recommend 着地客の回遊導線）の表示データを組み立てる。
 * View にロジックを置かないため、棚(🚀急上昇/🔥人気/🗂近いカテゴリ)と検索インデックスをここで確定する。
 *
 * 入力(getTagList の行)はコントローラ段階の生データ。各タグは RAW（未エスケープ）で扱い、
 * 表示名は extractTag、URL スラッグは urlencode で確定する。View へは `_discovery` で渡し、
 * フレームワークの自動エスケープを通さず View 側で明示的に扱う（二重エスケープ回避）。
 */
class ThemeDiscoveryService
{
    private const SHELF_LIMIT = 12;
    private const TREND_LIMIT = 14;

    /**
     * @param array<int|string, array<int, array<string,mixed>>> $tagList カテゴリ別グループのタグ行
     */
    public function build(array $tagList, string $currentTag, ?StaticTopPageDto $topPageDto): ThemeDiscoveryDto
    {
        // 全タグ平坦化: tag => 合計人数 / tag => カテゴリ。現在タグのカテゴリも特定。
        $tagMember = [];
        $tagCategory = [];
        $currentCategory = null;
        foreach ($tagList as $categoryId => $rows) {
            if (!is_array($rows)) continue;
            foreach ($rows as $row) {
                $tag = (string)($row['tag'] ?? '');
                if ($tag === '') continue;
                if (!array_key_exists($tag, $tagMember)) {
                    $tagMember[$tag] = (int)($row['total_member'] ?? 0);
                    $tagCategory[$tag] = $categoryId;
                }
                if ($tag === $currentTag) $currentCategory = $categoryId;
            }
        }
        unset($tagMember[$currentTag], $tagCategory[$currentTag]);
        if (!$tagMember) {
            return new ThemeDiscoveryDto([], [], [], '', []);
        }

        // 🚀 急上昇: topPageDto の hour / hour24（現在タグ除外）
        $trendingSet = [];
        $topTags = $topPageDto?->recommendList ?? [];
        foreach (['hour', 'hour24'] as $key) {
            foreach (($topTags[$key] ?? []) as $word) {
                $word = (string)$word;
                if ($word === '' || $word === $currentTag || isset($trendingSet[$word])) continue;
                $trendingSet[$word] = true;
                if (count($trendingSet) >= self::TREND_LIMIT) break 2;
            }
        }

        // 🔥 人気: 合計人数順（急上昇に出たものは除外）
        $byMember = $tagMember;
        arsort($byMember);
        $popular = [];
        foreach (array_keys($byMember) as $tag) {
            if (isset($trendingSet[$tag])) continue;
            $popular[] = $tag;
            if (count($popular) >= self::SHELF_LIMIT) break;
        }

        // 🗂 近いカテゴリ: 現在タグと同カテゴリ（合計人数順）
        $nearby = [];
        $nearbyCategoryName = '';
        if ($currentCategory !== null) {
            $categoryNames = array_flip(AppConfig::OPEN_CHAT_CATEGORY[MimimalCmsConfig::$urlRoot] ?? []);
            $nearbyCategoryName = (string)($categoryNames[$currentCategory] ?? '');
            $sameCategory = [];
            foreach ($tagCategory as $tag => $categoryId) {
                if ($categoryId === $currentCategory) $sameCategory[$tag] = $tagMember[$tag] ?? 0;
            }
            arsort($sameCategory);
            foreach (array_keys($sameCategory) as $tag) {
                $nearby[] = $tag;
                if (count($nearby) >= self::SHELF_LIMIT) break;
            }
        }

        $toItem = static fn(string $canonical): array => [
            'name' => RecommendUtility::extractTag($canonical),
            'slug' => urlencode($canonical),
        ];
        $searchIndex = array_map(
            static fn(string $canonical): array => [RecommendUtility::extractTag($canonical), urlencode($canonical)],
            array_keys($tagMember)
        );

        return new ThemeDiscoveryDto(
            array_map($toItem, array_keys($trendingSet)),
            array_map($toItem, $popular),
            array_map($toItem, $nearby),
            $nearbyCategoryName,
            $searchIndex,
        );
    }
}
