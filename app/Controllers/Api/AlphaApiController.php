<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Config\AppConfig;
use App\Models\ApiRepositories\Alpha\AlphaOpenChatRepository;
use App\Models\ApiRepositories\Alpha\AlphaPeriodGrowthRepository;
use App\Models\ApiRepositories\Alpha\AlphaStatsRepository;
use App\Models\ApiRepositories\Alpha\AlphaAlertRepository;
use App\Models\ApiRepositories\Alpha\AlphaAccessRankingRepository;
use App\Models\ApiRepositories\Alpha\AlphaSearchTimingRepository;
use App\Models\ApiRepositories\OpenChatApiArgs;
use App\Models\RankingBanRepositories\RankingBanPageRepository;
use App\Services\Alpha\AlphaInsightsService;
use App\Services\Auth\AuthInterface;
use App\Models\Repositories\DB;
use Shadow\Kernel\Reception;
use Shadow\Kernel\Validator;
use Shared\Exceptions\BadRequestException;
use Shared\MimimalCmsConfig;

class AlphaApiController
{
    private OpenChatApiArgs $args;

    function __construct(OpenChatApiArgs $argsObj)
    {
        $this->args = $argsObj;
    }

    /**
     * カテゴリIDから名前を取得
     */
    private function getCategoryName(int $categoryId): string
    {
        $categories = AppConfig::OPEN_CHAT_CATEGORY[''];
        foreach ($categories as $name => $id) {
            if ($id === $categoryId) {
                return $name;
            }
        }
        return '';
    }

    /**
     * 検索API
     * GET /alpha-api/search?keyword=xxx&category=0&page=0&limit=20&sort=member&order=desc
     */
    function search(AlphaOpenChatRepository $repo, AlphaSearchTimingRepository $timingRepo)
    {
        $error = BadRequestException::class;
        Reception::$isJson = true;

        // バリデーション
        $this->args->page = Validator::num(Reception::input('page', 0), min: 0, e: $error);
        $this->args->limit = Validator::num(Reception::input('limit', 20), min: 1, max: 100, e: $error);
        $this->args->category = (int)Validator::str(
            (string)Reception::input('category', '0'),
            regex: AppConfig::OPEN_CHAT_CATEGORY[MimimalCmsConfig::$urlRoot],
            e: $error
        );
        $this->args->order = Validator::str(Reception::input('order', 'desc'), regex: ['asc', 'desc'], e: $error);
        $this->args->sort = Validator::str(
            Reception::input('sort', 'member'),
            regex: ['member', 'created_at', 'hourly_diff', 'diff_24h', 'diff_1w'],
            e: $error
        );

        $keyword = Validator::str(Reception::input('keyword', ''), emptyAble: true, maxLen: 1000, e: $error);
        if ($keyword) {
            $this->args->keyword = $keyword;
        }

        // ETA計測: 実処理(クエリ)の wall time を測り、query_key で upsert する。
        // 失敗してもETAは付加機能なので握りつぶす（検索本体は止めない）。
        $startMs = microtime(true);

        // ソート条件に応じて適切なリポジトリメソッドを呼ぶ（1回のクエリで全データ取得）
        switch ($this->args->sort) {
            case 'hourly_diff':
                // 1時間でソート
                $data = $repo->findByStatsRanking($this->args, 'statistics_ranking_hour');
                break;

            case 'diff_24h':
                // 24時間でソート
                $data = $repo->findByStatsRanking($this->args, 'statistics_ranking_hour24');
                break;

            case 'diff_1w':
                // 1週間でソート
                $data = $repo->findByStatsRanking($this->args, 'statistics_ranking_week');
                break;

            case 'created_at':
            case 'member':
            default:
                // メンバー数または作成日でソート
                $data = $repo->findByMemberOrCreatedAt($this->args);
                break;
        }

        $elapsedMs = (int)round((microtime(true) - $startMs) * 1000);
        try {
            $timingRepo->record(
                $this->buildSearchKey($keyword, $this->args->category, $this->args->sort, $this->args->order),
                $elapsedMs
            );
        } catch (\Throwable $e) {
            // ETA記録失敗は無視
        }

        $totalCount = $data[0]['totalCount'] ?? 0;

        if (empty($data)) {
            return response([
                'data' => [],
                'totalCount' => 0,
            ]);
        }

        // レスポンスを整形
        $responseData = $this->formatResponse($data);

        return response([
            'data' => $responseData,
            'totalCount' => $totalCount,
        ]);
    }

