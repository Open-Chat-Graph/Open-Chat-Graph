<article class="top-ranking">
    <header class="openchat-list-title-area unset">
        <div class="openchat-list-date unset ranking-url">
            <h2 class="unset">
                <span class="openchat-list-title">1週間の人数増加ランキング</span>
            </h2>
            <span style="font-weight: normal; color:#aaa; font-size:13px; margin: 0">1日ごとに更新</span>
        </div>
    </header>
    <?php viewComponent('open_chat_list_ranking', ['openChatList' => $dto->weeklyList]) ?>
    <a class="top-ranking-readMore unset ranking-url" href="<?php echo url('ranking?list=weekly') ?>">
        <span class="ranking-readMore">1週間の人数増加ランキングをもっと見る</span>
    </a>
</article>

<hr class="hr-bottom">
<?php \App\Views\Ads\GoogleAdsence::output(\App\Views\Ads\GoogleAdsence::AD_SLOTS['siteSeparatorResponsive']) ?>

<article class="top-ranking created-at">
    <header class=" openchat-list-title-area unset">
        <div class="openchat-list-date unset ranking-url">
            <h2 class="unset">
                <span class="openchat-list-title">人数ランキング</span>
            </h2>
        </div>
    </header>
    <?php viewComponent('open_chat_list_ranking', ['openChatList' => $dto->popularList]) ?>
    <a class="top-ranking-readMore unset ranking-url" href="<?php echo url('ranking') ?>">
        <span class="ranking-readMore">人数ランキングをもっと見る</span>
    </a>
</article>