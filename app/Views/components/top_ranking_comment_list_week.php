<article class="top-ranking">
    <header class="openchat-list-title-area unset">
        <div class="openchat-list-date unset ranking-url">
            <h2 class="unset">
                <span class="openchat-list-title"><?php echo t('1週間の人数増加ランキング') ?></span>
            </h2>
            <span style="font-weight: normal; color:#aaa; font-size:13px; margin: 0"><?php echo t('1日ごとに更新') ?></span>
        </div>
    </header>
    <?php viewComponent('open_chat_list_ranking', ['openChatList' => array_slice($dto->weeklyList, 0, \App\Config\AppConfig::$listLimitTopRanking), 'noReverse' => true, 'showReverseListMedal' => true]) ?>
    <a class="top-ranking-readMore unset ranking-url white-btn" href="<?php echo url('ranking?list=weekly') ?>">
        <span class="ranking-readMore"><?php echo t('1週間の人数増加ランキングをもっと見る') ?></span>
    </a>
</article>