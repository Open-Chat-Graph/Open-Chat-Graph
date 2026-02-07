<article class="recent-comment-list">
    <header class="openchat-list-title-area unset">
        <div class="openchat-list-date unset ranking-url">
            <h2 class="unset">
                <span class="openchat-list-title"><?php echo $title ?? '最近のコメント投稿' ?></span>
            </h2>
        </div>
    </header>
    <div id="recent_comment">
        <?php viewComponent('open_chat_list_ranking_comment_dummy') ?>
    </div>
    <a class="top-ranking-readMore unset ranking-url" href="<?php echo url('comments-timeline') ?>">
        <span class="ranking-readMore">コメントのタイムラインを見る</span>
    </a>
</article>