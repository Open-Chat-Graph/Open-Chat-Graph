<?php

/** @var \App\Services\Recommend\Dto\RecommendListDto $recommend */

use App\Config\AppConfig;
use App\Services\Recommend\Enum\RecommendListType;
use App\Services\Recommend\RecommendUtility;
use Shared\MimimalCmsConfig;

if ($recommend->type === RecommendListType::Category) {
    $title = "「{$recommend->listName}」カテゴリー";
} elseif ($recommend->type === RecommendListType::Official) {
    $title = "{$recommend->listName}";
} else {
    $title = "「{$recommend->listName}」";
}

?>

<article class="top-ranking not-rank" style="<?php echo $style ?? '' ?>">
    <header class="openchat-list-title-area unset">
        <div class="openchat-list-date unset ranking-url">
            <h2 class="unset">
                <span style="line-height: 1.5; font-size: 14px; color: #111; font-weight: bold;"><?php echo $title ?>のおすすめ</span>
            </h2>
        </div>
    </header>

    <?php viewComponent('open_chat_list_recommend', compact('recommend', 'id') + ['limit' => true, 'shuffle' => true]) ?>

    <?php if (isset($showTags) && $tags = $recommend->getFilterdTags(true)) : ?>
        <div>
            <div style="line-height: 1.5; font-size: 14px; color: #111; font-weight: bold;">関連のテーマ</div>
            <?php viewComponent('tag_list_section', compact('tags')) ?>
        </div>
    <?php endif ?>

    <?php if ($recommend->type === RecommendListType::Category) : ?>
        <a class="top-ranking-readMore unset ranking-url white-btn" href="<?php echo url('ranking/' . AppConfig::OPEN_CHAT_CATEGORY[MimimalCmsConfig::$urlRoot][htmlspecialchars_decode($recommend->listName)] . '?list=daily') ?>">
            <span class="ranking-readMore">「<?php echo $recommend->listName ?>」カテゴリーをもっと見る</span>
        </a>
    <?php elseif ($recommend->type === RecommendListType::Official) : ?>
        <a class="top-ranking-readMore unset ranking-url white-btn" href="<?php echo url('ranking?keyword=' . urlencode('badge:' . htmlspecialchars_decode($recommend->listName))) ?>">
            <span class="ranking-readMore"><?php echo $recommend->listName ?>をもっと見る</span>
        </a>
    <?php else : ?>
        <a class="top-ranking-readMore unset ranking-url white-btn" href="<?php echo url("recommend?tag=" . urlencode(htmlspecialchars_decode($recommend->listName))) ?>">
            <span class="ranking-readMore">「<?php echo RecommendUtility::extractTag($recommend->listName) ?>」をもっと見る</span>
        </a>
    <?php endif ?>

</article>