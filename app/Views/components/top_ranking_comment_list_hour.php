<?php

use App\Config\AppConfig;
use Shared\MimimalCmsConfig;

$hourlyTime = $dto->hourlyUpdatedAt->format(\DateTime::ATOM);
$dto->hourlyUpdatedAt->setTimezone(new DateTimeZone(AppConfig::DATE_TIME_ZONE[MimimalCmsConfig::$urlRoot]));
$hourlyEnd = $dto->hourlyUpdatedAt->format('G:i');
$dto->hourlyUpdatedAt->modify('-1hour');
$hourlyStart = $dto->hourlyUpdatedAt->format('G:i');
$_hourlyRange = $hourlyStart . '〜<time datetime="' . $hourlyTime . '">' . $hourlyEnd . '</time>';
?>

<article class="top-ranking">
    <header class="openchat-list-title-area unset">
        <div class="openchat-list-date unset ranking-url">
            <h2 class="unset">
                <span class="openchat-list-title"><?php echo t('1時間の人数増加ランキング') ?></span>
            </h2>
            <span style="font-weight: normal; color:#aaa; font-size:13px; margin: 0">
                <?php echo $_hourlyRange ?>
            </span>
        </div>
    </header>
    <?php viewComponent('open_chat_list_ranking', ['openChatList' => array_slice($dto->hourlyList, 0, AppConfig::$listLimitTopRanking), 'isHourly' => true, 'noReverse' => true, 'showReverseListMedal' => true]) ?>
    <a class="top-ranking-readMore unset ranking-url white-btn" href="<?php echo url('ranking?list=hourly') ?>">
        <span class="ranking-readMore"><?php echo t('1時間の人数増加ランキングをもっと見る') ?></span>
    </a>
</article>