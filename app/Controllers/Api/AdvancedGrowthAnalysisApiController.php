<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Config\AppConfig;
use App\Services\Analysis\AdvancedGrowthAnalysisService;
use Shadow\Kernel\Reception as Recp;
use Shadow\Kernel\Validator as Valid;
use Shared\Exceptions\BadRequestException as HTTP400;
use Shared\MimimalCmsConfig;

/**
 * 詳細成長分析（/labs/growth）の API。
 *
 * - status : 重い集計を 1 チャンク進めて進捗(%)を返す（クライアントが逐次ポーリングして待ち時間の目安を表示）。no-store。
 * - result : 完成済み結果を絞り込み・整形して返す。毎時更新のため同一 URL は checkLastModified で CDN がキャッシュ。
 *
 * 公開ページだが noindex（専門ユーザー向け）。
 */
class AdvancedGrowthAnalysisApiController
{
    public function __construct(
        private AdvancedGrowthAnalysisService $service,
    ) {}

    public function status()
    {
        noStore();
        Recp::$isJson = true;
        [$metric, $period, $from, $to] = $this->commonInputs();

        return response($this->service->advance($metric, $period, $from, $to));
    }

    public function result()
    {
        checkLastModified($this->service->hourlyUpdatedAt());

        Recp::$isJson = true;
        $error = HTTP400::class;
        [$metric, $period, $from, $to] = $this->commonInputs();

        $sort = Valid::str(Recp::input('sort', ''), emptyAble: true, regex: ['count', 'rate', 'score'], e: $error);
        $order = Valid::str(Recp::input('order', 'desc'), regex: ['asc', 'desc'], e: $error);
        $category = (int)Valid::str(Recp::input('category', '0'), regex: AppConfig::OPEN_CHAT_CATEGORY[MimimalCmsConfig::$urlRoot], e: $error);
        $keyword = Valid::str(Recp::input('keyword', ''), emptyAble: true, maxLen: 100, e: $error);
        $page = (int)Valid::num(Recp::input('page', 0), min: 0, e: $error);
        $limit = (int)Valid::num(Recp::input('limit', 100), min: 1, max: 5000, e: $error);

        return response($this->service->result($metric, $period, $from, $to, $sort, $order, $category, $keyword, $page, $limit));
    }

    /**
     * @return array{0:string, 1:string, 2:?string, 3:?string}
     */
    private function commonInputs(): array
    {
        $error = HTTP400::class;
        $metric = Valid::str(Recp::input('metric', 'increase'), regex: ['increase', 'steady'], e: $error);
        $period = Valid::str(Recp::input('period', 'year'), regex: ['month', '3month', '6month', 'year', 'all', 'custom'], e: $error);

        $from = Recp::input('from');
        $to = Recp::input('to');
        $from = ($from === '' || $from === null) ? null : (string)$from;
        $to = ($to === '' || $to === null) ? null : (string)$to;

        return [$metric, $period, $from, $to];
    }
}
