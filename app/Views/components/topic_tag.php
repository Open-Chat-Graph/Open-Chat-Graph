<?php

/** @var \App\Services\StaticData\Dto\StaticTopPageDto $topPageDto */

use App\Config\AppConfig;
use App\Services\Recommend\Dto\RecommendListDto;
use App\Services\Recommend\TagDefinition\Ja\RecommendUtility;
use Shared\MimimalCmsConfig;

$tags = $topPageDto->recommendList;
$topPageDto->hourlyUpdatedAt->setTimezone(new DateTimeZone(AppConfig::DATE_TIME_ZONE[MimimalCmsConfig::$urlRoot]));
$tagLimit = $tagLimit ?? RecommendListDto::TAG_LIMIT;

// 空の配列では無効
if (empty($tags['hour']) && empty($tags['hour24'])) {
    return;
}

function greenTag($word)
{
?>
    <li>
        <a class="hour tag-btn" href="<?php echo url('recommend/' . urlencode(htmlspecialchars_decode($word))) ?>">
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
                <?php if ($key + 1 <= $tagLimit): ?>
                    <?php greenTag($word) ?>
                <?php else: ?>
                    <?php break; ?>
                <?php endif ?>
            <?php endforeach ?>

            <?php foreach ($tags['hour24'] as $key2 => $word) : ?>
                <?php if (($key ?? -1) + $key2 + 2 <= $tagLimit): ?>
                    <li>
                        <a class="tag-btn" href="<?php echo url('recommend/' . urlencode(htmlspecialchars_decode($word))) ?>">
                            <?php echo RecommendUtility::extractTag($word) ?>
                        </a>
                    </li>
                <?php else: ?>
                    <?php break; ?>
                <?php endif ?>
            <?php endforeach ?>

            <?php if (($key ?? -1) + ($key2 ?? -1) + 2 >= 21) : ?>
                <li id="open-btn-li">
                    <button class="unset tag-btn open-btn" onclick="this.parentElement.parentElement.classList.toggle('open')"></button>
                </li>
            <?php endif ?>
        </ul>
    </div>
</article>