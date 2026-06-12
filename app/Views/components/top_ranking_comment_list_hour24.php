<?php // 毎時変動するランキングをGoogle検索スニペットから除外。data-nosnippetはarticleに付けられない(div/section/spanのみ)ため外側をdivで包む ?>
<div data-nosnippet>
<article class="top-ranking">
    <header class="openchat-list-title-area unset">
        <div class="openchat-list-date unset ranking-url">
            <h2 class="unset">
                <span class="openchat-list-title"><?php echo t('24時間の人数増加ランキング') ?></span>
            </h2>
            <span style="font-weight: normal; color:var(--c-text-4); font-size:13px; margin: 0"><?php echo t('1時間ごとに更新') ?></span>
        </div>
    </header>
    <?php viewComponent('open_chat_list_ranking', ['openChatList' => array_slice($dto->dailyList, 0, \App\Config\AppConfig::$listLimitTopRanking), 'isHourly' => true, 'noReverse' => true, 'showReverseListMedal' => true]) ?>
    <a class="top-ranking-readMore unset ranking-url white-btn" href="<?php echo url('ranking?list=daily') ?>">
        <span class="ranking-readMore"><?php echo t('24時間の人数増加ランキングをもっと見る') ?></span>
    </a>
</article>
</div>