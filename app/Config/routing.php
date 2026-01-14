<?php

namespace App\Config;

use App\Controllers\Api\AdminEndPointController;
use App\Controllers\Api\CommentLikePostApiController;
use App\Controllers\Api\CommentListApiController;
use App\Controllers\Api\CommentPostApiController;
use App\Controllers\Api\CommentReportApiController;
use App\Controllers\Api\DatabaseApiController;
use Shadow\Kernel\Route;
use App\Services\Admin\AdminAuthService;
use App\Controllers\Api\OpenChatRankingPageApiController;
use App\Controllers\Api\OpenChatRegistrationApiController;
use App\Controllers\Api\RankingPositionApiController;
use App\Controllers\Api\MyListApiController;
use App\Controllers\Api\RecentCommentApiController;
use App\Controllers\Pages\FuriganaPageController;
use App\Controllers\Pages\JumpOpenChatPageController;
use App\Controllers\Pages\LabsPageController;
use App\Controllers\Pages\OpenChatPageController;
use App\Controllers\Pages\RankingBanLabsPageController;
use App\Controllers\Pages\ReactRankingPageController;
use App\Controllers\Pages\RecentCommentPageController;
use App\Controllers\Pages\RecentOpenChatPageController;
use App\Controllers\Pages\RecommendOpenChatPageController;
use App\Controllers\Pages\RegisterOpenChatPageController;
use App\Controllers\Pages\LogController;
use App\Controllers\Pages\AdminPageController;
use App\Middleware\VerifyCsrfToken;
use App\ServiceProvider\ApiCommentListControllerServiceProvider;
use App\ServiceProvider\ApiDbOpenChatControllerServiceProvider;
use App\ServiceProvider\ApiRankingPositionPageRepositoryServiceProvider;
use Shadow\Kernel\Reception;
use Shared\MimimalCmsConfig;

Route::path('ranking/{category}', [ReactRankingPageController::class, 'ranking'])
    ->matchStr('list', default: 'all', emptyAble: true)
    ->matchNum('category', min: 1)
    ->match(function (int $category) {
        handleRequestWithETagAndCache("ranking/{$category}");
        return !!getCategoryName($category);
    });

Route::path('ranking', [ReactRankingPageController::class, 'ranking'])
    ->matchStr('list', default: 'all', emptyAble: true)
    ->matchNum('category', emptyAble: true)
    ->match(fn() => handleRequestWithETagAndCache("ranking"));

/* Route::path('official-ranking/{category}', [ReactRankingPageController::class, 'ranking'])
    ->matchStr('list', default: 'rising', emptyAble: true)
    ->matchNum('category', min: 1)
    ->match(function (int $category) {
        handleRequestWithETagAndCache("official-ranking/{$category}");
        return isset(array_flip(AppConfig::OPEN_CHAT_CATEGORY[MimimalCmsConfig::$urlRoot])[$category]);
    });

Route::path('official-ranking', [ReactRankingPageController::class, 'ranking'])
    ->matchStr('list', default: 'rising', emptyAble: true)
    ->matchNum('category', emptyAble: true)
    ->match(fn() => handleRequestWithETagAndCache("official-ranking"));
 */

Route::path('policy');

Route::path('/')
    ->match(fn() => handleRequestWithETagAndCache('index'));

Route::path('oc/{open_chat_id}', [OpenChatPageController::class, 'index'])
    ->matchNum('open_chat_id', min: 1)
    ->match(function (int $open_chat_id) {
        if (MimimalCmsConfig::$urlRoot === '')
            handleRequestWithETagAndCache($open_chat_id);
    });

Route::path('oc/{open_chat_id}/jump', [JumpOpenChatPageController::class, 'index'])
    ->matchNum('open_chat_id', min: 1)
    ->match(function (int $open_chat_id) {
        return MimimalCmsConfig::$urlRoot !== '/tw';
    });

// TODO: test-api
Route::path('ocapi/{user}/{open_chat_id}', [OpenChatPageController::class, 'index'])
    ->matchNum('open_chat_id', min: 1)
    ->match(function (string $user) {

        app(ApiDbOpenChatControllerServiceProvider::class)->register();
        return MimimalCmsConfig::$urlRoot === '' && $user === SecretsConfig::$adminApiKey;
    });

Route::path('oclist', [OpenChatRankingPageApiController::class, 'index'])
    ->match(fn(Reception $reception) => handleRequestWithETagAndCache(json_encode($reception->input())));

