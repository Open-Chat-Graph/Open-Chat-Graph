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
 * 詳細成長分析（/analysis）の API。
 *
 * - status : 重い計算を 1 チャンク進めて進捗(%)を返す（クライアントが逐次ポーリング）。no-store。
 * - result : 完成済み結果をフィルタ・ソート・スライスして返す。毎時更新のため CDN キャッシュ。
 * - cancel : 中間ファイルを掃除（ポーリング停止で計算自体は止まる）。no-store。
 *
 * 公開ページだが noindex（専門ユーザー向け・重いクエリ）。母集合は「現在存在する部屋」。
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
        // 毎時クロールでしか変わらない＝1時間は同一データ。同一 URL クエリは CDN(Cloudflare)が返す。
        // 304 で返せる場合は重いファイル読込前に exit する。
        checkLastModified($this->service->hourlyUpdatedAt());

        Recp::$isJson = true;
        $error = HTTP400::class;
        [$metric, $period, $from, $to] = $this->commonInputs();

        $sort = Valid::str(Recp::input('sort', ''), emptyAble: true, regex: ['count', 'rate', 'score', 'cagr', 'slope'], e: $error);
        $order = Valid::str(Recp::input('order', 'desc'), regex: ['asc', 'desc'], e: $error);
        $category = (int)Valid::str(Recp::input('category', '0'), regex: AppConfig::OPEN_CHAT_CATEGORY[MimimalCmsConfig::$urlRoot], e: $error);
        $keyword = Valid::str(Recp::input('keyword', ''), emptyAble: true, maxLen: 100, e: $error);
        $page = (int)Valid::num(Recp::input('page', 0), min: 0, e: $error);
        $limit = (int)Valid::num(Recp::input('limit', 100), min: 1, max: 5000, e: $error);

        return response($this->service->result($metric, $period, $from, $to, $sort, $order, $category, $keyword, $page, $limit));
    }

    public function cancel()
    {
        noStore();
        Recp::$isJson = true;
        [$metric, $period, $from, $to] = $this->commonInputs();
        $this->service->cancel($metric, $period, $from, $to);

        return response(['canceled' => true]);
    }

    /**
     * status / result / cancel 共通の指標・期間入力。from/to の妥当性はサービスで判定する。
     *
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
