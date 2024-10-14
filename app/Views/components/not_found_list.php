<main style="padding-top: 0; padding-bottom: 0;">

    <hr class="hr-top">
    <article class="top-ranking">
        <header class="openchat-list-title-area unset">
            <div class="openchat-list-date unset ranking-url">
                <h2 class="unset">
                    <span class="openchat-list-title">Hourly</span>
                </h2>
                <div class="refresh-time">
                    <span>1時間ランキング (<?php echo $hourlyRange ?>)</span>
                </div>
            </div>
        </header>
        <?php /** @var \App\Services\StaticData\Dto\StaticTopPageDto $dto */
        viewComponent('open_chat_list_ranking', ['openChatList' => $dto->hourlyList, 'isHourly' => true]) ?>
        <a class="top-ranking-readMore unset ranking-url" href="<?php echo url('ranking?list=hourly') ?>">
            <span class="ranking-readMore">1時間ランキングをすべて見る</span>
        </a>
    </article>

    <article class="top-ranking">
        <header class="openchat-list-title-area unset">
            <div class="openchat-list-date unset ranking-url">
                <h2 class="unset">
                    <span class="openchat-list-title">Daily</span>
                </h2>
                <div class="refresh-time">
                    <span>24時間ランキング</span>
                </div>
            </div>
        </header>
        <?php viewComponent('open_chat_list_ranking', ['openChatList' => $dto->dailyList]) ?>
        <a class="top-ranking-readMore unset ranking-url" href="<?php echo url('ranking?list=daily') ?>">
            <span class="ranking-readMore">24時間ランキングをすべて見る</span>
        </a>
    </article>

    <article class="top-ranking">
        <header class="openchat-list-title-area unset">
            <div class="openchat-list-date unset ranking-url">
                <h2 class="unset">
                    <span class="openchat-list-title">Weekly</span>
                </h2>
                <div class="refresh-time">
                    <span>1週間ランキング</span>
                </div>
            </div>
        </header>
        <?php viewComponent('open_chat_list_ranking', ['openChatList' => $dto->weeklyList]) ?>
        <a class="top-ranking-readMore unset ranking-url" href="<?php echo url('ranking?list=weekly') ?>">
            <span class="ranking-readMore">1週間ランキングをすべて見る</span>
        </a>
    </article>

    <article class="top-ranking created-at">
        <header class="openchat-list-title-area unset">
            <div class="openchat-list-date unset ranking-url">
                <h2 class="unset">
                    <span class="openchat-list-title">Popular</span>
                </h2>
                <div class="refresh-time">
                    <span>メンバー数ランキング</span>
                </div>
                <span style="font-weight: normal; color:#b7b7b7; font-size:11.5px; margin: 0">※公式運営を除く</span>
            </div>
        </header>
        <?php viewComponent('open_chat_list_ranking', ['openChatList' => $dto->popularList]) ?>
        <a class="top-ranking-readMore unset ranking-url" href="<?php echo url('ranking') ?>">
            <span class="ranking-readMore">メンバー数ランキングをすべて見る</span>
        </a>
    </article>
</main>