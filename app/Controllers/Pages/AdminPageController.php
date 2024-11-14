<?php

declare(strict_types=1);

namespace App\Controllers\Pages;

use App\Config\AppConfig;
use App\Models\Repositories\DeleteOpenChatRepositoryInterface;
use App\Services\Admin\AdminAuthService;
use Shadow\DB;
use App\Services\OpenChat\OpenChatApiDbMerger;
use App\Models\SQLite\SQLiteStatistics;
use App\Models\UserLogRepositories\UserLogRepository;
use App\Services\OpenChat\OpenChatDailyCrawling;
use App\Services\RankingPosition\Persistence\RankingPositionHourPersistence;
use App\Services\SitemapGenerator;
use App\Services\UpdateHourlyMemberRankingService;
use Shadow\Kernel\Validator;
use Shared\Exceptions\NotFoundException;

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

    function test()
    {
        $path = AppConfig::ROOT_PATH . 'test_exec.php';
        $path = AppConfig::ROOT_PATH . 'genetop_exec.php';

        exec("php {$path} >/dev/null 2>&1 &");

        return view('admin/admin_message_page', ['title' => 'exec', 'message' => $path . ' を実行しました。']);
    }

    function testpage()
    {
        return view('admin/admin_message_page', ['title' => 'test', 'message' => 'testを実行しました。']);
    }

    private function halfcheck()
    {
        $path = AppConfig::ROOT_PATH . 'cron_half_check.php';

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
        $path = AppConfig::ROOT_PATH . 'genetop_exec.php';

        exec("/usr/bin/php8.2 {$path} >/dev/null 2>&1 &");

        return view('admin/admin_message_page', ['title' => 'exec', 'message' => $path . ' を実行しました。']);
    }

    function killmerge()
    {
        OpenChatApiDbMerger::enableKillFlag();

        return view('admin/admin_message_page', ['title' => 'OpenChatApiDbMerger', 'message' => 'OpenChatApiDbMergerを強制終了しました']);
    }

    function killdaily()
    {
        OpenChatDailyCrawling::enableKillFlag();
        return view('admin/admin_message_page', ['title' => 'OpenChatApiDbMerger', 'message' => 'OpenChatDailyCrawlingを強制終了しました']);
    }

    function phpinfo()
    {
        phpinfo();
    }
}
