<?php

declare(strict_types=1);

namespace App\Controllers\Pages;

use App\Config\AppConfig;
use App\Models\Repositories\SyncOpenChatStateRepositoryInterface;
use App\Services\Admin\AdminAuthService;
use Shadow\DB;
use App\Models\SQLite\SQLiteStatistics;
use App\Models\UserLogRepositories\UserLogRepository;
use App\Services\Admin\AdminTool;
use App\Services\Cron\Enum\BatchScript;
use App\Services\Cron\Enum\SyncOpenChatStateType;
use App\Services\Cron\Utility\BatchScriptLauncher;
use App\Services\OpenChat\Utility\OpenChatServicesUtility;
use App\Services\SitemapGenerator;
use App\Services\UpdateDailyRankingService;
use App\Services\UpdateHourlyMemberRankingService;
use Shadow\Kernel\Validator;
use Shared\Exceptions\NotFoundException;
use Shared\MimimalCmsConfig;

class AdminPageController
{
    function __construct(AdminAuthService $adminAuthService, private BatchScriptLauncher $batchScriptLauncher)
    {
        if (!$adminAuthService->auth()) {
            throw new NotFoundException;
        }
    }

    function mylist(UserLogRepository $repo)
    {
        $result = $repo->getUserListLogAll(9999, 0);
        return view('admin/dash_my_list', ['result' => $result]);
    }

    /**
     * Cronバッチ実行テスト
     * @param string $lang ja|tw|th
     */
    function cron_test(string $lang)
    {
        $urlRoot = null;
        switch ($lang) {
            case 'ja':
                $urlRoot = '';
                break;
            case 'tw':
                $urlRoot = '/tw';
                break;
            case 'th':
                $urlRoot = '/th';
                break;
        }

        if (is_null($urlRoot)) {
            return view('admin/admin_message_page', ['title' => 'exec', 'message' => 'パラメータ(lang)が不正です。']);
        }

        $this->batchScriptLauncher->launchInBackground(BatchScript::cronCrawling, $urlRoot);

        return view('admin/admin_message_page', ['title' => 'exec', 'message' => BatchScript::cronCrawling->absolutePath() . ' を実行しました。']);
    }

    /**
     * APIデータベース更新バッチ実行テスト
     */
    function apidb_test()
    {
        $this->batchScriptLauncher->launchInBackground(BatchScript::updateApiDb);

        return view('admin/admin_message_page', ['title' => 'exec', 'message' => BatchScript::updateApiDb->absolutePath() . ' を実行しました。']);
    }

    /**
     * 日次処理リトライテストバッチ実行
     */
    function retry_daily_test()
    {
        $this->batchScriptLauncher->launchInBackground(BatchScript::retryDailyTask, MimimalCmsConfig::$urlRoot);

        return view('admin/admin_message_page', ['title' => 'exec', 'message' => BatchScript::retryDailyTask->absolutePath() . ' を実行しました。']);
    }
    
    /**
     * $_SERVERの内容を出力
     */
    function server()
    {
        pre_var_dump($_SERVER);
    }

    /**
     * タグ更新テストバッチ実行
     */
    function tagupdate()
    {
        // admin からの手動実行は実行中の前ランを kill して後発で再実行する（--cancel-previous）。
        $this->batchScriptLauncher->launchInBackground(BatchScript::tagUpdate, '--cancel-previous');

        return view('admin/admin_message_page', ['title' => 'exec', 'message' => BatchScript::tagUpdate->absolutePath() . ' を実行しました。']);
    }

    /**
     * オープンチャット削除
     */
    function deleteoc(?string $oc, \App\Services\OpenChat\Updater\OpenChatDeleter $openChatDeleter)
    {
        if (!($oc = Validator::num($oc))) return false;
        $result = $openChatDeleter->deleteOpenChatById($oc);
        return view('admin/admin_message_page', ['title' => 'オープンチャット削除', 'message' => $result ? '削除しました' : '削除されたオープンチャットはありません']);
    }

