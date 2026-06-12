<?php

declare(strict_types=1);

namespace App\Models\SQLite\Repositories\Statistics;

use App\Models\Repositories\Statistics\StatisticsRepositoryInterface;
use App\Models\SQLite\SQLiteInsertImporter;
use App\Models\SQLite\SQLiteStatistics;
use App\Services\OpenChat\Dto\OpenChatDto;

class SqliteStatisticsRepository implements StatisticsRepositoryInterface
{
    public function addNewOpenChatStatisticsFromDto(OpenChatDto $dto): void
    {
        SQLiteStatistics::execute(
            "INSERT INTO
                statistics (open_chat_id, member, date)
            VALUES
                (:open_chat_id, :member, :date)",
            $dto->getStatisticsParams()
        );
    }

    public function insertDailyStatistics(int $open_chat_id, int $member, string $date): void
    {
        $query =
            'INSERT OR IGNORE INTO statistics (open_chat_id, member, date)
            VALUES
                (:open_chat_id, :member, :date)';

        SQLiteStatistics::execute($query, compact('open_chat_id', 'member', 'date'));
    }

    public function deleteDailyStatistics(int $open_chat_id): void
    {
        SQLiteStatistics::execute(
            'DELETE FROM statistics WHERE open_chat_id = :open_chat_id',
            compact('open_chat_id')
        );
    }

    public function getNewRoomsWithLessThan8Records(): array
    {
        // 最適化版: レコード数が8以下の新規部屋を取得
        // 8700万行のテーブルで約5秒で実行完了
        $query =
            "SELECT open_chat_id
            FROM statistics
            GROUP BY open_chat_id
            HAVING COUNT(*) < 8";

        $mode = [\PDO::FETCH_COLUMN, 0];
        return SQLiteStatistics::fetchAll($query, null, $mode);
    }

    public function getMemberChangeWithinLastWeek(string $date): array
    {
        // 過去8日間でメンバー数が変動した部屋
        $query =
            "SELECT open_chat_id
            FROM statistics
            WHERE `date` BETWEEN DATE(:curDate, '-8 days') AND :curDate
            GROUP BY open_chat_id
            HAVING COUNT(DISTINCT member) > 1";

        $mode = [\PDO::FETCH_COLUMN, 0];
        return SQLiteStatistics::fetchAll($query, ['curDate' => $date], $mode);
    }

    public function getWeeklyUpdateRooms(string $date): array
    {
        // 1. 最後のレコードが1週間以上前の部屋（週次更新用）
        // 2. 昨日8日以上ぶりにクロールされ、かつメンバー数が変動した部屋（確認クロール用）
        //    前の週と同じ人数なら確認不要（引き続き週次）。
        //    変動があった場合のみ翌日の確認クロールで日次復帰を判定する。
        $query =
            "SELECT open_chat_id
            FROM statistics
            GROUP BY open_chat_id
            HAVING MAX(`date`) <= DATE(:curDate, '-7 days')

            UNION

            SELECT open_chat_id
            FROM statistics AS s_new
            WHERE s_new.`date` = DATE(:curDate, '-1 day')
              AND (
                  SELECT COUNT(*) FROM statistics s_count
                  WHERE s_count.open_chat_id = s_new.open_chat_id
                    AND s_count.`date` >= DATE(:curDate, '-8 days')
              ) = 1
              AND s_new.member != (
                  SELECT s_old.member FROM statistics s_old
                  WHERE s_old.open_chat_id = s_new.open_chat_id
                    AND s_old.`date` < DATE(:curDate, '-8 days')
                  ORDER BY s_old.`date` DESC
                  LIMIT 1
              )";

        $mode = [\PDO::FETCH_COLUMN, 0];
        return SQLiteStatistics::fetchAll($query, ['curDate' => $date], $mode);
    }

    public function insertMember(array $data): int
    {
        /**
         * @var SQLiteInsertImporter $inserter
         */
        $inserter = app(SQLiteInsertImporter::class);

        return $inserter->import(SQLiteStatistics::connect(), 'statistics', $data, 500);
    }

    public function getOpenChatIdArrayByDate(string $date): array
    {
        $query =
            "SELECT
                open_chat_id
            FROM
                statistics
            WHERE
                date = '{$date}'";

        return SQLiteStatistics::fetchAll($query, null, [\PDO::FETCH_COLUMN, 0]);
    }

    public function getMemberCount(int $open_chat_id, string $date): int|false
    {
        $query =
            "SELECT
                member
            FROM
                statistics
            WHERE
                open_chat_id = {$open_chat_id}
                AND date = '{$date}'";

        return SQLiteStatistics::fetchColumn($query);
    }

