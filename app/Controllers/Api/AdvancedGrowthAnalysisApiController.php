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
 * 詳細成長分析（/analysis）の API。1 本だけ。
 *
 * その場で計算して返すだけ（サーバに保存しない）。毎時更新のため同一 URL クエリは
 * checkLastModified で CDN(Cloudflare) がキャッシュする＝重い計算は (条件×時間帯) ごとに 1 回。
 * 公開ページだが noindex（専門ユーザー向け）。
 */
class AdvancedGrowthAnalysisApiController
{
    public function __construct(
        private AdvancedGrowthAnalysisService $service,
    ) {}

    public function result()
    {
        // 1時間は同一データ。同一 URL は CDN が返し、304 なら計算前に exit する。
        checkLastModified($this->service->hourlyUpdatedAt());

        Recp::$isJson = true;
        $error = HTTP400::class;

        $metric = Valid::str(Recp::input('metric', 'increase'), regex: ['increase', 'steady'], e: $error);
        $period = Valid::str(Recp::input('period', 'year'), regex: ['month', '3month', '6month', 'year', 'all', 'custom'], e: $error);
        $sort = Valid::str(Recp::input('sort', ''), emptyAble: true, regex: ['count', 'rate', 'score'], e: $error);
        $order = Valid::str(Recp::input('order', 'desc'), regex: ['asc', 'desc'], e: $error);
        $category = (int)Valid::str(Recp::input('category', '0'), regex: AppConfig::OPEN_CHAT_CATEGORY[MimimalCmsConfig::$urlRoot], e: $error);
        $keyword = Valid::str(Recp::input('keyword', ''), emptyAble: true, maxLen: 100, e: $error);
        $page = (int)Valid::num(Recp::input('page', 0), min: 0, e: $error);
        $limit = (int)Valid::num(Recp::input('limit', 100), min: 1, max: 5000, e: $error);

        $from = Recp::input('from');
        $to = Recp::input('to');
        $from = ($from === '' || $from === null) ? null : (string)$from;
        $to = ($to === '' || $to === null) ? null : (string)$to;

        return response($this->service->search($metric, $period, $from, $to, $sort, $order, $category, $keyword, $page, $limit));
    }
}
