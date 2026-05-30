<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Config\AppConfig;
use App\Models\ApiRepositories\Alpha\AlphaOpenChatRepository;
use App\Models\ApiRepositories\Alpha\AlphaPeriodGrowthRepository;
use App\Models\ApiRepositories\Alpha\AlphaStatsRepository;
use App\Models\ApiRepositories\OpenChatApiArgs;
use App\Models\RankingBanRepositories\RankingBanPageRepository;
use App\Services\Alpha\AlphaInsightsService;
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
    function search(AlphaOpenChatRepository $repo)
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

        // キーワードは必須
        $keyword = Validator::str(Reception::input('keyword', ''), maxLen: 1000, e: $error);
        if (trim($keyword) === '') {
            throw new BadRequestException('keyword parameter is required');
        }

        $category = (int)Validator::str(
            (string)Reception::input('category', '0'),
            regex: AppConfig::OPEN_CHAT_CATEGORY[MimimalCmsConfig::$urlRoot],
            e: $error
        );
        $days = Validator::num(Reception::input('days', 30), min: 1, max: 365, e: $error);
        $order = Validator::str(Reception::input('order', 'desc'), regex: ['asc', 'desc'], e: $error);
        $limit = Validator::num(Reception::input('limit', 20), min: 1, max: 100, e: $error);

        $result = $repo->findPeriodGrowth($keyword, $category, (int)$days, $order, (int)$limit);

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
}
