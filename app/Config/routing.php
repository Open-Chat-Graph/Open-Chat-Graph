<?php

namespace App\Config;

use App\Controllers\Api\AccreditationPostApiController;
use App\Controllers\Api\AdminEndPointController;
use App\Controllers\Api\AdsRegistrationApiController;
use App\Controllers\Api\CommentLikePostApiController;
use App\Controllers\Api\CommentListApiController;
use App\Controllers\Api\CommentPostApiController;
use App\Controllers\Api\CommentReportApiController;
use App\Controllers\Api\LineLoginApiController;
use Shadow\Kernel\Route;
use App\Middleware\RedirectLineWebBrowser;
use App\Services\Admin\AdminAuthService;
use App\Controllers\Api\OpenChatRankingPageApiController;
use App\Controllers\Api\OpenChatRegistrationApiController;
use App\Controllers\Api\RankingPositionApiController;
use App\Controllers\Api\MyListApiController;
use App\Controllers\Api\RecentCommentApiController;
use App\Controllers\Pages\AccreditationController;
use App\Controllers\Pages\AdsRegistrationPageController;
use App\Controllers\Pages\FuriganaPageController;
use App\Controllers\Pages\OpenChatPageController;
use App\Controllers\Pages\RankingBanLabsPageController;
use App\Controllers\Pages\ReactRankingPageController;
use App\Controllers\Pages\RecentOpenChatPageController;
use App\Controllers\Pages\RecommendOpenChatPageController;
use App\Controllers\Pages\RegisterOpenChatPageController;
use App\Controllers\Pages\TagLabsPageController;
use App\Middleware\VerifyCsrfToken;
use App\Services\Accreditation\Enum\ExamType;
use Shadow\Kernel\Reception;

Route::middlewareGroup(RedirectLineWebBrowser::class)
    ->path('ranking/{category}', [ReactRankingPageController::class, 'ranking'])
    ->matchStr('list', default: 'all', emptyAble: true)
    ->matchNum('category', min: 1)
    ->match(function (int $category) {
        handleRequestWithETagAndCache(getHouryUpdateTime() . "ranking/{$category}");
        return isset(array_flip(AppConfig::OPEN_CHAT_CATEGORY)[$category]);
    })

    ->path('ranking', [ReactRankingPageController::class, 'ranking'])
    ->matchStr('list', default: 'all', emptyAble: true)
    ->matchNum('category', emptyAble: true)
    ->match(fn() => handleRequestWithETagAndCache(getHouryUpdateTime() . "ranking"))

    ->path('official-ranking/{category}', [ReactRankingPageController::class, 'ranking'])
    ->matchStr('list', default: 'rising', emptyAble: true)
    ->matchNum('category', min: 1)
    ->match(function (int $category) {
        handleRequestWithETagAndCache(getHouryUpdateTime() . "official-ranking/{$category}");
        return isset(array_flip(AppConfig::OPEN_CHAT_CATEGORY)[$category]);
    })

    ->path('official-ranking', [ReactRankingPageController::class, 'ranking'])
    ->matchStr('list', default: 'rising', emptyAble: true)
    ->matchNum('category', emptyAble: true)
    ->match(fn() => handleRequestWithETagAndCache(getHouryUpdateTime() . "official-ranking"));

Route::path('policy');

Route::path('/')
    ->match(fn() => handleRequestWithETagAndCache(getHouryUpdateTime() . 'index'));

Route::path('oc/{open_chat_id}', [OpenChatPageController::class, 'index'])
    ->matchNum('open_chat_id', min: 1)
    ->match(fn(int $open_chat_id) => handleRequestWithETagAndCache(getHouryUpdateTime() . $open_chat_id));

Route::path('oc/{open_chat_id}/csv', [OpenChatPageController::class, 'csv'])
    ->matchNum('open_chat_id', min: 1)
    ->match(fn(int $open_chat_id) => handleRequestWithETagAndCache(getDailyUpdateTime() . $open_chat_id));

Route::path('oclist', [OpenChatRankingPageApiController::class, 'index'])
    ->match(fn(Reception $reception) => handleRequestWithETagAndCache(getHouryUpdateTime() . json_encode($reception->input())));

