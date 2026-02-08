<?php

declare(strict_types=1);

namespace App\Services\RankingBan;

use App\Config\AppConfig;
use App\Models\Importer\SqlInsert;
use App\Models\Repositories\DB;
use App\Models\Repositories\RankingPosition\RankingPositionHourPageRepositoryInterface;
use App\Models\Repositories\RankingPosition\RankingPositionHourRepositoryInterface;
use App\Models\Repositories\RankingPosition\RankingPositionPageRepositoryInterface;
use App\Models\Repositories\Statistics\StatisticsRepositoryInterface;
use App\Services\Cron\Utility\CronUtility;
use App\Services\OpenChat\Updater\OpenChatUpdaterFromApi;
use App\Services\OpenChat\Utility\OpenChatServicesUtility;
use Shared\MimimalCmsConfig;

class RankingBanTableUpdater
{
    public \DateTime $time;

    function __construct(
        private RankingPositionPageRepositoryInterface $rankingPositionRepo,
        private RankingPositionHourPageRepositoryInterface $rankingPositionHourRepo,
        private StatisticsRepositoryInterface $statisticsRepo,
        private SqlInsert $sqlInsert,
        private OpenChatUpdaterFromApi $openChatUpdaterFromApi,
        private ProgressNotifier $notifier,
        private RankingPositionHourRepositoryInterface $rankingPositionHourRepository,
        mixed $time = null
    ) {
        $this->time = $time instanceof \DateTime ? $time : OpenChatServicesUtility::getModifiedCronTime('now');
    }

    private function buildTableData(
        array $openChatArray,
        \DateTime $latestTime,
        array $existsListArray
    ): array {
        $result = [];

        $existsIdArray = array_column($existsListArray, 'open_chat_id');
        foreach ($openChatArray as $ocArrayKey => $oc) {
            $id = $oc['id'];
            $member = $oc['member'];

            $ranking = $this->rankingPositionHourRepo->getFinalRankingPosition($id, $oc['category']);
            if ($ranking && new \DateTime($ranking['time']) >= $latestTime) continue;

            if (in_array($id, $existsIdArray)) {
                unset($existsListArray[array_search($id, $existsIdArray)]);
                continue;
            }

            $rankingDay = $this->rankingPositionRepo->getFinalRankingPosition($id, $oc['category']);
            if (!$rankingDay) continue;

            if ($ranking) {
                $rankingHourTime = new \DateTime($ranking['time']);
                $rankingHourTime->modify('+1hour');
                $datetime = $rankingHourTime->format('Y-m-d H:i:s');
            } else {
                $datetime = substr($rankingDay['time'], 0, 10);
                $member = $this->statisticsRepo->getMemberCount($id, $datetime) ?: $member;
            }

            $percentage = round($rankingDay['position'] / $rankingDay['total_count_ranking'] * 100);
            if ($percentage > 100) $percentage = 100;
            if ($percentage < 1) $percentage = 1;

            $result[] = compact(
                'datetime',
                'percentage',
                'member'
            ) + [
                'open_chat_id' => $id,
                'updated_at' => 0,
                'update_items' => null
            ];
        }

        return [$result, $existsListArray];
    }

    private function updateTable(\DateTime $latestTime, array $deleteListArray)
    {
        $endDateTime = $latestTime->format('Y-m-d H:i:s');

        // 進捗通知を開始
        $this->notifier->setTotalCount(count($deleteListArray));
        $this->notifier->notifyStart('RankingBanTableUpdater::updateTable');

        foreach ($deleteListArray as $row) {
            $id = $row['open_chat_id'];
            $datetime = $row['datetime'];

            $oc = DB::fetch(
                "SELECT
                    updated_at,
                    update_items
                FROM
                    open_chat
                WHERE
                    id = {$id}"
            );

            if ($oc && $oc['update_items'] && new \DateTime($oc['updated_at']) >= new \DateTime($datetime)) {
                DB::execute(
                    "UPDATE 
                        ranking_ban 
                    SET 
                        flag = 1,
                        end_datetime = '{$endDateTime}',
                        update_items = :update_items
                    WHERE
                        open_chat_id = {$id}
                        AND flag = 0",
                    ['update_items' => $oc['update_items']]
                );
            } else {
                DB::connect()->exec(
                    "UPDATE 
                        ranking_ban 
                    SET 
                        flag = 1,
                        end_datetime = '{$endDateTime}'
                    WHERE
                        open_chat_id = {$id}
                        AND flag = 0"
                );
            }

            // 進捗通知
            $this->notifier->incrementAndNotify('RankingBanTableUpdater::updateTable');
        }
    }