    /**
     * レスポンスをフロントエンドインターフェイスに合わせて整形
     */
    private function formatResponse(array $data): array
    {
        $result = [];

        foreach ($data as $item) {
            // totalCountキーをスキップ
            if (!isset($item['id'])) {
                continue;
            }

            // URLをLINE形式に変換
            $lineUrl = '';
            if (!empty($item['url'])) {
                // すでに完全なURLの場合はそのまま使用
                if (strpos($item['url'], 'http') === 0) {
                    $lineUrl = $item['url'];
                } else {
                    // ハッシュのみの場合は https://line.me/ti/g2/{hash} 形式に変換
                    $hash = trim($item['url'], '/');
                    if (!empty($hash)) {
                        $lineUrl = AppConfig::LINE_APP_URL . $hash;
                    }
                }
            }

            $result[] = [
                'id' => (int)$item['id'],
                'name' => $item['name'],
                'desc' => $item['description'] ?? '',
                'member' => (int)$item['member'],
                'img' => $item['img_url'] ?? '',
                'emblem' => (int)$item['emblem'],
                'category' => (int)$item['category'],
                'categoryName' => $this->getCategoryName((int)$item['category']),
                'join_method_type' => (int)$item['join_method_type'],

                // 1時間の差分（nullの場合はN/A表示）
                'increasedMember' => $item['hourly_diff'] !== null ? (int)$item['hourly_diff'] : null,
                'percentageIncrease' => $item['hourly_percent'] !== null ? (float)$item['hourly_percent'] : null,

                // 24時間の差分（nullの場合はN/A表示）
                'diff24h' => $item['daily_diff'] !== null ? (int)$item['daily_diff'] : null,
                'percent24h' => $item['daily_percent'] !== null ? (float)$item['daily_percent'] : null,

                // 1週間の差分（nullの場合はN/A表示）
                'diff1w' => $item['weekly_diff'] !== null ? (int)$item['weekly_diff'] : null,
                'percent1w' => $item['weekly_percent'] !== null ? (float)$item['weekly_percent'] : null,

                // ランキング掲載判定
                'isInRanking' => isset($item['is_in_ranking']) ? (bool)$item['is_in_ranking'] : false,

                // 作成日と登録日
                'createdAt' => !empty($item['created_at']) ? strtotime($item['created_at']) : null,
                'registeredAt' => $item['api_created_at'] ?? '',

                // LINE URL
                'url' => $lineUrl,
            ];
        }

        return $result;
    }

    /**
     * 基本情報取得API（軽量）
     * GET /alpha-api/stats/{open_chat_id}
     */
    function stats(
        AlphaStatsRepository $statsRepo,
        int $open_chat_id
    ) {
        // MySQLから基本データ取得のみ
        $ocData = $statsRepo->findById($open_chat_id);

        if (!$ocData) {
            return response(['error' => 'OpenChat not found'], 404);
        }

        // URLをLINE形式に変換
        $lineUrl = '';
        if (!empty($ocData['url'])) {
            // すでに完全なURLの場合はそのまま使用
            if (strpos($ocData['url'], 'http') === 0) {
                $lineUrl = $ocData['url'];
            } else {
                // ハッシュのみの場合は https://line.me/ti/g2/{hash} 形式に変換
                $hash = trim($ocData['url'], '/');
                if (!empty($hash)) {
                    $lineUrl = AppConfig::LINE_APP_URL . $hash;
                }
            }
        }

        return response([
            'id' => $open_chat_id,
            'name' => $ocData['name'],
            'currentMember' => (int)$ocData['member'],
            'category' => (int)$ocData['category'],
            'categoryName' => $this->getCategoryName((int)$ocData['category']),
            'description' => $ocData['description'] ?? '',
            'thumbnail' => $ocData['img_url'] ?? '',
            'emblem' => (int)($ocData['emblem'] ?? 0),
            'hourlyDiff' => $ocData['hourly_diff_member'] !== null ? (int)$ocData['hourly_diff_member'] : null,
            'hourlyPercentage' => $ocData['hourly_percent_increase'] !== null ? (float)$ocData['hourly_percent_increase'] : null,
            'diff24h' => $ocData['daily_diff_member'] !== null ? (int)$ocData['daily_diff_member'] : null,
            'percent24h' => $ocData['daily_percent_increase'] !== null ? (float)$ocData['daily_percent_increase'] : null,
            'diff1w' => $ocData['weekly_diff_member'] !== null ? (int)$ocData['weekly_diff_member'] : null,
            'percent1w' => $ocData['weekly_percent_increase'] !== null ? (float)$ocData['weekly_percent_increase'] : null,
            'isInRanking' => isset($ocData['is_in_ranking']) ? (bool)$ocData['is_in_ranking'] : false,
            'createdAt' => $ocData['created_at'] ? strtotime($ocData['created_at']) : null,
            'registeredAt' => $ocData['api_created_at'] ?? '',
            'joinMethodType' => (int)($ocData['join_method_type'] ?? 0),
            'url' => $lineUrl,
        ]);
    }

    /**
     * グラフデータ取得API（重い処理）
     * GET /alpha-api/stats/{open_chat_id}/graph?bar=ranking&rankingCategory=all
     */
    function graphData(
        AlphaStatsRepository $statsRepo,
        int $open_chat_id,
        string $bar = '',
        string $rankingCategory = 'all'
    ) {
        // SQLiteから統計データ取得
        $statsData = $statsRepo->getStatisticsData($open_chat_id);
        $dates = $statsData['dates'];
        $members = $statsData['members'];

        // ランキングデータ取得（barパラメータがrankingまたはrisingの場合）
        $rankings = [];
        if ($bar === 'ranking' || $bar === 'rising') {
            // カテゴリー情報を取得（ランキングデータに必要）
            $ocData = $statsRepo->findById($open_chat_id);
            if ($ocData) {
                // カテゴリー判定（all=0, category=オープンチャットのカテゴリー）
                $category = $rankingCategory === 'all' ? 0 : (int)$ocData['category'];
                $rankings = $statsRepo->getRankingData($open_chat_id, $category, $bar, $dates);
            }
        }

        return response([
            'dates' => $dates,
            'members' => $members,
            'rankings' => $rankings,
        ]);
    }