Route::path(
    'oc/{open_chat_id}/position',
    [RankingPositionApiController::class, 'rankingPosition']
)
    ->matchNum('open_chat_id', min: 1)
    ->matchNum('category', min: 0, max: 41)
    ->matchStr('sort', regex: ['ranking', 'rising'])
    ->matchStr('start_date')
    ->matchStr('end_date')
    ->match(function (string $start_date, string $end_date, Reception $reception) {
        $isValid = $start_date === date("Y-m-d", strtotime($start_date))
            && $end_date === date("Y-m-d", strtotime($end_date));
        if (!$isValid)
            return false;

        handleRequestWithETagAndCache(getHouryUpdateTime() . json_encode($reception->input()));
        return true;
    });

Route::path(
    'oc/{open_chat_id}/position_hour',
    [RankingPositionApiController::class, 'rankingPositionHour']
)
    ->matchNum('open_chat_id', min: 1)
    ->matchNum('category', min: 0, max: 41)
    ->matchStr('sort', regex: ['ranking', 'rising'])
    ->match(function (Reception $reception) {
        handleRequestWithETagAndCache(getHouryUpdateTime() . json_encode($reception->input()));
    });

Route::path('mylist-api', [MyListApiController::class, 'index']);
Route::path('recent-comment-api', [RecentCommentApiController::class, 'index']);

Route::path('recommend', [RecommendOpenChatPageController::class, 'index'])
    ->matchStr('tag', maxLen: 100)
    ->match(function (string $tag) {
        handleRequestWithETagAndCache(getHouryUpdateTime() . $tag);
    });

Route::path(
    'oc@post@get',
    [OpenChatRegistrationApiController::class, 'register', 'post'],
    [RegisterOpenChatPageController::class, 'index', 'get'],
)
    ->middleware([VerifyCsrfToken::class])
    ->matchStr('url', 'post', regex: OpenChatCrawlerConfig::LINE_URL_MATCH_PATTERN);

Route::path(
    'recently-registered/{page}@get',
    [RecentOpenChatPageController::class, 'index'],
)
    ->matchNum('page')
    ->match(function (int $page) {
        handleRequestWithETagAndCache(getHouryUpdateTime() . "recently-registered/{$page}");
    });

Route::path(
    'recently-registered@get',
    [RecentOpenChatPageController::class, 'index'],
)
    ->matchNum('page', emptyAble: true)
    ->match(function () {
        handleRequestWithETagAndCache(getHouryUpdateTime() . "recently-registered");
    });

Route::path(
    'labs/tags',
    [TagLabsPageController::class, 'index']
)
    ->matchStr('ads', emptyAble: true)
    ->match(function () {
        handleRequestWithETagAndCache(getHouryUpdateTime() . "labs/tags");
    });

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
        handleRequestWithETagAndCache(getHouryUpdateTime() . json_encode($reception->input()));
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
        fn(string $text, string $name) => removeAllZeroWidthCharacters($text)
            ? ['name' => removeAllZeroWidthCharacters($name) ? $name : '']
            : false,
        'post'
    )
    ->middleware([VerifyCsrfToken::class]);

// コメントリアクションAPI
Route::path(
    'comment_reaction/{comment_id}@post@delete',
    [CommentLikePostApiController::class, 'add', 'post'],
    [CommentLikePostApiController::class, 'delete', 'delete']
)
    ->matchNum('comment_id', min: 1)
    ->matchStr('type', 'post', regex: ['empathy', 'insights', 'negative'])
    ->middleware([VerifyCsrfToken::class]);

// 通報API
Route::path(
    'comment_report/{comment_id}@post',
    [CommentReportApiController::class, 'index']
)
    ->matchNum('comment_id', min: 1)
    ->matchStr('token');