    function crawlUpdateDeleteOpenChat(array $ocArray, \DateTime $latestTime)
    {
        $latestOcIdArray = [];
        foreach ($ocArray as $key => $oc) {
            $datetime = new \DateTime($oc['datetime']);

            if ($datetime < $latestTime) continue;

            $latestOcIdArray[$key] = $oc['open_chat_id'];
        }

        // 進捗通知を開始
        $this->notifier->setTotalCount(count($latestOcIdArray));
        $this->notifier->notifyStart('RankingBanTableUpdater::crawlUpdateDeleteOpenChat');

        foreach ($latestOcIdArray as $key => $id) {
            $this->openChatUpdaterFromApi->fetchUpdateOpenChat($id, false);

            $oc = DB::fetch(
                "SELECT
                    updated_at,
                    update_items
                FROM
                    open_chat
                WHERE
                    id = {$id}"
            );

            // 進捗通知
            $this->notifier->incrementAndNotify('RankingBanTableUpdater::crawlUpdateDeleteOpenChat');

            if (!$oc) continue;
            if (!$oc['update_items'] || new \DateTime($oc['updated_at']) < $latestTime) continue;

            $ocArray[$key]['updated_at'] = 1;
            $ocArray[$key]['update_items'] = $oc['update_items'];
        }

        return $ocArray;
    }

    function updateRankingBanTable(?\DateTime $crawlLatestTime = null)
    {
        // 日本以外の場合、更新をスキップする
        if (MimimalCmsConfig::$urlRoot !== '') {
            return;
        }

        $lastTime = (new \DateTime($this->time->format('Y-m-d H:i:s')))->modify('-1 hour')->format('Y-m-d H:i:s');
        $dbTime = $this->rankingPositionHourRepository->getLastHour(1);
        if ((!$crawlLatestTime) && ($dbTime !== $lastTime)) {
            return;
        }

        $openChatArray = DB::fetchAll(
            "SELECT
                oc.id,
                oc.category,
                oc.member
            FROM
                open_chat AS oc
                LEFT JOIN statistics_ranking_hour AS h24 ON oc.id = h24.open_chat_id
            WHERE
                h24.id IS NULL
                AND oc.api_created_at IS NOT NULL
                AND oc.category IS NOT NULL
                AND oc.category != 0"
        );

        $existsListArray = DB::fetchAll(
            "SELECT
                open_chat_id,
                datetime
            FROM 
                ranking_ban
            WHERE 
                flag = 0"
        );

        // 開発環境の場合、更新制限をかける
        if (AppConfig::$isDevlopment) {
            $limit = AppConfig::$developmentEnvUpdateLimit['RankingBanTableUpdater'] ?? 1;
            $openChatArrayCount = count($openChatArray);
            $openChatArray = array_slice($openChatArray, 0, $limit);
            $existsListArray = array_slice($existsListArray, 0, $limit);
            CronUtility::addCronLog("Development environment. Update limit: {$limit} / {$openChatArrayCount}");
        }

        [$insertOcArray, $deleteListArray] = $this->buildTableData($openChatArray, $this->time, $existsListArray);

        $this->updateTable($this->time, $deleteListArray);

        $result = $this->crawlUpdateDeleteOpenChat($insertOcArray, $crawlLatestTime ?? $this->time);

        $this->sqlInsert->import(DB::connect(), 'ranking_ban', $result);
    }
}
