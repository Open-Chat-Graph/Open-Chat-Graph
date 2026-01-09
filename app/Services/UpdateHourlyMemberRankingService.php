<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\AppConfig;
use App\Models\Repositories\RankingPosition\HourMemberRankingUpdaterRepositoryInterface;
use App\Models\Repositories\RankingPosition\RankingPositionHourRepositoryInterface;
use App\Models\Repositories\Statistics\StatisticsRepositoryInterface;
use App\Services\Recommend\StaticData\RecommendStaticDataGenerator;
use App\Services\StaticData\StaticDataGenerator;

class UpdateHourlyMemberRankingService
{
    function __construct(
        private StaticDataGenerator $staticDataGenerator,
        private RecommendStaticDataGenerator $recommendStaticDataGenerator,
        private HourMemberRankingUpdaterRepositoryInterface $hourMemberRankingUpdaterRepository,
        private RankingPositionHourRepositoryInterface $rankingPositionHourRepository,
        private StatisticsRepositoryInterface $statisticsRepository,
    ) {}

    function update(bool $saveNextFiltersCache = true)
    {
        $time = $this->rankingPositionHourRepository->getLastHour();
        if (!$time) return;

        addVerboseCronLog('毎時メンバーランキングテーブルを更新中');
        $this->hourMemberRankingUpdaterRepository->updateHourRankingTable(
            new \DateTime($time),
            $this->getCachedFilters($time)
        );
        addVerboseCronLog('毎時メンバーランキングテーブル更新完了');

        $this->updateStaticData($time);

        if ($saveNextFiltersCache)
            $this->saveNextFiltersCache($time);
    }

    /**
     * dailyTask後にフィルターキャッシュを保存する
     *
     * ## パフォーマンス最適化: データ再利用
     *
     * getMemberChangeWithinLastWeekCacheArray()は全statisticsテーブル（8700万行）をスキャンする重い処理。
     * dailyTask時に以下の2箇所で実行される可能性がある:
     * 1. DailyUpdateCronService::getTargetOpenChatIdArray() - クローリング対象を絞るため
     * 2. このメソッド - キャッシュ保存のため
     *
     * ### 現在の実装: データ再利用（パフォーマンス優先）
     * - DailyUpdateCronServiceで取得したデータを引数で受け取る
     * - クエリを再実行せず、処理時間を短縮
     * - クエリ実行回数: 1回
     * - データ鮮度: クローリング前の状態（1日前のデータで妥協）
     *
     * ### 代替案: 最新データ取得（データ鮮度優先）
     * - 引数をnullにして、このメソッド内でクエリを実行
     * - クローリング後の最新状態をキャッシュに保存
     * - クエリ実行回数: 2回（処理時間が増加）
     * - データ鮮度: クローリング後の最新状態
     *
     * @param int[]|null $filterIds DailyUpdateCronServiceから取得したデータ（nullの場合は再取得）
     */
    function saveFiltersCacheAfterDailyTask(?array $filterIds = null): void
    {
        $time = $this->rankingPositionHourRepository->getLastHour();
        if (!$time) return;

        $date = (new \DateTime($time))->format('Y-m-d');

        // キャッシュの日付ファイルをチェック
        $cacheDateFilePath = AppConfig::getStorageFilePath('openChatHourFilterIdDate');
        $cachedDate = file_exists($cacheDateFilePath) ? file_get_contents($cacheDateFilePath) : false;

        // すでに今日のキャッシュがある場合はスキップ
        // dailyTaskが同じ日に複数回実行されても、データ取得は1回のみ
        if ($cachedDate === $date) {
            addCronLog('本日のフィルターキャッシュは更新済みのためスキップ');
            return;
        }

        // キャッシュを更新（変動がある部屋 + 新規部屋を含む全データ）
        if ($filterIds === null) {
            // 引数がない場合: クエリを実行して最新データを取得（データ鮮度優先）
            // クローリング後の最新状態を反映できるが、処理時間が増加
            addVerboseCronLog('過去1週間のメンバー変動データを取得中');
            $filterIds = $this->statisticsRepository->getMemberChangeWithinLastWeekCacheArray($date);
            addVerboseCronLog('過去1週間のメンバー変動データ取得完了');
        } else {
            // 引数がある場合: DailyUpdateCronServiceから渡されたデータを再利用（パフォーマンス優先）
            // クエリを再実行せず、処理時間を短縮（現在の実装）
            addCronLog('日次更新処理で取得済みのキャッシュデータを再利用');
        }

        // フィルターIDを保存
        saveSerializedFile(
            AppConfig::getStorageFilePath('openChatHourFilterId'),
            $filterIds
        );

        // 日付を保存
        safeFileRewrite($cacheDateFilePath, $date);
    }

    private function getCachedFilters(string $time)
    {
        // キャッシュから「変動がある部屋」を取得
        $cachedFilters = getUnserializedFile(AppConfig::getStorageFilePath('openChatHourFilterId'));

        // キャッシュがない場合は全て取得
        if (!$cachedFilters) {
            return $this->statisticsRepository->getHourMemberChangeWithinLastWeekArray((new \DateTime($time))->format('Y-m-d'));
        }

        // 「レコード8以下の新規部屋」を毎回取得してマージ（約5秒）
        // これにより新規ルームのリアルタイム性を確保
        $newRooms = $this->statisticsRepository->getNewRoomsWithLessThan8Records();

        return array_unique(array_merge($cachedFilters, $newRooms));
    }

    private function saveNextFiltersCache(string $time)
    {
        addVerboseCronLog('過去1週間の毎時メンバー変動データを取得中');
        saveSerializedFile(
            AppConfig::getStorageFilePath('openChatHourFilterId'),
            $this->statisticsRepository->getHourMemberChangeWithinLastWeekArray((new \DateTime($time))->format('Y-m-d')),
        );
        addVerboseCronLog('過去1週間の毎時メンバー変動データ取得完了');
    }

    private function updateStaticData(string $time)
    {
        safeFileRewrite(AppConfig::getStorageFilePath('hourlyCronUpdatedAtDatetime'), $time);

        addVerboseCronLog('ランキング静的データを生成中');
        $this->staticDataGenerator->updateStaticData();
        addVerboseCronLog('ランキング静的データ生成完了');

        addVerboseCronLog('おすすめ静的データを生成中');
        $this->recommendStaticDataGenerator->updateStaticData();
        addVerboseCronLog('おすすめ静的データ生成完了');
    }
}
