<?php
$hourlyTime = $dto->hourlyUpdatedAt->format(\DateTime::ATOM);
$hourlyEnd = $dto->hourlyUpdatedAt->format('G:i');
$dto->hourlyUpdatedAt->modify('-1hour');
$hourlyStart = $dto->hourlyUpdatedAt->format('G:i');
$_hourlyRange = $hourlyStart . '〜<time datetime="' . $hourlyTime . '">' . $hourlyEnd . '</time>';
?>

<article class="top-ranking">
    <header class="openchat-list-title-area unset">
        <div class="openchat-list-date unset ranking-url">
            <h2 class="unset">
                <span class="openchat-list-title">1時間の人数増加ランキング</span>
            </h2>
            <span style="font-weight: normal; color:#aaa; font-size:13px; margin: 0">
                <?php echo $_hourlyRange ?>
            </span>
        </div>
    </header>
    <?php viewComponent('open_chat_list_ranking', ['openChatList' => $dto->hourlyList, 'isHourly' => true]) ?>
    <a class="top-ranking-readMore unset ranking-url" href="<?php echo url('ranking?list=hourly') ?>">
        <span class="ranking-readMore">もっと見る</span>
    </a>
</article>