    /**
     * マイリスト用一括統計取得API
     * POST /alpha-api/batch-stats
     * Body: {"ids": [123, 456, 789]}
     */
    function batchStats(AlphaStatsRepository $statsRepo, Reception $reception)
    {
        $json = $reception->input();

        if (!isset($json['ids']) || !is_array($json['ids'])) {
            throw new BadRequestException('ids parameter is required and must be an array');
        }

        $ids = array_map('intval', $json['ids']);

        // 最大50件に制限
        if (count($ids) > 50) {
            throw new BadRequestException('Maximum 50 IDs allowed');
        }

        if (empty($ids)) {
            return response(['data' => []]);
        }

        // リポジトリから一括取得
        $data = $statsRepo->findByIds($ids);

        // レスポンスを整形
        $result = [];
        foreach ($data as $item) {
            // URLをLINE形式に変換
            $lineUrl = '';
            if (!empty($item['url'])) {
                // すでに完全なURLの場合はそのまま使用
                if (strpos($item['url'], 'http') === 0) {
                    $lineUrl = $item['url'];
                } else {
                    // ハッシュのみの場合は https://line.me/ti/g2/{hash} 形式に変換
                    $hash = trim($item['url'], '/');
                    if (!empty($hash)) {
                        $lineUrl = AppConfig::LINE_APP_URL . $hash;
                    }
                }
            }

            $result[] = [
                'id' => (int)$item['id'],
                'name' => $item['name'],
                'desc' => $item['description'] ?? '',
                'member' => (int)$item['member'],
                'img' => $item['img_url'] ?? '',
                'emblem' => (int)$item['emblem'],
                'category' => (int)$item['category'],
                'categoryName' => $this->getCategoryName((int)$item['category']),
                'join_method_type' => (int)$item['join_method_type'],

                // 1時間の差分（nullの場合はN/A表示）
                'increasedMember' => $item['hourly_diff'] !== null ? (int)$item['hourly_diff'] : null,
                'percentageIncrease' => $item['hourly_percent'] !== null ? (float)$item['hourly_percent'] : null,

                // 24時間の差分（nullの場合はN/A表示）
                'diff24h' => $item['daily_diff'] !== null ? (int)$item['daily_diff'] : null,
                'percent24h' => $item['daily_percent'] !== null ? (float)$item['daily_percent'] : null,

                // 1週間の差分（nullの場合はN/A表示）
                'diff1w' => $item['weekly_diff'] !== null ? (int)$item['weekly_diff'] : null,
                'percent1w' => $item['weekly_percent'] !== null ? (float)$item['weekly_percent'] : null,

                // ランキング掲載判定
                'isInRanking' => isset($item['is_in_ranking']) ? (bool)$item['is_in_ranking'] : false,

                // 作成日と登録日
                'createdAt' => !empty($item['created_at']) ? strtotime($item['created_at']) : null,
                'registeredAt' => $item['api_created_at'] ?? '',

                // LINE URL
                'url' => $lineUrl,
            ];
        }

        return response([
            'data' => $result,
        ]);
    }

    /**
     * ランキング掲載履歴取得API
     * GET /alpha-api/ranking-history/{open_chat_id}
     */
    function rankingHistory(RankingBanPageRepository $rankingBanRepo, int $open_chat_id)
    {
        Reception::$isJson = true;

        // 現在のメンバー数を取得
        $currentMemberSql = "SELECT member FROM open_chat WHERE id = :id";
        $currentMember = DB::fetchColumn($currentMemberSql, ['id' => $open_chat_id]);

        // 履歴データ取得
        $history = $rankingBanRepo->findHistoryByOpenChatId($open_chat_id);

        // レスポンス整形
        $result = array_map(function ($item) use ($currentMember) {
            return [
                'datetime' => $item['datetime'],
                'endDatetime' => $item['end_datetime'],
                'status' => $item['end_datetime'] === null ? '未掲載' : '再掲載済み',
                'hasContentChange' => $item['updated_at'] >= 1 || !empty($item['update_items']),
                'updateItems' => $item['update_items'] ?? [],
                'member' => (int)$item['member'],
                'currentMember' => (int)$currentMember,
                'memberDiff' => (int)$currentMember - (int)$item['member'],
                'percentage' => (int)$item['percentage'],
                // 「N位 / M位」表示用（古いレコードは未保存=null → フロントは percentage にフォールバック）
                'position' => isset($item['ranking_position']) ? (int)$item['ranking_position'] : null,
                'totalCount' => isset($item['ranking_total']) ? (int)$item['ranking_total'] : null,
            ];
        }, $history);

        return response([
            'data' => $result,
        ]);
    }

    /**
     * 高次の考察 取得API
     * GET /alpha-api/insights/{open_chat_id}
     *
     * 既存の数値・グラフ・掲載履歴では一目で分からない高次の洞察だけを
     * 構造化配列で返す。データ不足の洞察は配列に含めない（黙る）。
     */
    function insights(
        AlphaStatsRepository $statsRepo,
        AlphaInsightsService $insightsService,
        int $open_chat_id
    ) {
        Reception::$isJson = true;

        // カテゴリ取得（存在しないルームは 404）
        $ocData = $statsRepo->findById($open_chat_id);
        if (!$ocData) {
            return response(['error' => 'OpenChat not found'], 404);
        }

        $category = isset($ocData['category']) && $ocData['category'] !== null
            ? (int)$ocData['category']
            : null;

        $result = $insightsService->generate($open_chat_id, $category);

        return response($result);
    }

