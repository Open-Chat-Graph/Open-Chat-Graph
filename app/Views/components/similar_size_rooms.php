<?php

use App\Config\AppConfig;
use Shared\MimimalCmsConfig;

/** @var array $rooms */
/** @var \App\Services\Recommend\Dto\RecommendListDto|null $recommend */
/** @var string $mode */
/** @var int $currentMember */
/** @var int|null $category */
/** @var string|null $tag */

$categoryName = '';
if ($category !== null && isset(AppConfig::OPEN_CHAT_CATEGORY[MimimalCmsConfig::$urlRoot])) {
    $catMap = array_flip(AppConfig::OPEN_CHAT_CATEGORY[MimimalCmsConfig::$urlRoot]);
    $categoryName = $catMap[(int)$category] ?? '';
}

$tag = $tag ?? null;
$recommend = $recommend ?? null;

if (!function_exists('similarSizeRoundMember')) {
    function similarSizeRoundMember(int $m): int
    {
        if ($m < 100) return max(10, (int)round($m / 10) * 10);
        if ($m < 1000) return (int)round($m / 100) * 100;
        if ($m < 10000) return (int)round($m / 1000) * 1000;
        return (int)round($m / 10000) * 10000;
    }
}

$roundedMember = similarSizeRoundMember($currentMember);

$isTagMode = $mode === 'tag_member' || $mode === 'tag_top';
$scope = $isTagMode ? ($tag ?? '') : $categoryName;
?>

<?php
// tag_top は recommend 上位の流用なので順位＋メダルを表示。
// tag_member / cat_member は「人数が近い」軸の並びなので順位は意味を持たず、付けない。
$isRanked = $mode === 'tag_top';
?>
<?php // おすすめ枠をGoogle検索スニペットから除外。data-nosnippetはarticleに付けられない(div/section/spanのみ)ため外側をdivで包む ?>
<div data-nosnippet>
<article class="top-ranking<?php echo $isRanked ? '' : ' not-rank' ?>" style="margin-top: 24px;" aria-labelledby="similar-size-rooms-title">
    <header style="display: block; margin: 0 0 12px 0;">
        <h2 id="similar-size-rooms-title" style="display: block; margin: 0; padding: 0; font-size: 15px; font-weight: bold; color: var(--c-text-1); line-height: 1.4;">
            <?php if ($mode === 'tag_top' && $scope !== ''): ?>
                <?php echo sprintfT('「%s」でいま人数が伸びているルーム', htmlspecialchars($scope, ENT_QUOTES, 'UTF-8')) ?>
            <?php elseif ($scope !== ''): ?>
                <?php echo sprintfT('メンバー%s人前後の「%s」のルーム', number_format($roundedMember), htmlspecialchars($scope, ENT_QUOTES, 'UTF-8')) ?>
            <?php else: ?>
                <?php echo t('関連するオープンチャット') ?>
            <?php endif ?>
        </h2>
    </header>

    <?php viewComponent('open_chat_list_recommend', [
        'listArray'        => $rooms,
        'recommend'        => $recommend,
        'showListMedal'    => $isRanked,
        'showApiCreatedAt' => true,
        'hideIncrease'     => true,
    ]) ?>

    <?php if ($isTagMode && $tag !== null && $tag !== ''): ?>
        <a class="top-ranking-readMore unset ranking-url white-btn" href="<?php echo url('recommend/' . urlencode($tag)) ?>">
            <span class="ranking-readMore"><?php echo sprintfT('「%s」をもっと見る', htmlspecialchars($tag, ENT_QUOTES, 'UTF-8')) ?></span>
        </a>
    <?php elseif ($categoryName !== '' && isset(AppConfig::OPEN_CHAT_CATEGORY[MimimalCmsConfig::$urlRoot][$categoryName])): ?>
        <a class="top-ranking-readMore unset ranking-url white-btn" href="<?php echo url('ranking/' . AppConfig::OPEN_CHAT_CATEGORY[MimimalCmsConfig::$urlRoot][$categoryName] . '?list=daily') ?>">
            <span class="ranking-readMore"><?php echo sprintfT('「%s」カテゴリーをもっと見る', htmlspecialchars($categoryName, ENT_QUOTES, 'UTF-8')) ?></span>
        </a>
    <?php endif ?>
</article>
</div>