    public function getMemberMetricsForNarrative(int $open_chat_id, ?string $baseDate = null): array
    {
        // 「現在の基準日」。指定時は毎時クロール基準時刻 ('Y-m-d') を、null 時は SQLite 実行時刻
        // (date('now')、UTC) を使う。後者は深夜帯の UTC 日付差で最新日とズレ、m7 等が誤った日に
        // 丸まり diff7=0 になりうるため、呼び出し側から cron 基準日を渡せるようにした (後方互換)。
        $base = $baseDate !== null ? ':base_date' : "'now'";

        // 直近 200 日を母集団に、期間値・ピーク・単日最大伸びを 1 クエリで集約。
        // 単日最大伸びは daily member の前日差分 (LAG) で算出 (OHLC の close-open ではなく)。
        $query =
            "WITH o AS (
                SELECT date, member,
                       member - LAG(member) OVER (ORDER BY date ASC) AS day_diff
                  FROM statistics
                 WHERE open_chat_id = :open_chat_id
                   AND date >= date({$base}, '-200 days')
            )
            SELECT
                (SELECT member FROM o ORDER BY date DESC LIMIT 1) AS curr,
                (SELECT date   FROM o ORDER BY date DESC LIMIT 1) AS curr_date,
                (SELECT member FROM o WHERE date <= date({$base},'-1 days')  ORDER BY date DESC LIMIT 1) AS m1,
                (SELECT member FROM o WHERE date <= date({$base},'-7 days')  ORDER BY date DESC LIMIT 1) AS m7,
                (SELECT member FROM o WHERE date <= date({$base},'-30 days') ORDER BY date DESC LIMIT 1) AS m30,
                (SELECT member FROM o WHERE date <= date({$base},'-90 days') ORDER BY date DESC LIMIT 1) AS m90,
                (SELECT COUNT(*)    FROM o) AS sample_n,
                (SELECT MAX(member) FROM o) AS peak_high,
                (SELECT date FROM o ORDER BY member DESC, date DESC LIMIT 1) AS peak_date,
                (SELECT MAX(day_diff) FROM o) AS max_single_day_growth,
                (SELECT date FROM o WHERE day_diff = (SELECT MAX(day_diff) FROM o) ORDER BY date DESC LIMIT 1) AS max_growth_date,
                (SELECT MIN(date) FROM o) AS first_date,
                -- 全期間 (window 外含む) のピーク。長期縮小の検知に使う
                (SELECT MAX(member) FROM statistics WHERE open_chat_id = :open_chat_id) AS all_time_peak,
                (SELECT date FROM statistics WHERE open_chat_id = :open_chat_id ORDER BY member DESC, date DESC LIMIT 1) AS all_time_peak_date";

        $params = ['open_chat_id' => $open_chat_id];
        if ($baseDate !== null) {
            $params['base_date'] = $baseDate;
        }

        SQLiteStatistics::connect(SQLiteStatistics::WEB_READER);
        $row = SQLiteStatistics::fetch($query, $params);

        $empty = [
            'curr' => null, 'curr_date' => null,
            'm1' => null,
            'm7' => null, 'm30' => null, 'm90' => null,
            'sample_n' => 0,
            'peak_high' => null, 'peak_date' => null,
            'max_single_day_growth' => null, 'max_growth_date' => null,
            'first_date' => null,
            'all_time_peak' => null, 'all_time_peak_date' => null,
        ];
        if (!$row || !is_array($row)) {
            return $empty;
        }

        return [
            'curr'                  => $row['curr'] !== null ? (int)$row['curr'] : null,
            'curr_date'             => $row['curr_date'] !== null ? (string)$row['curr_date'] : null,
            'm1'                    => $row['m1']  !== null ? (int)$row['m1']  : null,
            'm7'                    => $row['m7']  !== null ? (int)$row['m7']  : null,
            'm30'                   => $row['m30'] !== null ? (int)$row['m30'] : null,
            'm90'                   => $row['m90'] !== null ? (int)$row['m90'] : null,
            'sample_n'              => (int)($row['sample_n'] ?? 0),
            'peak_high'             => $row['peak_high'] !== null ? (int)$row['peak_high'] : null,
            'peak_date'             => $row['peak_date'] !== null ? (string)$row['peak_date'] : null,
            'max_single_day_growth' => $row['max_single_day_growth'] !== null ? (int)$row['max_single_day_growth'] : null,
            'max_growth_date'       => $row['max_growth_date'] !== null ? (string)$row['max_growth_date'] : null,
            'first_date'            => $row['first_date'] !== null ? (string)$row['first_date'] : null,
            'all_time_peak'         => $row['all_time_peak'] !== null ? (int)$row['all_time_peak'] : null,
            'all_time_peak_date'    => $row['all_time_peak_date'] !== null ? (string)$row['all_time_peak_date'] : null,
        ];
    }
}