    /**
     * 毎時メンバーランキング更新
     */
    function hourlygenerank(UpdateHourlyMemberRankingService $hourlyMemberRanking)
    {
        $hourlyMemberRanking->update();
        echo 'done';
    }

    /**
     * サイトマップ生成
     */
    function genesitemap(SitemapGenerator $sitemapGenerator)
    {
        $sitemapGenerator->generate();
        echo 'done';
    }

    /**
     * 統計データの2024-01-15分を2024-01-14分の日次ランキングデータから復元する
     * ※ 2024-01-15に統計データの更新処理が不具合で停止したため、その補填用
     */
    private function recoveryyesterdaystats()
    {
        $exeption = [];

        $ranking = DB::fetchAll('SELECT * FROM statistics_ranking_day');
        foreach ($ranking as $oc) {
            $yesterday = SQLiteStatistics::fetchColumn("SELECT member FROM statistics WHERE date = '2024-01-14' AND open_chat_id = " . $oc['open_chat_id']);
            if (!$yesterday) {
                $exeption[] = $oc;
                continue;
            };

            $member = $yesterday + $oc['diff_member'];
            SQLiteStatistics::execute("UPDATE statistics SET member = {$member} WHERE date = '2024-01-15' AND open_chat_id = " . $oc['open_chat_id']);
        }

        var_dump($exeption);
    }

    /**
     * 管理者用cookie登録
     */
    function cookie(AdminAuthService $adminAuthService, ?string $key)
    {
        if (!$adminAuthService->registerAdminCookie($key)) {
            return false;
        }

        return view('admin/admin_message_page', ['title' => 'cookie取得完了', 'message' => 'アクセス用のcookieを取得しました']);
    }

    /**
     * 全静的キャッシュデータ更新
     */
    function genetop()
    {
        $this->batchScriptLauncher->launchInBackground(BatchScript::genetopExec, MimimalCmsConfig::$urlRoot);

        return view('admin/admin_message_page', ['title' => 'exec', 'message' => BatchScript::genetopExec->absolutePath() . ' を実行しました。']);
    }

    /**
     * ルーム個別ページの分析文(narrative)の静的キャッシュ(oc_page_cache)を生成する。
     * 指定言語の全ルームをバックフィルする（背景実行・長時間）。
     * 例: /admin/genocpagecache/ja  (tw / th も可)
     */
    function genocpagecache(string $lang)
    {
        $urlRoot = match ($lang) {
            'ja' => '',
            'tw' => '/tw',
            'th' => '/th',
            default => null,
        };

        if (is_null($urlRoot)) {
            return view('admin/admin_message_page', ['title' => 'exec', 'message' => 'パラメータ(lang)が不正です。ja / tw / th を指定してください。']);
        }

        $this->batchScriptLauncher->launchInBackground(BatchScript::updateOcPageCache, $urlRoot);

        return view('admin/admin_message_page', ['title' => 'exec', 'message' => BatchScript::updateOcPageCache->absolutePath() . " ({$lang}) を背景実行しました（全ルームのバックフィル・完了まで時間がかかります）。"]);
    }

    /**
     * 日次ランキング更新
     */
    function updatedailyranking(UpdateDailyRankingService $updateRankingService,)
    {
        $updateRankingService->update(OpenChatServicesUtility::getCronModifiedStatsMemberDate());

        return view('admin/admin_message_page', ['title' => 'updateRankingService', 'message' => 'updateRankingServiceを実行しました。']);
    }

    function killmerge(SyncOpenChatStateRepositoryInterface $syncOpenChatStateRepository)
    {
        $syncOpenChatStateRepository->setTrue(SyncOpenChatStateType::openChatApiDbMergerKillFlag);
        return view('admin/admin_message_page', ['title' => 'OpenChatApiDbMerger', 'message' => 'OpenChatApiDbMergerを強制終了しました']);
    }

    function killdaily(SyncOpenChatStateRepositoryInterface $syncOpenChatStateRepository)
    {
        $syncOpenChatStateRepository->setString(
            SyncOpenChatStateType::openChatDailyCrawlingKillFlag,
            date('Y-m-d H:i:s')
        );
        return view('admin/admin_message_page', ['title' => 'OpenChatApiDbMerger', 'message' => 'OpenChatDailyCrawlingを強制終了しました']);
    }

