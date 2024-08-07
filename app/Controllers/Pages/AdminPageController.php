<?php

declare(strict_types=1);

namespace App\Controllers\Pages;

use App\Config\AppConfig;
use App\Models\Accreditation\AccreditationDB;
use App\Models\Accreditation\AccreditationUserModel;
use App\Models\CommentRepositories\DeleteCommentRepositoryInterface;
use App\Models\Repositories\DeleteOpenChatRepositoryInterface;
use App\Services\UpdateRankingService;
use App\Services\StaticData\StaticDataGenerator;
use App\Services\Admin\AdminAuthService;
use Shadow\DB;
use App\Services\Admin\AdminTool;
use App\Services\OpenChat\OpenChatApiDbMerger;
use App\Models\SQLite\SQLiteStatistics;
use App\Models\UserLogRepositories\UserLogRepository;
use App\Services\Accreditation\Enum\ExamType;
use App\Services\Accreditation\QuizApi\QuizApiService;
use App\Services\Accreditation\QuizOgpGenerator;
use App\Services\OpenChat\OpenChatDailyCrawling;
use App\Services\OpenChat\Utility\OpenChatServicesUtility;
use App\Services\RankingPosition\Persistence\RankingPositionHourPersistence;
use App\Services\Recommend\StaticData\RecommendStaticDataGenerator;
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

    private function test()
    {
        $path = AppConfig::ROOT_PATH . 'test_exec.php';

        exec("/usr/bin/php8.2 {$path} >/dev/null 2>&1 &");

        return view('admin/admin_message_page', ['title' => 'exec', 'message' => $path . ' を実行しました。']);
    }

    private function testpage(QuizOgpGenerator $quizOgpGenerator, AccreditationUserModel $accreditationUserModel)
    {

        foreach (ExamType::cases() as $type) {
            $dtos = $accreditationUserModel->getQuestionList(1, $type);
            foreach ($dtos as $dto) {
                $quizOgpGenerator->generateTextOgp(
                    $dto->question,
                    'quiz_img_' . $dto->id,
                );
            }
        }
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
