<article class="top-list">
    <header class="openchat-list-title-area unset">
        <div class="openchat-list-date unset ranking-url">
            <h2 class="unset">
                <span class="openchat-list-title">最近のコメント投稿</span>
            </h2>
        </div>
    </header>
    <div id="recent_comment">
        <?php viewComponent('open_chat_list_ranking_comment', ['openChatList' => $dto->recentCommentList]) ?>
    </div>
</article>