    /**
     * 任意のN日増減 検索API
     * GET /alpha-api/period-growth?keyword=xxx&category=0&days=30&order=desc&limit=20
     *
     * キーワード(＋カテゴリ)一致のうち「N日前と現在のどちらにも統計がある」
     * ルームに絞り、その期間のメンバー増減でソートして返す。
     */
    function periodGrowth(AlphaPeriodGrowthRepository $repo)
    {
        $error = BadRequestException::class;
        Reception::$isJson = true;

        // キーワードは任意（空なら全件対象。リポジトリ側で member 降順の候補プール上限内で扱う）。
        // emptyAble:true で空文字は default('') を返す（既定は空文字で例外→404になるため）。
        $keyword = (string)Validator::str(Reception::input('keyword', ''), maxLen: 1000, emptyAble: true, e: $error);

        $category = (int)Validator::str(
            (string)Reception::input('category', '0'),
            regex: AppConfig::OPEN_CHAT_CATEGORY[MimimalCmsConfig::$urlRoot],
            e: $error
        );
        $days = Validator::num(Reception::input('days', 30), min: 1, max: 365, e: $error);
        $order = Validator::str(Reception::input('order', 'desc'), regex: ['asc', 'desc'], e: $error);
        $limit = Validator::num(Reception::input('limit', 20), min: 1, max: 100, e: $error);

        // 任意の期間指定（両方そろっていれば days より優先）
        $startDate = $this->validDateOrNull(Reception::input('startDate', ''));
        $endDate = $this->validDateOrNull(Reception::input('endDate', ''));

        if ($startDate !== null && $endDate !== null) {
            $result = $repo->findPeriodGrowthByDateRange($keyword, $category, $startDate, $endDate, $order, (int)$limit);
        } else {
            $result = $repo->findPeriodGrowth($keyword, $category, (int)$days, $order, (int)$limit);
        }

        $data = [];
        foreach ($result['data'] as $row) {
            $item = $row['candidate'];

            // URLをLINE形式に変換（search/batchStats と同じロジック）
            $lineUrl = '';
            if (!empty($item['url'])) {
                if (strpos($item['url'], 'http') === 0) {
                    $lineUrl = $item['url'];
                } else {
                    $hash = trim($item['url'], '/');
                    if (!empty($hash)) {
                        $lineUrl = AppConfig::LINE_APP_URL . $hash;
                    }
                }
            }

            $data[] = [
                'id' => (int)$item['id'],
                'name' => $item['name'],
                'desc' => $item['description'] ?? '',
                // 現在のメンバー数（open_chat の最新値）
                'member' => (int)$item['member'],
                'img' => $item['img_url'] ?? '',
                'emblem' => (int)$item['emblem'],
                'category' => (int)$item['category'],
                'categoryName' => $this->getCategoryName((int)$item['category']),
                'join_method_type' => (int)$item['join_method_type'],

                // N日増減（statistics: 基準日 − N日前）
                'diff' => (int)$row['diff'],
                'percent' => (float)$row['percent'],
                'pastMember' => (int)$row['pastMember'],

                // 比較に用いた実日付（要求日と最寄りの実データ日）
                'pastDate' => $row['pastDateActual'],
                'baseDate' => $row['baseDateActual'],

                // 作成日と登録日
                'createdAt' => !empty($item['created_at']) ? strtotime($item['created_at']) : null,
                'registeredAt' => $item['api_created_at'] ?? '',

                'url' => $lineUrl,
            ];
        }

        return response([
            'data' => $data,
            'days' => $result['days'],
            'totalMatched' => $result['totalMatched'],
            // リスト全体の基準日/狙ったN日前日付（フロント表示用）
            'baseDate' => $result['baseDate'],
            'targetPastDate' => $result['pastDate'],
        ]);
    }

    /**
     * アクセス数ランキング（Labs）
     * GET /alpha-api/access-ranking?category=0&days=30&order=desc&limit=20
     *
     * alpha_room_access_daily の直近N日ページビュー合計でソートして返す。
     * データが無い間（creds未投入/未集計）は data:[], updatedAt:null を 200 で返す。
     */
    function accessRanking(AlphaAccessRankingRepository $repo)
    {
        $error = BadRequestException::class;
        Reception::$isJson = true;

        $category = (int)Validator::str(
            (string)Reception::input('category', '0'),
            regex: AppConfig::OPEN_CHAT_CATEGORY[MimimalCmsConfig::$urlRoot],
            e: $error
        );
        $days = Validator::num(Reception::input('days', 30), min: 1, max: 365, e: $error);
        $order = Validator::str(Reception::input('order', 'desc'), regex: ['asc', 'desc'], e: $error);
        $limit = Validator::num(Reception::input('limit', 20), min: 1, max: 100, e: $error);

        $result = $repo->getAccessRanking($category, (int)$days, $order, (int)$limit);

        $data = [];
        foreach ($result['data'] as $item) {
            $data[] = $this->formatRankingRoom($item) + [
                'pageviews' => (int)($item['pageviews'] ?? 0),
                'activeUsers' => (int)($item['active_users'] ?? 0),
            ];
        }

        // トップ/おすすめページも含めた上位（rooms とは別枠）
        $pages = $repo->getPageScopeRanking((int)$days, $order, (int)$limit, 'pageviews');

        return response([
            'data' => $data,
            'pages' => $pages,
            'days' => $result['days'],
            'baseDate' => $result['baseDate'],
            'updatedAt' => $result['updatedAt'],
        ]);
    }

