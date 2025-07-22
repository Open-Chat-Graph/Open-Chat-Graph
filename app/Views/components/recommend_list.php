<?php

/** @var \App\Services\Recommend\Dto\RecommendListDto $recommend */

use App\Config\AppConfig;
use App\Services\Recommend\Enum\RecommendListType;

?>
<div>
    <div style="margin: 0 -1rem;">
        <div class="btn-wrapper">
            <div class="inner">
                <?php if ($recommend->type === RecommendListType::Category) : ?>
                    <h3>
                        <div style="text-wrap: wrap;">「<?php echo $recommend->listName ?>」カテゴリーの</div>
                        <div>おすすめ</div>
                    </h3>
                <?php elseif ($recommend->type === RecommendListType::Official) : ?>
                    <?php if ($recommend->listName) : ?>
                        <h3>
                            <div style="text-wrap: wrap;"><?php echo $recommend->listName ?></div>
                        </h3>
                    <?php endif ?>
                <?php else : ?>
                    <a class="unset" href="<?php echo url("recommend/" . urlencode(htmlspecialchars_decode($recommend->listName))) ?>">
                        <h3>
                            <div style="text-wrap: wrap;">「<?php echo $recommend->listName ?>」の</div>
                            <div>おすすめ</div>
                        </h3>
                        <small style="font-size: 14px; font-weight:bold; color:#4d73ff; margin: auto 0; margin-left: 4px; text-wrap: nowrap; word-break: keep-all;" aria-hidden="true">すべて見る</small>
                    </a>
                <?php endif ?>
            </div>
            <button type="button" class="read-more-list-btn" onclick="this.textContent = this.parentElement.nextElementSibling.classList.toggle('show-all') ? '一部を表示' : 'もっと見る';">もっと見る</button>
        </div>
        <ul class="recommend-list">
            <?php foreach ($recommend->getList(false) as $roc) : ?>
                <li class="<?php if ($roc['id'] === $id) echo 'selected' ?>">
                    <a class="rectangle" <?php if ($roc['id'] !== $id) echo 'href="' . url('/oc/' . $roc['id']) . ($roc['table_name'] === AppConfig::RANKING_HOUR_TABLE_NAME || $roc['table_name'] === AppConfig::RANKING_DAY_TABLE_NAME ? '?limit=hour' : '') . '"' ?>>
                        <img loading="lazy" alt="<?php echo $roc['name'] ?>" src="<?php echo imgPreviewUrl($roc['id'], $roc['img_url']) ?>" />
                        <h4>
                            <?php if (($roc['emblem'] ?? 0) === 1) : ?><span class="super-icon sp" style="margin: 0; margin-right: -2px; margin-top: -3px; scale: 0.65;"></span><?php elseif (($roc['emblem'] ?? 0) === 2) : ?><span class="super-icon official" style="margin: 0; margin-right: -2px; margin-top: -3px; scale: 0.65;"></span><?php endif ?><?php if (($roc['join_method_type'] ?? 0) === 2) : ?><span class="lock-icon"></span><?php endif ?><?php echo $roc['name'] ?>
                        </h4>
                        <div class="recommend-member">
                            <span>
                                <?php if ($roc['member'] === $recommend->maxMemberCount && (($tag ?? '') !== $recommend->listName || $recommend->maxMemberCount >= $member)) : ?>
                                    <span aria-hidden="true" style="margin: 0 -2px; font-size: 9px; user-select: none;">🏆</span>
                                    <span style="font-weight: bold;">メンバー <?php echo formatMember($roc['member']) ?>人</span>
                                <?php else : ?>
                                    <span>メンバー <?php echo formatMember($roc['member']) ?>人</span>
                                <?php endif ?>
                                <?php if ($roc['table_name'] === AppConfig::RANKING_HOUR_TABLE_NAME) : ?>
                                    <span aria-hidden="true" style="margin: 0 -3px; font-size: 9px; user-select: none;">🔥</span>
                                <?php endif ?>
                                <?php if ($roc['table_name'] === AppConfig::RANKING_DAY_TABLE_NAME) : ?>
                                    <span aria-hidden="true" style="margin: 0 -2px; font-size: 9px; user-select: none;">🚀</span>
                                <?php endif ?>
                                <?php if ($roc['table_name'] === AppConfig::RANKING_WEEK_TABLE_NAME) : ?>
                                    <svg class="MuiSvgIcon-root MuiSvgIcon-fontSizeMedium show-north css-162gv95" focusable="false" aria-hidden="true" viewBox="0 0 24 24" data-testid="NorthIcon">
                                        <path d="m5 9 1.41 1.41L11 5.83V22h2V5.83l4.59 4.59L19 9l-7-7-7 7z"></path>
                                    </svg>
                                <?php endif ?>
                            </span>
                        </div>
                    </a>
                </li>
            <?php endforeach ?>
        </ul>
    </div>
    <?php if ($recommend->type === RecommendListType::Official) : ?>
        <a style="margin-top: 1rem;" class="top-ranking-readMore unset ranking-url" href="<?php echo url('ranking?list=hourly') ?>">
            <span class="ranking-readMore">もっと見る</span>
        </a>
    <?php endif ?>
</div>