Route::path(
    'oc/{open_chat_id}/position',
    [RankingPositionApiController::class, 'rankingPosition']
)
    ->matchNum('open_chat_id', min: 1)
    ->matchNum('category', min: 0)
    ->matchStr('sort', regex: ['ranking', 'rising'])
    ->matchStr('start_date')
    ->matchStr('end_date')
    ->match(function (string $start_date, string $end_date, Reception $reception) {
        $isValid = $start_date === date("Y-m-d", strtotime($start_date))
            && $end_date === date("Y-m-d", strtotime($end_date))
            && strtotime($start_date) <= strtotime($end_date);
        if (!$isValid)
            return false;

        handleRequestWithETagAndCache(json_encode($reception->input()));
        return true;
    });

// TODO: test-api
Route::path(
    'ranking-position/{user}/oc/{open_chat_id}/position',
    [RankingPositionApiController::class, 'rankingPosition']
)
    ->matchNum('open_chat_id', min: 1)
    ->matchNum('category', min: 0)
    ->matchStr('sort', regex: ['ranking', 'rising'])
    ->matchStr('start_date')
    ->matchStr('end_date')
    ->match(function (string $start_date, string $end_date, string $user) {
        $isValid = $start_date === date("Y-m-d", strtotime($start_date))
            && $end_date === date("Y-m-d", strtotime($end_date))
            && strtotime($start_date) <= strtotime($end_date);
        if (!$isValid)
            return false;

        app(ApiRankingPositionPageRepositoryServiceProvider::class)->register();
        return MimimalCmsConfig::$urlRoot === '' && $user === SecretsConfig::$adminApiKey;
    });

// TODO: test-api
Route::path('ranking-position/{user}/oc/{open_chat_id}/position_hour')
    ->match(function (string $user) {
        if (!MimimalCmsConfig::$urlRoot === '' && $user === SecretsConfig::$adminApiKey)
            return false;

        return response([
            'date' => ['01/01 00:00'],
            'member' => [0],
            'position' => [0],
            'time' => ['00:00'],
            'totalCount' => [0],
        ]);
    });

Route::path(
    'oc/{open_chat_id}/position_hour',
    [RankingPositionApiController::class, 'rankingPositionHour']
)
    ->matchNum('open_chat_id', min: 1)
    ->matchNum('category', min: 0)
    ->matchStr('sort', regex: ['ranking', 'rising'])
    ->match(function (Reception $reception) {
        handleRequestWithETagAndCache(json_encode($reception->input()));
    });

Route::path('mylist-api', [MyListApiController::class, 'index'])
    ->match(fn() => MimimalCmsConfig::$urlRoot === '');

Route::path('recent-comment-api', [RecentCommentApiController::class, 'index'])
    ->match(fn() => MimimalCmsConfig::$urlRoot === '')
    ->matchNum('open_chat_id', min: 1, emptyAble: true);

Route::path('recent-comment-api/nocache', [RecentCommentApiController::class, 'nocache'])
    ->match(fn() => MimimalCmsConfig::$urlRoot === '')
    ->matchNum('open_chat_id', min: 1, emptyAble: true);

// タグ関連のルーティング
Route::path('recommend')
    ->matchStr('tag', maxLen: 1000)
    ->match(function (string $tag) {
        return redirect(url('recommend/' . urlencode($tag)), 301);
    });

Route::path('recommend/{tag}', [RecommendOpenChatPageController::class, 'index'])
    ->matchStr('tag', maxLen: 1000)
    ->match(function (string $tag) {
        handleRequestWithETagAndCache($tag);
        return ['tag' => urldecode($tag)];
    });

Route::path(
    'oc@post@get',
    [OpenChatRegistrationApiController::class, 'register', 'post'],
    [RegisterOpenChatPageController::class, 'index', 'get'],
)
    ->middleware([VerifyCsrfToken::class])
    ->matchStr('url', 'post', regex: \App\Services\Crawler\Config\OpenChatCrawlerConfig::LINE_URL_MATCH_PATTERN[MimimalCmsConfig::$urlRoot])
    ->match(function() {
        return MimimalCmsConfig::$urlRoot === '';
    });

Route::path(
    'recently-registered/{page}@get',
    [RecentOpenChatPageController::class, 'index'],
)
    ->matchNum('page')
    ->match(function (int $page) {
        handleRequestWithETagAndCache("recently-registered/{$page}");
    });

Route::path(
    'recently-registered@get',
    [RecentOpenChatPageController::class, 'index'],
)
    ->matchNum('page', emptyAble: true)
    ->match(function () {
        handleRequestWithETagAndCache("recently-registered");
    });

Route::path(
    'comments-timeline/{page}@get',
    [RecentCommentPageController::class, 'index'],
)
    ->matchNum('page')
    ->match(function (int $page) {
        if (MimimalCmsConfig::$urlRoot !== '')
            return false;

        handleRequestWithETagAndCache("recent-comments/{$page}");
    });

Route::path(
    'comments-timeline@get',
    [RecentCommentPageController::class, 'index'],
)
    ->matchNum('page', emptyAble: true)
    ->match(function () {
        if (MimimalCmsConfig::$urlRoot !== '')
            return false;

        handleRequestWithETagAndCache("recent-comments");
    });