    /**
     * 検索流入(SEO)ランキング（Labs）
     * GET /alpha-api/search-ranking?category=0&days=30&order=desc&limit=20
     *
     * alpha_room_access_daily の直近N日 検索クリック合計でソートして返す。
     * 各部屋に searchClicks / searchImpressions / searchPosition を付ける。
     */
    function searchRanking(AlphaAccessRankingRepository $repo)
    {
        $error = BadRequestException::class;
        Reception::$isJson = true;

        $category = (int)Validator::str(
            (string)Reception::input('category', '0'),
            regex: AppConfig::OPEN_CHAT_CATEGORY[MimimalCmsConfig::$urlRoot],
            e: $error
        );
        $days = Validator::num(Reception::input('days', 30), min: 1, max: 365, e: $error);
        $order = Validator::str(Reception::input('order', 'desc'), regex: ['asc', 'desc'], e: $error);
        $limit = Validator::num(Reception::input('limit', 20), min: 1, max: 100, e: $error);

        $result = $repo->getSearchRanking($category, (int)$days, $order, (int)$limit);

        $data = [];
        foreach ($result['data'] as $item) {
            $position = isset($item['search_position']) && $item['search_position'] !== null
                ? round((float)$item['search_position'], 2)
                : null;
            $data[] = $this->formatRankingRoom($item) + [
                'searchClicks' => (int)($item['search_clicks'] ?? 0),
                'searchImpressions' => (int)($item['search_impressions'] ?? 0),
                'searchPosition' => $position,
                'activeUsers' => (int)($item['active_users'] ?? 0),
            ];
        }

        // トップ/おすすめページも含めた上位（rooms とは別枠。検索クリック順）
        $pages = $repo->getPageScopeRanking((int)$days, $order, (int)$limit, 'search_clicks');

        return response([
            'data' => $data,
            'pages' => $pages,
            'days' => $result['days'],
            'baseDate' => $result['baseDate'],
            'updatedAt' => $result['updatedAt'],
        ]);
    }

    /**
     * 検索クエリランキング（Labs）
     * GET /alpha-api/search-query-ranking?days=30&limit=20
     *
     * alpha_search_query_daily の直近N日 検索クリック合計でソートして上位クエリを返す。
     */
    function searchQueryRanking(AlphaAccessRankingRepository $repo)
    {
        $error = BadRequestException::class;
        Reception::$isJson = true;

        $days = Validator::num(Reception::input('days', 30), min: 1, max: 365, e: $error);
        $limit = Validator::num(Reception::input('limit', 20), min: 1, max: 100, e: $error);

        $result = $repo->getSearchQueryRanking((int)$days, (int)$limit);

        return response([
            'data' => $result['data'],
            'days' => $result['days'],
            'updatedAt' => $result['updatedAt'],
        ]);
    }

    /**
     * 詳細画面のGA/GSC指標 取得API
     * GET /alpha-api/room-metrics/{open_chat_id}?days=30
     *
     * alpha_room_access_daily を直近N日で集計して1部屋の指標を返す。
     * データが無い間（creds未投入/未集計）は 0 / null を 200 で返す。
     */
    function roomMetrics(AlphaAccessRankingRepository $repo, int $open_chat_id)
    {
        $error = BadRequestException::class;
        Reception::$isJson = true;

        // 期間: days（既定30）／ start・end（Y-m-d 範囲）／ all=1（全期間）。範囲は alpha_room_access_daily の MIN〜MAX 基準で解決。
        $days = Validator::num(Reception::input('days', 30), min: 1, max: 3650, e: $error);
        $start = trim((string)Reception::input('start', ''));
        $end = trim((string)Reception::input('end', ''));
        $all = (string)Reception::input('all', '') === '1';
        $win = $repo->resolveWindow($start, $end, (int)$days, $all);

        $m = $repo->getRoomMetrics($open_chat_id, $win['fromDate'], $win['toDate']);
        $seoIndirect = $repo->getRoomIndirectSeo($open_chat_id, $win['fromDate'], $win['toDate']);
        $searchQueries = $repo->getRoomSearchQueries($open_chat_id, $win['fromDate'], $win['toDate'], 20);
        $referrerRows = $repo->getRoomReferrers($open_chat_id, $win['fromDate'], $win['toDate'], 20);

        $referrers = array_map(fn($r) => $this->formatReferrer($r, $open_chat_id), $referrerRows);

        return response([
            'days' => $win['days'],
            'fromDate' => $win['fromDate'],
            'toDate' => $win['toDate'],
            'updatedAt' => $m['updatedAt'],
            'pageviews' => $m['pageviews'],
            'activeUsers' => $m['activeUsers'],
            'searchClicks' => $m['searchClicks'],
            'searchImpressions' => $m['searchImpressions'],
            'searchPosition' => $m['searchPosition'],
            'seoIndirect' => $seoIndirect,
            'jumpClicks' => $m['jumpClicks'],
            'jumpClicksOrganic' => $m['jumpClicksOrganic'],
            'avgEngagementSeconds' => $m['avgEngagementSeconds'],
            'searchQueries' => $searchQueries,
            'referrers' => $referrers,
        ]);
    }

