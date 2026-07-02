<?php

declare(strict_types=1);

namespace App\Services\TikTokVideo;

use App\Config\AppConfig;
use App\Models\Repositories\DB;
use App\Services\Statistics\StatisticsChartArrayService;
use Shared\MimimalCmsConfig;

/**
 * TikTok 動画用ペイロード（デイリー急上昇 TOP N ＋各ルームの30日メンバー数系列）を組み立てる。
 *
 * 本番 cron（batch/exec/tiktok_video_dispatch.php）から呼ばれ、結果は GitHub repository_dispatch の
 * client_payload として送出される。形式は TikTokRisingVideoService::generate() の入力と対
 * （レンダリング側は DB に触れないため、必要なデータをここで全部詰める）。
 *
 * ランキングは statistics_ranking_hour24（24時間のメンバー増加数）を増加数降順で取る
 * （/ranking ページの「デイリー・増加数順」と同じ母集合）。
 */
class TikTokVideoPayloadBuilder
{
    /** 30日系列の取得日数（OGP カードと同じ） */
    private const SERIES_DAYS = 30;

    public function __construct(
        private StatisticsChartArrayService $chartService,
    ) {}

    /**
     * @return array<string,mixed> TikTokRisingVideoService::generate() が受け取る形のペイロード
     */
    public function build(int $limit = 5): array
    {
        $rows = DB::fetchAll(
            "SELECT
                oc.id,
                oc.name,
                oc.member,
                oc.img_url,
                sr.diff_member,
                sr.percent_increase
            FROM
                open_chat AS oc
                JOIN " . AppConfig::RANKING_DAY_TABLE_NAME . " AS sr ON oc.id = sr.open_chat_id
            ORDER BY
                sr.diff_member DESC
            LIMIT " . (int)$limit
        );

        $rooms = [];
        foreach ($rows as $row) {
            $dto = $this->chartService->buildStatisticsChartArray(
                (int)$row['id'],
                null,
                date('Y-m-d', time() - (self::SERIES_DAYS - 1) * 86400),
                date('Y-m-d'),
            );

            $rooms[] = [
                'id' => (int)$row['id'],
                'name' => (string)$row['name'],
                'member' => (int)$row['member'],
                'increase' => (int)$row['diff_member'],
                'percent' => $row['percent_increase'] !== null ? (float)$row['percent_increase'] : null,
                'iconUrl' => imgPreviewUrl($row['img_url']),
                'dates' => $dto->date ?? [],
                'series' => $dto->member ?? [],
            ];
        }

        return [
            'version' => 1,
            'urlRoot' => MimimalCmsConfig::$urlRoot,
            'generatedAt' => date('Y-m-d H:i:s'),
            'listType' => 'daily',
            'rooms' => $rooms,
        ];
    }
}