Route::path(
    'labs',
    [LabsPageController::class, 'index']
)
    ->match(fn() => MimimalCmsConfig::$urlRoot === '');

Route::path(
    'labs/live',
    [LabsPageController::class, 'live']
)
    ->match(fn() => MimimalCmsConfig::$urlRoot === '');

/* Route::path(
    'labs/tags',
    [TagLabsPageController::class, 'index']
)
    ->matchStr('ads', emptyAble: true)
    ->match(function () {
                if (MimimalCmsConfig::$urlRoot !== '')
            return false;

        handleRequestWithETagAndCache("labs/tags");
    }); */

Route::path(
    'labs/publication-analytics',
    [RankingBanLabsPageController::class, 'index']
)
    ->matchNum('publish', min: 0, max: 2, default: 1, emptyAble: true)
    ->matchNum('change', min: 0, max: 2, default: 1, emptyAble: true)
    ->matchNum('percent', min: 1, max: 100, default: 50, emptyAble: true)
    ->matchNum('page', min: 1, default: 1, emptyAble: true)
    ->matchStr('keyword', maxLen: 100, emptyAble: true)
    ->match(function (Reception $reception) {
        if (MimimalCmsConfig::$urlRoot !== '')
            return false;

        handleRequestWithETagAndCache(json_encode($reception->input()));
    });

// コメントAPI
Route::path(
    'comment/{open_chat_id}@get@post',
    [CommentListApiController::class, 'index', 'get'],
    [CommentPostApiController::class, 'index', 'post']
)
    ->matchNum('open_chat_id', min: 0)
    ->matchNum('page', 'get', min: 0)
    ->matchNum('limit', 'get', min: 1)
    ->matchStr('token', 'post')
    ->matchStr('name', 'post', maxLen: 20, emptyAble: true)
    ->matchStr('text', 'post', maxLen: 1000)
    ->match(
        function (string $text, string $name) {
            if (MimimalCmsConfig::$urlRoot !== '')
                return false;

            return removeAllZeroWidthCharacters($text)
                ? ['name' => removeAllZeroWidthCharacters($name) ? $name : '']
                : false;
        },
        'post'
    )
    ->middleware([VerifyCsrfToken::class], 'get');

// アーカイブコメントAPI（認証付き、読み取り専用）
Route::path(
    'comment/{user}/oc/comment/{open_chat_id}@get',
    [CommentListApiController::class, 'index', 'get']
)
    ->matchNum('open_chat_id', min: 0)
    ->matchNum('page', 'get', min: 0)
    ->matchNum('limit', 'get', min: 1)
    ->match(function (string $user) {
        app(ApiCommentListControllerServiceProvider::class)->register();
        return MimimalCmsConfig::$urlRoot === '' && $user === SecretsConfig::$adminApiKey;
    });

// コメントリアクションAPI
Route::path(
    'comment_reaction/{comment_id}@post@delete',
    [CommentLikePostApiController::class, 'add', 'post'],
    [CommentLikePostApiController::class, 'delete', 'delete']
)
    ->matchNum('comment_id', min: 1)
    ->matchStr('type', 'post', regex: ['empathy', 'insights', 'negative'])
    ->match(fn() => MimimalCmsConfig::$urlRoot === '')
    ->middleware([VerifyCsrfToken::class]);

// 通報API
Route::path(
    'comment_report/{comment_id}@post',
    [CommentReportApiController::class, 'index']
)
    ->matchNum('comment_id', min: 1)
    ->match(fn() => MimimalCmsConfig::$urlRoot === '')
    ->matchStr('token');

Route::path('admin/cookie')
    ->match(function (AdminAuthService $adminAuthService, ?string $key) {
        sessionStart();
        if (!$adminAuthService->registerAdminCookie($key)) {
            return false;
        }
        return redirect();
    });

// Admin Log Viewer
Route::path('admin/log', [LogController::class, 'index'])
    ->match(function () {
        noStore();
        return MimimalCmsConfig::$urlRoot === '';
    });

Route::path('admin/log/exception', [LogController::class, 'exceptionLog'])
    ->matchNum('page', min: 1, default: 1, emptyAble: true)
    ->match(function (AdminAuthService $adminAuthService) {
        noStore();
        return MimimalCmsConfig::$urlRoot === '' && $adminAuthService->auth();
    });

Route::path('admin/log/exception/detail', [LogController::class, 'exceptionDetail'])
    ->matchNum('index', min: 0)
    ->match(function (AdminAuthService $adminAuthService) {
        noStore();
        return MimimalCmsConfig::$urlRoot === '' && $adminAuthService->auth();
    });