    /**
     * 全cronバッチ強制終了
     */
    function killcron()
    {
        $commands = [
            'pkill -f cron_crawling.php',
        ];

        foreach ($commands as $cmd) {
            exec($cmd);
        }

        // ps aux | grep cron の結果をmessageに含める（grep自身は除外）
        $psOutput = [];
        exec("ps aux | grep '[c]ron' 2>&1", $psOutput);

        $message = "全cronバッチの強制終了を実行しました。\n\n";
        $message .= "ps aux | grep cron:\n";
        $message .= implode("\n", $psOutput);

        return view('admin/admin_message_page', [
            'title' => 'killcron',
            'message' => $message,
        ]);
    }

    /**
     * このコントローラーの全publicメソッドのシグネチャとコメントを表示
     */
    function help()
    {
        $controllers = [
            'AdminPageController' => self::class,
            'LogController' => LogController::class,
            'AdminCommentImageController' => AdminCommentImageController::class,
            'AdminRecommendTagController' => AdminRecommendTagController::class,
        ];

        // メソッド名と実際のURLパスが異なるもののマッピング
        $routeMap = [
            'LogController' => [
                'index' => 'admin/log',
                'cronLog' => 'admin/log/{type}',
                'exceptionLog' => 'admin/log/exception',
                'exceptionDetail' => 'admin/log/exception/detail',
            ],
            'AdminCommentImageController' => [
                'commentImages' => 'admin/comment-images',
            ],
            'AdminRecommendTagController' => [
                'index' => 'admin/recommend-tags',
            ],
        ];

        // help 一覧に出さないメソッド（GUI内部が叩くPOST専用の保存エンドポイント等）
        $hiddenMethods = [
            'AdminRecommendTagController' => ['save' => true],
        ];

        $helpText = '';
        foreach ($controllers as $name => $class) {
            $reflection = new \ReflectionClass($class);
            $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);

            $helpText .= "{$name}:\n\n";
            foreach ($methods as $method) {
                if ($method->isConstructor()) {
                    continue;
                }

                $methodName = $method->getName();
                if (!empty($hiddenMethods[$name][$methodName])) {
                    continue;
                }
                $path = $routeMap[$name][$methodName] ?? "admin/{$methodName}";
                $docComment = $method->getDocComment();
                $helpText .= url($path) . "\n";
                if ($docComment) {
                    $helpText .= trim(preg_replace('/^\s*\*\s?/m', '', preg_replace('/^\/\*\*|\*\/$/', '', $docComment))) . "\n";
                } else {
                    $helpText .= "No documentation available.\n";
                }
                $helpText .= "\n";
            }
            $helpText .= "\n";
        }

        echo nl2br(htmlspecialchars($helpText));
    }

    function phpinfo()
    {
        phpinfo();
    }

    function adminer()
    {
        // 出力バッファをクリア
        while (ob_get_level()) {
            ob_end_clean();
        }

        // SQLiteデータベースへの自動接続設定
        if (!isset($_GET['sqlite'])) {
            $dbPath = AppConfig::ROOT_PATH . 'storage/ja/SQLite/ocgraph_sqlapi/sqlapi.db';
            $_GET['sqlite'] = $dbPath;
            $_GET['username'] = '';
            $_GET['db'] = $dbPath;
        }

        // パスワードなしSQLite接続を許可するプラグイン版を読み込む
        // adminer-plugin.php内でadminer-5.4.1.phpも読み込まれる
        include AppConfig::ROOT_PATH . 'app/Services/Admin/adminer-plugin.php';
        exit;
    }

    /**
     * Discord通知テスト
     */
    function testdiscord(?string $message)
    {
        $message = $message ?? 'テストメッセージ from AdminPageController';
        $result = AdminTool::sendDiscordNotify($message);
        return view('admin/admin_message_page', ['title' => 'Discord通知テスト', 'message' => $result . "\n" . '送信メッセージ: ' . $message]);
    }
}
