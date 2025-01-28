<?php

/** @var \App\Services\StaticData\Dto\StaticTopPageDto $topPageDto */

use App\Services\Recommend\TagDefinition\Ja\RecommendUtility;
use App\Views\Ads\GoogleAdsence as GAd;

$tags = $topPageDto->recommendList;

// 空の配列では無効
if (empty($tags['hour']) && empty($tags['hour24'])) {
    return;
}

function greenTag($word)
{
?>
    <li>
        <a class="hour tag-btn" href="<?php echo url('recommend?tag=' . urlencode(htmlspecialchars_decode($word))) ?>">
            <?php echo RecommendUtility::extractTag($word) ?>
            <svg class="MuiSvgIcon-root MuiSvgIcon-fontSizeMedium show-north css-162gv95" focusable="false" aria-hidden="true" viewBox="0 0 24 24" data-testid="NorthIcon">
                <path d="m5 9 1.41 1.41L11 5.83V22h2V5.83l4.59 4.59L19 9l-7-7-7 7z"></path>
            </svg>
        </a>
    </li>
<?php
}

?>

<article class="top-ranking topic-tag">
    <div>
        <header class="openchat-list-title-area unset" style="margin-bottom: 0px;">
            <div class="openchat-list-date unset ranking-url">
                <h2 class="unset">
                    <span class="openchat-list-title"><?php echo t('いま人数急増中のテーマ') ?></span>
                    <span aria-hidden="true" style="font-size: 9px; user-select: none; margin-bottom: px;margin-left: -3px;">🚀</span>
                </h2>
                <span style="font-weight: normal; color:#aaa; font-size:13px; margin: 0"><?php echo $topPageDto->hourlyUpdatedAt->format('G:i') ?></span>
            </div>
        </header>

        <ul class="tag-list">
            <?php $hourCount = count($tags['hour']); ?>

            <?php foreach ($tags['hour'] as $key => $word) : ?>
                <?php greenTag($word) ?>
            <?php endforeach ?>

            <?php foreach ($tags['hour24'] as $key => $word) : ?>
                <li>
                    <a class="tag-btn" href="<?php echo url('recommend?tag=' . urlencode(htmlspecialchars_decode($word))) ?>">
                        <?php echo RecommendUtility::extractTag($word) ?>
                    </a>
                </li>
            <?php endforeach ?>

            <?php if (count($tags['hour']) + count($tags['hour24']) > 21) : ?>
                <li id="open-btn-li">
                    <button class="unset tag-btn open-btn" onclick="this.parentElement.parentElement.classList.toggle('open')"></button>
                </li>
            <?php endif ?>
        </ul>
    </div>
</article>