Route::path('admin/log/{type}', [LogController::class, 'cronLog'])
    ->matchStr('type', regex: ['ja-cron', 'th-cron', 'tw-cron'])
    ->matchNum('page', min: 1, default: 1, emptyAble: true)
    ->match(function () {
        noStore();
        return MimimalCmsConfig::$urlRoot === '';
    });

// Adminer Database Tool
Route::path('admin/adminer@get@post', [AdminPageController::class, 'adminer'])
    ->match(function () {
        return MimimalCmsConfig::$urlRoot === '';
    });

Route::path(
    'admin-api@post',
    [AdminEndPointController::class, 'index']
);

Route::path(
    'admin-api/deletecomment@post@get',
    [AdminEndPointController::class, 'deletecomment']
)
    ->matchNum('id')
    ->matchNum('commentId')
    ->match(fn() => MimimalCmsConfig::$urlRoot === '')
    ->matchNum('flag', min: 0, max: 3);

Route::path(
    'admin-api/deleteuser@post',
    [AdminEndPointController::class, 'deleteuser']
)
    ->matchNum('id')
    ->match(fn() => MimimalCmsConfig::$urlRoot === '')
    ->matchNum('commentId');

Route::path(
    'admin-api/commentbanroom@post',
    [AdminEndPointController::class, 'commentbanroom']
)
    ->match(fn() => MimimalCmsConfig::$urlRoot === '')
    ->matchNum('id');

Route::path(
    'oc/{open_chat_id}/admin',
    [OpenChatPageController::class, 'index']
)
    ->matchNum('open_chat_id', min: 1)
    ->match(fn() => ['isAdminPage' => '1']);

/* Route::path(
    'ads/register@post',
    [AdsRegistrationApiController::class, 'register']
)
    ->matchStr('ads_title')
    ->matchStr('ads_sponsor_name')
    ->matchStr('ads_paragraph', emptyAble: true)
    ->matchStr('ads_href')
    ->matchStr('ads_img_url')
    ->matchStr('ads_tracking_url', emptyAble: true)
    ->matchStr('ads_title_button');

Route::path(
    'ads/update@post',
    [AdsRegistrationApiController::class, 'update']
)
    ->matchNum('id', min: 1)
    ->matchStr('ads_title')
    ->matchStr('ads_sponsor_name')
    ->matchStr('ads_paragraph', emptyAble: true)
    ->matchStr('ads_href')
    ->matchStr('ads_img_url')
    ->matchStr('ads_tracking_url', emptyAble: true)
    ->matchStr('ads_title_button');

Route::path(
    'ads/delete@post',
    [AdsRegistrationApiController::class, 'delete']
)
    ->matchNum('id', min: 1);

Route::path(
    'ads/update-tagmap@post',
    [AdsRegistrationApiController::class, 'updateTagsMap']
)
    ->matchNum('ads_id')
    ->matchStr('tag');

Route::path(
    'ads/tags@post',
    [AdsRegistrationApiController::class, 'updateTagsMap']
)
    ->matchNum('id', min: 1)
    ->matchStr('tag');

Route::path(
    'ads',
    [AdsRegistrationPageController::class, 'index']
)
    ->matchNum('id', emptyAble: true);

Route::path(
    'labs/tags/ads',
    [TagLabsPageController::class, 'index']
)
    ->match(function () {
        return ['isAdminPage' => '1'];
    }); */

Route::path('furigana@POST')
    ->match(fn() => MimimalCmsConfig::$urlRoot === '')
    ->matchStr('json');

Route::path('furigana/guideline')
    ->match(function () {
        handleRequestWithETagAndCache('guideline');
        return MimimalCmsConfig::$urlRoot === '';
    });

Route::path(
    'furigana/defamation-guideline',
    [FuriganaPageController::class, 'defamationGuideline']
)
    ->match(function () {
        handleRequestWithETagAndCache('defamationGuideline');
        return MimimalCmsConfig::$urlRoot === '';
    });

Route::path(
    'database/{user}/query@get@options',
    [DatabaseApiController::class, 'index']
)
    ->match(function (string $user) {
        allowCORS();
        return MimimalCmsConfig::$urlRoot === '' && $user === SecretsConfig::$adminApiKey;
    })
    ->matchStr('stmt');

Route::path(
    'database/{user}/schema@get@options',
    [DatabaseApiController::class, 'schema']
)
    ->match(function (string $user) {
        allowCORS();
        return MimimalCmsConfig::$urlRoot === '' && $user === SecretsConfig::$adminApiKey;
    });

Route::path(
    'database/{user}/ban@get@options',
    [DatabaseApiController::class, 'ban']
)
    ->match(function (string $user) {
        allowCORS();
        return MimimalCmsConfig::$urlRoot === '' && $user === SecretsConfig::$adminApiKey;
    })
    ->matchStr('date');

cache();
Route::run();
