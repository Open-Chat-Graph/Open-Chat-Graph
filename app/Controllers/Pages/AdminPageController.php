<?php

declare(strict_types=1);

namespace App\Controllers\Pages;

use App\Config\AppConfig;
use App\Models\Repositories\Api\ApiDeletedOpenChatListRepository;
use App\Models\Repositories\DeleteOpenChatRepositoryInterface;
use App\Models\Repositories\SyncOpenChatStateRepositoryInterface;
use App\Services\Admin\AdminAuthService;
use Shadow\DB;
use App\Services\OpenChat\OpenChatApiDbMerger;
use App\Models\SQLite\SQLiteStatistics;
use App\Models\UserLogRepositories\UserLogRepository;
use App\Services\Admin\AdminTool;
use App\Services\Cron\Enum\SyncOpenChatStateType;
use App\Services\OpenChat\OpenChatDailyCrawling;
use App\Services\OpenChat\OpenChatImageUpdater;
use App\Services\OpenChat\Utility\OpenChatServicesUtility;
use App\Services\RankingPosition\Persistence\RankingPositionHourPersistence;
use App\Services\SitemapGenerator;
use App\Services\UpdateDailyRankingService;
use App\Services\UpdateHourlyMemberRankingService;
use Shadow\Kernel\Validator;
use Shared\Exceptions\NotFoundException;
use Shared\MimimalCmsConfig;

class AdminPageController
{
    function __construct(AdminAuthService $adminAuthService)
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

    function ban(ApiDeletedOpenChatListRepository $repo, string $date)
    {
        $result = $repo->getDeletedOpenChatList($date, 999999);
        $result = array_map(function ($item) {
            $item['description'] = truncateDescription($item['description']);
            return $item;
        }, $result);

        pre_var_dump($result);
    }

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

        $path = AppConfig::ROOT_PATH . 'batch/cron/cron_crawling.php';
        $arg = escapeshellarg($urlRoot);

        exec(AppConfig::$phpBinary . " {$path} {$arg} >/dev/null 2>&1 &");

        return view('admin/admin_message_page', ['title' => 'exec', 'message' => $path . ' を実行しました。']);
    }

    function apidb_test()
    {
        $path = AppConfig::ROOT_PATH . 'batch/exec/update_api_db.php';

        exec(AppConfig::$phpBinary . " {$path} >/dev/null 2>&1 &");

        return view('admin/admin_message_page', ['title' => 'exec', 'message' => $path . ' を実行しました。']);
    }

    function rankingban_test()
    {
        $path = AppConfig::ROOT_PATH . 'batch/exec/ranking_ban_test.php';

        exec(AppConfig::$phpBinary . " {$path} >/dev/null 2>&1 &");

        return view('admin/admin_message_page', ['title' => 'exec', 'message' => $path . ' を実行しました。']);
    }

    function retry_daily_test()
    {
        $urlRoot = MimimalCmsConfig::$urlRoot;

        $path = AppConfig::ROOT_PATH . 'batch/exec/retry_daily_tast.php';
        $arg = escapeshellarg($urlRoot);

        exec(AppConfig::$phpBinary . " {$path} {$arg} >/dev/null 2>&1 &");

        return view('admin/admin_message_page', ['title' => 'exec', 'message' => $path . ' を実行しました。']);
    }

    function tagupdate()
    {
        $path = AppConfig::ROOT_PATH . 'batch/exec/tag_update.php';

        exec(AppConfig::$phpBinary . " {$path} >/dev/null 2>&1 &");

        return view('admin/admin_message_page', ['title' => 'exec', 'message' => $path . ' を実行しました。']);
    }

    function recommendtagupdate()
    {
        $path = AppConfig::ROOT_PATH . 'batch/exec/tag_update_onlyrecommend.php';

        exec(AppConfig::$phpBinary . " {$path} >/dev/null 2>&1 &");

        return view('admin/admin_message_page', ['title' => 'exec', 'message' => $path . ' を実行しました。']);
    }

    function updateimgeall(?string $lang)
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

        $path = AppConfig::ROOT_PATH . 'batch/exec/imageupdater_exec.php';
        $arg = escapeshellarg($urlRoot);

        exec(AppConfig::$phpBinary . " {$path} {$arg} >/dev/null 2>&1 &");

        return view('admin/admin_message_page', ['title' => 'exec', 'message' => $path . ' を実行しました。']);
    }

    private function halfcheck()
    {
        $path = AppConfig::ROOT_PATH . 'batch/cron/cron_half_check.php';

        exec("/usr/bin/php8.2 {$path} >/dev/null 2>&1 &");

        return view('admin/admin_message_page', ['title' => 'exec', 'message' => $path . ' を実行しました。']);
    }

    function deleteoc(?string $oc, DeleteOpenChatRepositoryInterface $deleteOpenChatRepository)
    {
        if (!($oc = Validator::num($oc))) return false;
        $result = $deleteOpenChatRepository->deleteOpenChat($oc);
        return view('admin/admin_message_page', ['title' => 'オープンチャット削除', 'message' => $result ? '削除しました' : '削除されたオープンチャットはありません']);
    }

    function positiondb(RankingPositionHourPersistence $rankingPositionHourPersistence)
    {
        $rankingPositionHourPersistence->persistStorageFileToDb();
        echo 'done';
    }

    function hourlygenerank(UpdateHourlyMemberRankingService $hourlyMemberRanking)
    {
        $hourlyMemberRanking->update();
        echo 'done';
    }

    function genesitemap(SitemapGenerator $sitemapGenerator)
    {
        $sitemapGenerator->generate();
        echo 'done';
    }

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

    function cookie(AdminAuthService $adminAuthService, ?string $key)
    {
        if (!$adminAuthService->registerAdminCookie($key)) {
            return false;
        }

        return view('admin/admin_message_page', ['title' => 'cookie取得完了', 'message' => 'アクセス用のcookieを取得しました']);
    }

    function genetop()
    {
        $path = AppConfig::ROOT_PATH . 'batch/exec/genetop_exec.php';
        $arg = escapeshellarg(MimimalCmsConfig::$urlRoot);

        exec(AppConfig::$phpBinary . " {$path} {$arg} >/dev/null 2>&1 &");

        return view('admin/admin_message_page', ['title' => 'exec', 'message' => $path . ' を実行しました。']);
    }

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
        $syncOpenChatStateRepository->setTrue(SyncOpenChatStateType::openChatDailyCrawlingKillFlag);
        return view('admin/admin_message_page', ['title' => 'OpenChatApiDbMerger', 'message' => 'OpenChatDailyCrawlingを強制終了しました']);
    }

    function phpinfo()
    {
        phpinfo();
    }
}