    /**
     * リファラ行を表示用に整形する（label / isInternal を付ける）。
     *
     * isInternal は host が本家ドメイン（SecretsConfig::$gscSiteUrl 由来）かどうか。
     * label は (direct)→「直接・不明」/ 検索エンジン→「検索」/ 本家内部→パスからページ種別 /
     * 外部→ホスト名。
     *
     * @param array{referrer:string, pageviews:int} $r
     * @param int $currentRoomId この詳細ページの部屋ID（自分自身からの参照を判別する）
     * @return array{referrer:string, label:string, detail:string, pageviews:int, isInternal:bool}
     *   label  … 一覧の1行に出す短い文言（はみ出す分は省略表示）
     *   detail … タップ/ホバーのチップに出す全文（どこから来たかを明示）
     */
    private function formatReferrer(array $r, int $currentRoomId = 0): array
    {
        $referrer = (string)$r['referrer'];
        $pageviews = (int)$r['pageviews'];

        if ($referrer === '(direct)') {
            return [
                'referrer' => $referrer,
                'label' => '直接・不明',
                'detail' => '直接アクセス（ブックマーク／アプリ内／URL直打ち など、参照元なし）',
                'pageviews' => $pageviews,
                'isInternal' => false,
            ];
        }

        $host = (string)(parse_url($referrer, PHP_URL_HOST) ?? '');
        $host = strtolower($host);
        // 先頭の www. は無視して比較する
        $bareHost = preg_replace('/^www\./', '', $host) ?? $host;

        $ownDomain = $this->ownDomainHost();
        $isInternal = $ownDomain !== '' && ($bareHost === $ownDomain || str_ends_with($bareHost, '.' . $ownDomain));

        if ($isInternal) {
            $path = (string)(parse_url($referrer, PHP_URL_PATH) ?? '');
            $query = (string)(parse_url($referrer, PHP_URL_QUERY) ?? '');
            [$label, $detail, $isSeoOrigin] = $this->internalReferrerLabel($path, $query, $currentRoomId);
            return [
                'referrer' => $referrer,
                'label' => $label,
                'detail' => $detail,
                'pageviews' => $pageviews,
                // SEO経由＝本家内SEOページ経由の間接流入。自己参照(このページ内)は除く。
                'isInternal' => $isSeoOrigin,
            ];
        }

        // 検索エンジン判定（host ベース）
        $engine = $this->searchEngineName($bareHost);
        if ($engine !== '') {
            return [
                'referrer' => $referrer,
                'label' => $engine . '検索',
                'detail' => $engine . '検索からの流入（外部）',
                'pageviews' => $pageviews,
                'isInternal' => false,
            ];
        }

        // それ以外の外部はホスト名（取れなければ生 referrer）。チップには元URLを出す。
        return [
            'referrer' => $referrer,
            'label' => $bareHost !== '' ? $bareHost : $referrer,
            'detail' => '外部サイトからの流入: ' . $referrer,
            'pageviews' => $pageviews,
            'isInternal' => false,
        ];
    }

    /**
     * 本家ドメインのホスト名を SecretsConfig::$gscSiteUrl から取り出す（ハードコードしない）。
     * 例 'sc-domain:openchat-review.me' / 'https://openchat-review.me/' → 'openchat-review.me'。
     * 設定が空なら ''（その場合 isInternal は常に false）。
     */
    private function ownDomainHost(): string
    {
        $site = trim(\App\Config\SecretsConfig::$gscSiteUrl);
        if ($site === '') {
            return '';
        }
        // sc-domain:example.com 形式
        if (str_starts_with($site, 'sc-domain:')) {
            $host = substr($site, strlen('sc-domain:'));
        } else {
            // URLプレフィックス形式 https://example.com/
            $parsed = parse_url($site, PHP_URL_HOST);
            $host = $parsed !== null && $parsed !== false ? $parsed : $site;
        }
        $host = strtolower(trim($host));
        return preg_replace('/^www\./', '', $host) ?? $host;
    }

    /**
     * 検索エンジン名を返す（該当しなければ ''）。Google / Yahoo / Bing 等。
     */
    private function searchEngineName(string $host): string
    {
        if ($host === '') {
            return '';
        }
        $map = [
            'google.' => 'Google',
            'bing.' => 'Bing',
            'yahoo.' => 'Yahoo!',
            'duckduckgo.' => 'DuckDuckGo',
            'baidu.' => 'Baidu',
            'yandex.' => 'Yandex',
            'ecosia.' => 'Ecosia',
            'naver.' => 'Naver',
        ];
        foreach ($map as $needle => $name) {
            if (str_contains($host, $needle)) {
                return $name;
            }
        }
        return '';
    }

    /**
     * 本家(openchat-review.me)内リファラの path/query から「どのページから来たか」を
     * 人間可読に整形する。本家のページ種別は限られるので各パターンを文言化する。
     *
     * @return array{0:string, 1:string, 2:bool} [一覧用の短ラベル, チップ用の全文, SEO経由(間接流入)とみなすか]
     *   第3要素 false ＝ 自己参照「このページ内」（再読込/グラフ操作。SEO経由バッジを出さない）。
     */
    private function internalReferrerLabel(string $path, string $query, int $currentRoomId = 0): array
    {
        // 末尾スラッシュを正規化（'/ranking/' と '/ranking' を同一視）
        $p = $path === '' ? '/' : rtrim($path, '/');
        if ($p === '') {
            $p = '/';
        }
        $params = [];
        if ($query !== '') {
            parse_str($query, $params);
        }
        $keyword = isset($params['keyword']) ? trim((string)$params['keyword']) : '';

        // トップ
        if ($p === '/') {
            return ['トップページ', 'オプチャグラフのトップページ', true];
        }
        // ランキング（検索結果＝キーワード付き、カテゴリ別、急上昇）
        if ($p === '/ranking' || str_starts_with($p, '/ranking/')) {
            // keyword=tag:◯◯ はおすすめタグからの遷移（検索ではなくタグ）。1行にタグ名も出す。
            if (str_starts_with($keyword, 'tag:')) {
                $tag = trim(substr($keyword, 4));
                if ($tag !== '') {
                    return ['おすすめ（' . $tag . '）', 'おすすめタグ「' . $tag . '」', true];
                }
            }
            if ($keyword !== '') {
                return ['検索結果「' . $keyword . '」', '検索結果「' . $keyword . '」', true];
            }
            if (preg_match('#^/ranking/(.+)$#u', $p, $m)) {
                $cat = urldecode($m[1]);
                return ['ランキング（' . $cat . '）', 'ランキング（カテゴリ: ' . $cat . '）', true];
            }
            return ['ランキング', '急上昇ランキング', true];
        }
        if ($p === '/official-ranking' || str_starts_with($p, '/official-ranking/')) {
            return ['公式ランキング', '公式ランキング', true];
        }
        // おすすめ（タグ別＝1行にタグ名も／一覧）
        if (preg_match('#^/recommend/(.+)$#u', $p, $m)) {
            $tag = urldecode($m[1]);
            return ['おすすめ（' . $tag . '）', 'おすすめタグ「' . $tag . '」', true];
        }
        if ($p === '/recommend') {
            return ['おすすめ', 'おすすめタグ一覧', true];
        }
        // 部屋詳細（自分自身＝再訪/グラフ操作 と 他の部屋 を区別する。自己参照は SEO経由ではない）
        if (preg_match('#^/(?:oc|openchat)/(\d+)#', $p, $m)) {
            if ($currentRoomId > 0 && (int)$m[1] === $currentRoomId) {
                return ['このページ内', 'この部屋のページ内（再読み込み・グラフ操作など）', false];
            }
            return ['他の部屋', '他の部屋（ID: ' . $m[1] . '）から', true];
        }
        if ($p === '/oclist') {
            return ['部屋一覧', '部屋一覧ページ', true];
        }
        if (str_starts_with($p, '/recently-registered')) {
            return ['新着の部屋', '新着登録の部屋一覧', true];
        }
        if (str_starts_with($p, '/comments-timeline')) {
            return ['コメント新着', '新着コメント一覧', true];
        }
        if (str_starts_with($p, '/comment/')) {
            return ['コメント欄', 'コメント欄', true];
        }
        if ($p === '/labs' || str_starts_with($p, '/labs/')) {
            return ['ラボ', 'オプチャグラフ Labs', true];
        }
        // 既知パターン外の本家内ページ
        return ['サイト内ページ', 'オプチャグラフ内のページ: ' . $path, true];
    }

