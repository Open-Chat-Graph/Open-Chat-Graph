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
 * View にロジックを置かないため、棚(🗂近いテーマ/🚀急上昇)と検索インデックスをここで確定する。
 *
 * 入力(getTagList の行)はコントローラ段階の生データ。各タグは RAW（未エスケープ）で扱い、
 * 表示名は extractTag、URL スラッグは urlencode で確定する。View へは `_discovery` で渡し、
 * フレームワークの自動エスケープを通さず View 側で明示的に扱う（二重エスケープ回避）。
 */
class ThemeDiscoveryService
{
    /* ページ文脈(同カテゴリ)を主役にし、汎用棚(急上昇)は控えめに絞る。
       羅列感を避けるため 2 棚合計 20 個以内に収める。 */
    private const SHELF_LIMIT = 12;
    private const TREND_LIMIT = 8;

    /**
     * @param array<int|string, array<int, array<string,mixed>>> $tagList カテゴリ別グループのタグ行
     * @param array<string, int> $relatedTags 現在タグの関連タグ => 共起スコア（RelatedTagsService の事前計算。無ければ空）
     */
    public function build(array $tagList, string $currentTag, ?StaticTopPageDto $topPageDto, array $relatedTags = []): ThemeDiscoveryDto
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
            return new ThemeDiscoveryDto([], [], '', '', []);
        }

        // 🗂 近いテーマ: ページ文脈の主役なので最初に確定する。
        // 1) 関連タグ（共起スコア順）を優先。タグ付けの優先順位次第でどちらにも転び得た部屋を
        //    共有するタグ = 意味的に近い（RelatedTagsService 参照）。
        // 2) 枠が余れば同カテゴリ（合計人数順）で補完する。
        $nearby = [];
        $nearbySet = [];
        foreach (array_keys($relatedTags) as $tag) {
            // 表示中の tagList に存在するタグだけ採用（ページが生きている保証 + 表示名/人数の整合）
            if (!isset($tagMember[$tag])) continue;
            $nearby[] = $tag;
            $nearbySet[$tag] = true;
            if (count($nearby) >= self::SHELF_LIMIT) break;
        }

        $nearbyCategoryName = '';
        if ($currentCategory !== null) {
            $categoryNames = array_flip(AppConfig::OPEN_CHAT_CATEGORY[MimimalCmsConfig::$urlRoot] ?? []);
            $nearbyCategoryName = (string)($categoryNames[$currentCategory] ?? '');
            if (count($nearby) < self::SHELF_LIMIT) {
                $sameCategory = [];
                foreach ($tagCategory as $tag => $categoryId) {
                    if ($categoryId === $currentCategory && !isset($nearbySet[$tag])) {
                        $sameCategory[$tag] = $tagMember[$tag] ?? 0;
                    }
                }
                arsort($sameCategory);
                foreach (array_keys($sameCategory) as $tag) {
                    $nearby[] = $tag;
                    $nearbySet[$tag] = true;
                    if (count($nearby) >= self::SHELF_LIMIT) break;
                }
            }
        }

        // 🚀 急上昇: topPageDto の hour / hour24（現在タグ・近いテーマと重複は除外）
        $trendingSet = [];
        $topTags = $topPageDto?->recommendList ?? [];
        foreach (['hour', 'hour24'] as $key) {
            foreach (($topTags[$key] ?? []) as $word) {
                $word = (string)$word;
                if ($word === '' || $word === $currentTag || isset($trendingSet[$word]) || isset($nearbySet[$word])) continue;
                $trendingSet[$word] = true;
                if (count($trendingSet) >= self::TREND_LIMIT) break 2;
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
            array_map($toItem, $nearby),
            $nearbyCategoryName,
            RecommendUtility::extractTag($currentTag),
            $searchIndex,
        );
    }
}
