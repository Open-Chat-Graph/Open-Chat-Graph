<article class="top-ranking created-at">
    <header class=" openchat-list-title-area unset">
        <div class="openchat-list-date unset ranking-url">
            <h2 class="unset">
                <span class="openchat-list-title">人数ランキング</span>
            </h2>
        </div>
    </header>
    <?php viewComponent('open_chat_list_ranking', ['openChatList' => $dto->popularList]) ?>
    <a class="top-ranking-readMore unset ranking-url white-btn" href="<?php echo url('ranking') ?>">
        <span class="ranking-readMore">人数ランキングをもっと見る</span>
    </a>
</article>