    /**
     * 検索ETA（プログレスバー用）取得API
     * GET /alpha-api/search-eta?keyword=&category=&sort=&order=
     *
     * 同条件の query_key に記録された直近の elapsed_ms を返す。
     * 無ければ全体の中央値、それも無ければ既定値(800ms)。
     */
    function searchEta(AlphaSearchTimingRepository $timingRepo)
    {
        $error = BadRequestException::class;
        Reception::$isJson = true;

        $keyword = Validator::str(Reception::input('keyword', ''), emptyAble: true, maxLen: 1000, e: $error);
        $category = (int)Validator::str(
            (string)Reception::input('category', '0'),
            regex: AppConfig::OPEN_CHAT_CATEGORY[MimimalCmsConfig::$urlRoot],
            e: $error
        );
        $sort = Validator::str(
            Reception::input('sort', 'member'),
            regex: ['member', 'created_at', 'hourly_diff', 'diff_24h', 'diff_1w'],
            e: $error
        );
        $order = Validator::str(Reception::input('order', 'desc'), regex: ['asc', 'desc'], e: $error);

        $etaMs = $timingRepo->resolveEtaMs($this->buildSearchKey($keyword, $category, $sort, $order));

        return response(['etaMs' => $etaMs]);
    }

    /**
     * 検索条件を ETA 用の query_key に正規化する。
     * keyword は parseKeywords と同じ正規化（全角スペース→半角・トリム・空除去・小文字化）後に
     * カンマ連結し、category|sort|order を付ける。search と search-eta で完全一致させること。
     */
    private function buildSearchKey(string $keyword, int $category, string $sort, string $order): string
    {
        $normalized = str_replace('　', ' ', $keyword);
        $parts = array_values(array_filter(
            array_map(static fn($k) => mb_strtolower(trim($k)), explode(' ', $normalized)),
            static fn($k) => $k !== ''
        ));
        $kw = implode(',', $parts);
        $key = $kw . '|' . $category . '|' . $sort . '|' . $order;
        // query_key は varchar(190)。超過時は安定キー（ハッシュ）に丸める。
        if (mb_strlen($key) > 190) {
            $key = 'h:' . substr(hash('sha256', $key), 0, 32) . '|' . $category . '|' . $sort . '|' . $order;
        }
        return $key;
    }

    /**
     * ランキング行を共通の RankingRoom 形（指標フィールドを除く）に整形。
     * search / periodGrowth と同じ img_url(obsハッシュ)・LINE URL 組み立てを踏襲する。
     *
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function formatRankingRoom(array $item): array
    {
        return [
            'id' => (int)$item['id'],
            'name' => $item['name'],
            'desc' => $item['description'] ?? '',
            'member' => (int)$item['member'],
            'img' => $item['img_url'] ?? '',
            'emblem' => (int)$item['emblem'],
            'category' => (int)$item['category'],
            'categoryName' => $this->getCategoryName((int)$item['category']),
            'join_method_type' => (int)$item['join_method_type'],
            'createdAt' => !empty($item['created_at']) ? strtotime($item['created_at']) : null,
            'registeredAt' => $item['api_created_at'] ?? '',
            'url' => $this->buildLineUrl($item['url'] ?? ''),
        ];
    }

    /**
     * URL/ハッシュを LINE 形式の完全URLに変換（search/batchStats/periodGrowth と同一ロジック）。
     */
    private function buildLineUrl(?string $url): string
    {
        if (empty($url)) {
            return '';
        }
        if (strpos($url, 'http') === 0) {
            return $url;
        }
        $hash = trim($url, '/');
        return $hash !== '' ? AppConfig::LINE_APP_URL . $hash : '';
    }

    // ============================================================
    // 通知/アラート（ウォッチ設定 + 算出済み通知）
    // 認証は未導入。cookie-user-id ベースの user_id で識別する。
    // ============================================================