Route::path('admin/cookie')
    ->match(function (AdminAuthService $adminAuthService, ?string $key) {
        sessionStart();
        if (!$adminAuthService->registerAdminCookie($key)) {
            return false;
        }
        return redirect();
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
    ->matchNum('flag', min: 0, max: 3);

Route::path(
    'admin-api/deleteuser@post',
    [AdminEndPointController::class, 'deleteuser']
)
    ->matchNum('id')
    ->matchNum('commentId');

Route::path(
    'admin-api/commentbanroom@post',
    [AdminEndPointController::class, 'commentbanroom']
)
    ->matchNum('id');

Route::path(
    'oc/{open_chat_id}/admin',
    [OpenChatPageController::class, 'index']
)
    ->matchNum('open_chat_id', min: 1)
    ->match(fn() => ['isAdminPage' => '1']);

Route::path(
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
    });

Route::path(
    'auth/login',
    [LineLoginApiController::class, 'login']
)
    ->matchStr('return_to', maxLen: 100, emptyAble: true);

Route::path(
    'auth/callback',
    [LineLoginApiController::class, 'callback']
)
    ->matchStr('error', emptyAble: true)
    ->matchStr('code', emptyAble: true)
    ->matchStr('state', emptyAble: true);

Route::path(
    'auth/logout',
    [LineLoginApiController::class, 'logout']
)
    ->matchStr('return_to', maxLen: 100, emptyAble: true);

Route::path(
    'accreditation/login',
    [AccreditationController::class, 'homeLogin']
);

Route::path(
    'accreditation/{examType}/{pageType}',
    [AccreditationController::class, 'route']
)
    ->matchStr('examType', maxLen: 10)
    ->matchStr('pageType', maxLen: 20, emptyAble: true)
    ->matchNum('page', emptyAble: true, default: 1)
    ->matchNum('id', emptyAble: true, default: 0);

Route::path(
    'accreditation/register-profile@POST',
    [AccreditationPostApiController::class, 'registerProfile']
)
    ->matchStr('name', maxLen: 20)
    ->matchStr('url', regex: OpenChatCrawlerConfig::LINE_INTERNAL_URL_MATCH_PATTERN, emptyAble: true)
    ->matchStr('admin_key', maxLen: 200, emptyAble: true)
    ->matchStr('return_to')
    ->match(
        fn(string $name) => !!removeAllZeroWidthCharacters($name)
    );

Route::path(
    'accreditation/register-question@POST',
    [AccreditationPostApiController::class, 'registerQuestion']
)
    ->matchStr('question', maxLen: 4000)
    ->matchStr('answers.a', maxLen: 4000)
    ->matchStr('answers.b', maxLen: 4000)
    ->matchStr('answers.c', maxLen: 4000)
    ->matchStr('answers.d', maxLen: 4000)
    ->matchStr('answers.correct', maxLen: 1)
    ->matchStr('explanation', maxLen: 4000)
    ->matchStr('source_url', maxLen: 2083, emptyAble: true)
    ->matchStr('type')
    ->matchStr('return_to')
    ->match(fn(string $type) => !!ExamType::tryFrom($type));

Route::path(
    'accreditation/edit-question@POST',
    [AccreditationPostApiController::class, 'editQuestion']
)
    ->matchStr('question', maxLen: 4000)
    ->matchStr('answers.a', maxLen: 4000)
    ->matchStr('answers.b', maxLen: 4000)
    ->matchStr('answers.c', maxLen: 4000)
    ->matchStr('answers.d', maxLen: 4000)
    ->matchStr('answers.correct', maxLen: 1)
    ->matchStr('explanation', maxLen: 4000)
    ->matchStr('source_url', maxLen: 2083, emptyAble: true)
    ->matchStr('type')
    ->matchNum('id')
    ->matchNum('publishing', emptyAble: true, default: 0)
    ->matchStr('return_to')
    ->match(fn(string $type) => !!ExamType::tryFrom($type));

Route::path(
    'accreditation/delete-question@POST',
    [AccreditationPostApiController::class, 'deleteQuestion']
)
    ->matchNum('id')
    ->matchStr('return_to');

Route::path(
    'accreditation/reset-permission-question@POST',
    [AccreditationPostApiController::class, 'resetPermissionQuestion']
)
    ->matchNum('id')
    ->matchStr('return_to');

Route::path(
    'accreditation/move-question@POST',
    [AccreditationPostApiController::class, 'moveQuestion']
)
    ->matchNum('id')
    ->matchStr('type')
    ->match(fn(string $type) => !!ExamType::tryFrom($type));

Route::path(
    'accreditation/set-admin-permission@POST',
    [AccreditationPostApiController::class, 'setAdminPermission']
)
    ->matchNum('id')
    ->matchNum('is_admin', min: 0, max: 1)
    ->matchStr('return_to');

Route::path('furigana@POST')
    ->matchStr('json');

Route::path('furigana/guideline')
    ->match(fn() => handleRequestWithETagAndCache(getHouryUpdateTime() . 'guideline'));

Route::path(
    'furigana/defamation-guideline',
    [FuriganaPageController::class, 'defamationGuideline']
)
    ->match(fn() => handleRequestWithETagAndCache(getHouryUpdateTime() . 'defamationGuideline'));

Route::path('accreditation')
    ->matchNum('id', emptyAble: true, default: 0);

cache();
Route::run();
