<?php

declare(strict_types=1);

namespace App\Services\Alpha;

use App\Config\AppConfig;
use App\Models\ApiRepositories\Alpha\AlphaStatsRepository;
use App\Services\Statistics\StatisticsChartArrayService;
use App\Views\Classes\Dto\RankingPositionChartArgDtoFactoryInterface;
use Shared\MimimalCmsConfig;

/**
 * Alpha SPA にグラフ埋め込みに必要なデータをまとめて返すサービス。
 *
 * 返却形式:
 *   array{
 *     scriptPath: string,
 *     chartArgDto: array,
 *     statsDto: array,
 *   }|null  — null は open_chat が存在しない場合
 */
class AlphaGraphEmbedService
{
    public function __construct(
        private AlphaStatsRepository $statsRepo,
        private StatisticsChartArrayService $statisticsChartArrayService,
        private RankingPositionChartArgDtoFactoryInterface $chartArgDtoFactory,
    ) {}

    /**
     * @return array{scriptPath: string, chartArgDto: array, statsDto: array|false}|null
     */
    public function build(int $open_chat_id): ?array
    {
        $oc = $this->statsRepo->findById($open_chat_id);
        if (!$oc) {
            return null;
        }

        // カテゴリ名を解決（OpenChatPageController と同じロジック）
        $categoryValue = $oc['category']
            ? array_search($oc['category'], AppConfig::OPEN_CHAT_CATEGORY[MimimalCmsConfig::$urlRoot])
            : null;
        $categoryName = $categoryValue !== false && $categoryValue !== null
            ? (string)$categoryValue
            : t('すべて');

        $chartArgDto = $this->chartArgDtoFactory->create($oc, $categoryName);

        // category を渡す（OpenChatPageController と同じ）。渡さないと positionAvailability の
        // カテゴリー内データ(ranking_in/rising_in)が常に false になり「カテゴリー内」トグルが常時グレーになる。
        $statsDto = $this->statisticsChartArrayService->buildStatisticsChartArray(
            $open_chat_id,
            isset($oc['category']) ? (int)$oc['category'] : null
        );

        $scriptPath = getFilePath('js/oc-app', 'graph-*.js');

        return [
            'scriptPath' => $scriptPath,
            'chartArgDto' => (array)$chartArgDto,
            'statsDto' => $statsDto !== false ? (array)$statsDto : null,
        ];
    }
}