    /**
     * ウォッチ設定の取得
     * GET /alpha-api/alerts/config
     */
    function alertsConfigGet(AuthInterface $auth, AlphaAlertRepository $repo)
    {
        Reception::$isJson = true;
        $userId = $auth->loginCookieUserId();

        return response([
            'keywords' => $repo->getKeywordWatches($userId),
            'rooms' => $repo->getRoomWatches($userId),
            'mylistThreshold' => $repo->getMylistThreshold($userId),
        ]);
    }

    /**
     * ウォッチ設定の保存（全置き換え）
     * PUT /alpha-api/alerts/config
     * Body: { keywords:[{keyword,category?}], rooms:[{openChatId,upMember?,upPercent?,downMember?,downPercent?}],
     *         mylistThreshold:{upPercent?,downPercent?,enabled} }
     */
    function alertsConfigPut(AuthInterface $auth, AlphaAlertRepository $repo, Reception $reception)
    {
        Reception::$isJson = true;
        $userId = $auth->loginCookieUserId();
        $body = $reception->input();

        // keywords
        $keywords = [];
        if (isset($body['keywords']) && is_array($body['keywords'])) {
            foreach ($body['keywords'] as $k) {
                if (!is_array($k)) continue;
                $kw = trim((string)($k['keyword'] ?? ''));
                if ($kw === '' || mb_strlen($kw) > 190) continue;
                $cat = (isset($k['category']) && $k['category'] !== null && $k['category'] !== '')
                    ? (int)$k['category'] : null;
                $keywords[] = ['keyword' => $kw, 'category' => $cat];
            }
        }

        // rooms
        $rooms = [];
        if (isset($body['rooms']) && is_array($body['rooms'])) {
            foreach ($body['rooms'] as $r) {
                if (!is_array($r)) continue;
                $ocId = (int)($r['openChatId'] ?? $r['open_chat_id'] ?? 0);
                if ($ocId < 1) continue;
                $rooms[] = [
                    'open_chat_id' => $ocId,
                    'up_member' => $this->numOrNull($r['upMember'] ?? null),
                    'up_percent' => $this->floatOrNull($r['upPercent'] ?? null),
                    'down_member' => $this->numOrNull($r['downMember'] ?? null),
                    'down_percent' => $this->floatOrNull($r['downPercent'] ?? null),
                ];
            }
        }

        $repo->replaceKeywordWatches($userId, $keywords);
        $repo->replaceRoomWatches($userId, $rooms);

        // mylistThreshold
        if (isset($body['mylistThreshold']) && is_array($body['mylistThreshold'])) {
            $mt = $body['mylistThreshold'];
            $repo->saveMylistThreshold(
                $userId,
                $this->floatOrNull($mt['upPercent'] ?? null),
                $this->floatOrNull($mt['downPercent'] ?? null),
                !empty($mt['enabled'])
            );
        }

        return response([
            'keywords' => $repo->getKeywordWatches($userId),
            'rooms' => $repo->getRoomWatches($userId),
            'mylistThreshold' => $repo->getMylistThreshold($userId),
        ]);
    }

    /**
     * 算出済み通知の取得（＋既読更新）
     * GET /alpha-api/alerts
     *   ?markRead=all          … 取得後に全件既読化
     *   ?markRead=1,2,3        … 指定id既読化
     */
    function alertsGet(AuthInterface $auth, AlphaAlertRepository $repo)
    {
        Reception::$isJson = true;
        $userId = $auth->loginCookieUserId();

        $markRead = (string)Reception::input('markRead', '');
        if ($markRead === 'all') {
            $repo->markAllRead($userId);
        } elseif ($markRead !== '') {
            $repo->markRead($userId, array_map('intval', explode(',', $markRead)));
        }

        $notifications = $repo->getNotifications($userId);

        // type 別に振り分けてフロントが扱いやすい形にする
        $keywordHits = [];
        $movements = [];
        $unreadCount = 0;
        foreach ($notifications as $n) {
            if (!$n['is_read']) $unreadCount++;
            $item = [
                'id' => $n['id'],
                'type' => $n['type'],
                'isRead' => $n['is_read'],
                'createdAt' => strtotime($n['created_at']),
            ] + $n['payload'];

            if ($n['type'] === 'keyword') {
                // KeywordHit は member:int|null と detectedAt:int(unix秒) を必ず含める。
                // 旧通知（payload にこれらが無い）は member=null / detectedAt=通知作成時刻 で補完。
                if (!array_key_exists('member', $item)) {
                    $item['member'] = null;
                }
                if (!array_key_exists('detectedAt', $item) || !is_int($item['detectedAt'])) {
                    $item['detectedAt'] = (int)$item['createdAt'];
                }
                $keywordHits[] = $item;
            } else {
                $movements[] = $item;
            }
        }

        return response([
            'keywordHits' => $keywordHits,
            'movements' => $movements,
            'unreadCount' => $unreadCount,
            'computedAt' => $notifications[0]['created_at'] ?? null,
        ]);
    }

    /**
     * Y-m-d 形式として妥当な日付文字列だけを返す（不正・空は null）。
     */
    private function validDateOrNull(mixed $v): ?string
    {
        $s = trim((string)($v ?? ''));
        if ($s === '') {
            return null;
        }
        $d = \DateTime::createFromFormat('Y-m-d', $s);
        if ($d === false) {
            return null;
        }
        // createFromFormat は '2026-02-30' 等を繰り上げて受理するので往復一致を確認
        return $d->format('Y-m-d') === $s ? $s : null;
    }

    private function numOrNull(mixed $v): ?int
    {
        return ($v === null || $v === '') ? null : (int)$v;
    }

    private function floatOrNull(mixed $v): ?float
    {
        return ($v === null || $v === '') ? null : (float)$v;
    }
}
