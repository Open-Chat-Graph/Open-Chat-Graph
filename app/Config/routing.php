<?php

namespace App\Config;

use App\Controllers\Api\AdminEndPointController;
use App\Controllers\Api\CommentLikePostApiController;
use App\Controllers\Api\CommentListApiController;
use App\Controllers\Api\CommentPostApiController;
use App\Controllers\Api\CommentImageThumbnailController;
use App\Controllers\Api\CommentReportApiController;
use App\Controllers\Api\DatabaseApiController;
use App\Controllers\Api\McpApiController;
use Shadow\Kernel\Route;
use App\Services\Admin\AdminAuthService;
use App\Controllers\Api\OpenChatRankingPageApiController;
use App\Controllers\Api\OpenChatChartApiController;
use App\Controllers\Api\AdvancedGrowthAnalysisApiController;
use App\Controllers\Api\OpenChatRegistrationApiController;
use App\Controllers\Api\MyListApiController;
use App\Controllers\Api\RecentCommentApiController;
use App\Controllers\Pages\AdminCommentImageController;
use App\Controllers\Pages\AdminBanUserController;
use App\Controllers\Pages\AdminCommentLogController;
use App\Controllers\Pages\AdminRecommendTagController;
use App\Controllers\Pages\FuriganaPageController;
use App\Controllers\Pages\IndexPageController;
use App\Controllers\Pages\JumpOpenChatPageController;
use App\Controllers\Pages\AllRoomStatsPageController;
use App\Controllers\Pages\LabsPageController;
use App\Controllers\Pages\OpenChatPageController;
use App\Controllers\Pages\BlogController;
use App\Controllers\Pages\PolicyPageController;
use App\Controllers\Pages\RankingBanLabsPageController;
use App\Controllers\Pages\ReactRankingPageController;
use App\Controllers\Pages\ReactAnalysisPageController;
use App\Controllers\Pages\RecentCommentPageController;
use App\Controllers\Pages\RecentOpenChatPageController;
use App\Controllers\Pages\RecommendOpenChatPageController;
use App\Controllers\Pages\RegisterOpenChatPageController;
use App\Controllers\Pages\RobotsController;
use App\Controllers\Pages\AdsTxtController;
use App\Controllers\Pages\StagingIconController;
use App\Controllers\Pages\LogController;
use App\Controllers\Pages\AdminPageController;
use App\Middleware\VerifyCsrfToken;
use App\Models\CommentRepositories\RecentCommentListRepositoryInterface;
use App\Services\Storage\FileStorageInterface;
use Shadow\Kernel\Reception;
use Shadow\Kernel\Validator;
use Shared\Exceptions\UnauthorizedException;
use Shared\MimimalCmsConfig;

Route::path('ranking/{category}', [ReactRankingPageController::class, 'ranking'])
    ->matchStr('list', default: 'all', emptyAble: true)
    ->matchNum('category', min: 1)
    ->match(function (int $category, FileStorageInterface $fileStorage) {
        if (!getCategoryName($category)) {
            return false;
        }
        checkLastModified($fileStorage->getContents('@hourlyCronUpdatedAtDatetime'));
    });

Route::path('ranking', [ReactRankingPageController::class, 'ranking'])
    ->matchStr('list', default: 'all', emptyAble: true)
    ->matchNum('category', emptyAble: true)
    ->match(function (FileStorageInterface $fileStorage) {
        checkLastModified($fileStorage->getContents('@hourlyCronUpdatedAtDatetime'));
    });

Route::path('policy', [PolicyPageController::class, 'index'])
    ->match(function (FileStorageInterface $fileStorage) {
        checkLastModified($fileStorage->getContents('@hourlyCronUpdatedAtDatetime'));
    });

Route::path('blog', [BlogController::class, 'index']);

Route::path('blog/{slug}', [BlogController::class, 'article']);

Route::path('robots.txt', [RobotsController::class, 'index'])
    ->match(function (FileStorageInterface $fileStorage) {
        if (MimimalCmsConfig::$urlRoot !== '')
            return false;

        checkLastModified($fileStorage->getContents('@hourlyCronUpdatedAtDatetime'));
    });

Route::path('ads.txt', [AdsTxtController::class, 'index'])
    ->match(function (FileStorageInterface $fileStorage) {
        if (MimimalCmsConfig::$urlRoot !== '')
            return false;

        checkLastModified($fileStorage->getContents('@hourlyCronUpdatedAtDatetime'));
    });

Route::path('assets/icon-192x192.png', [StagingIconController::class, 'icon192'])
    ->match(function () {
        if (MimimalCmsConfig::$urlRoot !== '')
            return false;
    });

Route::path('favicon.ico', [StagingIconController::class, 'favicon'])
    ->match(function () {
        if (MimimalCmsConfig::$urlRoot !== '')
            return false;
    });

Route::path('/', [IndexPageController::class, 'index'])
    ->match(function (FileStorageInterface $fileStorage) {
        checkLastModified($fileStorage->getContents('@hourlyCronUpdatedAtDatetime'));
    });

Route::path('oc/{open_chat_id}', [OpenChatPageController::class, 'index'])
    ->matchNum('open_chat_id', min: 1)
    ->match(function (FileStorageInterface $fileStorage) {
        checkLastModified($fileStorage->getContents('@hourlyCronUpdatedAtDatetime'));
    });

Route::path('oc/{open_chat_id}/jump', [JumpOpenChatPageController::class, 'index'])
    ->matchNum('open_chat_id', min: 1)
    ->match(function (FileStorageInterface $fileStorage) {
        checkLastModified($fileStorage->getContents('@hourlyCronUpdatedAtDatetime'));
    });

// 動的OGP画像（SNSシェア用カード）。オンデマンド生成＋ファイルキャッシュ。noindex
Route::path('oc/{open_chat_id}/card', [\App\Controllers\Api\OcCardImageController::class, 'index'])
    ->matchNum('open_chat_id', min: 1);

// 検索用1:1サムネイル（meta name="thumbnail"）。オンデマンド生成＋エッジキャッシュ。noindex
Route::path('oc/{open_chat_id}/thumb', [\App\Controllers\Api\OcCardImageController::class, 'thumb'])
    ->matchNum('open_chat_id', min: 1);

// 統計グラフデータ。graph(React)が表示ビュー（期間×順位種別×カテゴリ×モード）を指定して
// 描画に必要な系列を1リクエストで取得する。初回ロードは meta=1 でタブ可用性メタも同梱
// （/oc 本体から統計SQLite読み取りを外すため非同期取得）
Route::path('oc/{open_chat_id}/chart', [OpenChatChartApiController::class, 'chart'])
    ->matchNum('open_chat_id', min: 1)
    ->matchNum('category', min: 0)
    ->matchStr('span', regex: ['hour', 'day'])
    ->matchStr('sort', regex: ['none', 'ranking', 'rising'])
    ->matchStr('scope', regex: ['in', 'all'])
    ->matchStr('mode', regex: ['line', 'candlestick'])
    ->matchNum('meta', max: 1, emptyAble: true)
    ->match(function (FileStorageInterface $fileStorage) {
        checkLastModified($fileStorage->getContents('@hourlyCronUpdatedAtDatetime'));
    });

Route::path('oclist', [OpenChatRankingPageApiController::class, 'index'])
    ->match(function (FileStorageInterface $fileStorage) {
        checkLastModified($fileStorage->getContents('@hourlyCronUpdatedAtDatetime'));
    });

Route::path('oclist-tags', [OpenChatRankingPageApiController::class, 'themeTags'])
    ->match(function (FileStorageInterface $fileStorage) {
        checkLastModified($fileStorage->getContents('@hourlyCronUpdatedAtDatetime'));
    });

// 詳細成長分析（/labs/growth）。専門ユーザー向け・重いクエリ。React は /ranking と同一バンドル
// （React Router が URL で AnalysisPage を出し分け）。ページ HTML は毎時更新基準でCDNキャッシュ。
Route::path('labs/growth', [ReactAnalysisPageController::class, 'index'])
    ->match(function (FileStorageInterface $fileStorage) {
        checkLastModified($fileStorage->getContents('@hourlyCronUpdatedAtDatetime'));
    });

// 進捗ポーリング(status, no-store) と 結果取得(result, checkLastModified で CDN キャッシュ)。
Route::path('analysis-status', [AdvancedGrowthAnalysisApiController::class, 'status']);
Route::path('analysis-result', [AdvancedGrowthAnalysisApiController::class, 'result']);

Route::path('mylist-api', [MyListApiController::class, 'index'])
    ->match(function () {
        if (MimimalCmsConfig::$urlRoot !== '')
            return false;

        noStore();
    });

Route::path('recent-comment-api', [RecentCommentApiController::class, 'index'])
    ->match(function (RecentCommentListRepositoryInterface $recentCommentListRepository) {
        if (MimimalCmsConfig::$urlRoot !== '')
            return false;
        $time = $recentCommentListRepository->getLatestCommentTime();
        if ($time) checkLastModified($time);
    })
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
    ->match(function (string $tag, FileStorageInterface $fileStorage) {
        checkLastModified($fileStorage->getContents('@hourlyCronUpdatedAtDatetime'));
        return ['tag' => urldecode($tag)];
    });

// 動的OGP画像（タグページのSNSシェア用カード）。オンデマンド生成＋エッジキャッシュ。noindex
Route::path('recommend/{tag}/card', [\App\Controllers\Api\RecommendCardImageController::class, 'index'])
    ->matchStr('tag', maxLen: 1000)
    ->match(function (string $tag) {
        return ['tag' => urldecode($tag)];
    });

// 検索用1:1サムネイル（meta name="thumbnail"）。オンデマンド生成＋エッジキャッシュ。noindex
Route::path('recommend/{tag}/thumb', [\App\Controllers\Api\RecommendCardImageController::class, 'thumb'])
    ->matchStr('tag', maxLen: 1000)
    ->match(function (string $tag) {
        return ['tag' => urldecode($tag)];
    });

Route::path(
    'oc@post@get',
    [OpenChatRegistrationApiController::class, 'register', 'post'],
    [RegisterOpenChatPageController::class, 'index', 'get'],
)
    ->middleware([VerifyCsrfToken::class])
    ->matchStr('url', 'post', regex: \App\Services\Crawler\Config\OpenChatCrawlerConfig::LINE_URL_MATCH_PATTERN[MimimalCmsConfig::$urlRoot])
    ->match(function () {
        return MimimalCmsConfig::$urlRoot === '';
    });

Route::path(
    'recently-registered/{page}@get',
    [RecentOpenChatPageController::class, 'index'],
)
    ->matchNum('page')
    ->match(function (FileStorageInterface $fileStorage) {
        checkLastModified($fileStorage->getContents('@hourlyCronUpdatedAtDatetime'));
    });

Route::path(
    'recently-registered@get',
    [RecentOpenChatPageController::class, 'index'],
)
    ->matchNum('page', emptyAble: true)
    ->match(function (FileStorageInterface $fileStorage) {
        checkLastModified($fileStorage->getContents('@hourlyCronUpdatedAtDatetime'));
    });

Route::path(
    'comments-timeline/{page}@get',
    [RecentCommentPageController::class, 'index'],
)
    ->matchNum('page')
    ->match(function (int $page, RecentCommentListRepositoryInterface $recentCommentListRepository) {
        if (MimimalCmsConfig::$urlRoot !== '')
            return false;
        $time = $recentCommentListRepository->getLatestCommentTime();
        if ($time) checkLastModified($time);
    });

Route::path(
    'comments-timeline@get',
    [RecentCommentPageController::class, 'index'],
)
    ->matchNum('page', emptyAble: true)
    ->match(function (RecentCommentListRepositoryInterface $recentCommentListRepository) {
        if (MimimalCmsConfig::$urlRoot !== '')
            return false;
        $time = $recentCommentListRepository->getLatestCommentTime();
        if ($time) checkLastModified($time);
    });

Route::path(
    'labs',
    [LabsPageController::class, 'index']
)
    ->match(function () {
        if (MimimalCmsConfig::$urlRoot !== '')
            return false;
        checkLastModified(filemtime(MimimalCmsConfig::$viewsDir . '/labs_content.php'));
    });

Route::path(
    'labs/live',
    [LabsPageController::class, 'live']
)
    ->match(function () {
        if (MimimalCmsConfig::$urlRoot !== '')
            return false;
        checkLastModified(filemtime(MimimalCmsConfig::$viewsDir . '/live_content.php'));
    });

Route::path(
    'labs/all-room-stats',
    [AllRoomStatsPageController::class, 'index']
)
    ->match(function (FileStorageInterface $fileStorage) {
        if (MimimalCmsConfig::$urlRoot !== '')
            return false;
        checkLastModified($fileStorage->getContents('@hourlyCronUpdatedAtDatetime'));
    });

Route::path(
    'labs/publication-analytics',
    [RankingBanLabsPageController::class, 'index']
)
    ->matchNum('publish', min: 0, max: 2, default: 1, emptyAble: true)
    ->matchNum('change', min: 0, max: 2, default: 1, emptyAble: true)
    ->matchStr('items', maxLen: 80, emptyAble: true)
    ->matchNum('percent', min: 1, max: 100, default: 50, emptyAble: true)
    ->matchNum('dmin', min: 0, max: 87600, default: 0, emptyAble: true)
    ->matchNum('dmax', min: 0, max: 87600, default: 0, emptyAble: true)
    ->matchNum('page', min: 1, default: 1, emptyAble: true)
    ->matchStr('keyword', maxLen: 100, emptyAble: true)
    ->matchStr('since', maxLen: 10, emptyAble: true)
    ->matchStr('until', maxLen: 10, emptyAble: true)
    ->match(function (Reception $reception, FileStorageInterface $fileStorage) {
        if (MimimalCmsConfig::$urlRoot !== '')
            return false;
        checkLastModified($fileStorage->getContents('@hourlyCronUpdatedAtDatetime'));
    });

// 一覧データのHTMLフラグメント（非同期取得用）。バリデーション・キャッシュ制御は上のページルートと完全に同一
Route::path(
    'labs/publication-analytics/list',
    [RankingBanLabsPageController::class, 'fragment']
)
    ->matchNum('publish', min: 0, max: 2, default: 1, emptyAble: true)
    ->matchNum('change', min: 0, max: 2, default: 1, emptyAble: true)
    ->matchStr('items', maxLen: 80, emptyAble: true)
    ->matchNum('percent', min: 1, max: 100, default: 50, emptyAble: true)
    ->matchNum('dmin', min: 0, max: 87600, default: 0, emptyAble: true)
    ->matchNum('dmax', min: 0, max: 87600, default: 0, emptyAble: true)
    ->matchNum('page', min: 1, default: 1, emptyAble: true)
    ->matchStr('keyword', maxLen: 100, emptyAble: true)
    ->matchStr('since', maxLen: 10, emptyAble: true)
    ->matchStr('until', maxLen: 10, emptyAble: true)
    ->match(function (Reception $reception, FileStorageInterface $fileStorage) {
        if (MimimalCmsConfig::$urlRoot !== '')
            return false;
        checkLastModified($fileStorage->getContents('@hourlyCronUpdatedAtDatetime'));
    });

// コメントAPI
Route::path(
    'comment/{open_chat_id}@get@post',
    [CommentListApiController::class, 'index', 'get'],
    [CommentPostApiController::class, 'index', 'post']
)
    ->matchNum('open_chat_id', min: 0)
    ->matchNum('page', 'get', min: 0)
    ->matchNum('limit', 'get', min: 1, max: 10)
    ->matchStr('token', 'post')
    ->matchStr('name', 'post', maxLen: 20, emptyAble: true)
    ->matchStr('text', 'post', maxLen: 1000)
    ->matchFile('image0', ['image/jpeg'], 8192, emptyAble: true, requestMethod: 'post')
    ->matchFile('image1', ['image/jpeg'], 8192, emptyAble: true, requestMethod: 'post')
    ->matchFile('image2', ['image/jpeg'], 8192, emptyAble: true, requestMethod: 'post')
    ->match(
        function (string $text, string $name) {
            if (MimimalCmsConfig::$urlRoot !== '')
                return false;

            if (AppConfig::$isStaging && SecretsConfig::$stagingBasicAuthPassword) {
                $auth = getBasicAuthCredentials();
                if ($auth['user'] !== SecretsConfig::$stagingBasicAuthUser || $auth['pass'] !== SecretsConfig::$stagingBasicAuthPassword) {
                    header('WWW-Authenticate: Basic realm="Staging Comment API"');
                    throw new UnauthorizedException(
                        'Basic authentication is required to post comments on staging environment.'
                    );
                }
            }

            return removeAllZeroWidthCharacters($text)
                ? ['name' => removeAllZeroWidthCharacters($name) ? $name : '']
                : false;
        },
        'post'
    )
    ->middleware([VerifyCsrfToken::class], 'get');

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
    [CommentReportApiController::class, 'reportComment']
)
    ->matchNum('comment_id', min: 1)
    ->match(fn() => MimimalCmsConfig::$urlRoot === '')
    ->matchStr('token');

// コメント画像サムネイルAPI
Route::path(
    'comment-img/thumb/{filename}@get',
    [CommentImageThumbnailController::class, 'index']
)
    ->match(fn() => MimimalCmsConfig::$urlRoot === '');

// 画像通報API
Route::path(
    'comment_image_report/{image_id}@post',
    [CommentReportApiController::class, 'reportImage']
)
    ->matchNum('image_id', min: 1)
    ->match(fn() => MimimalCmsConfig::$urlRoot === '')
    ->matchStr('token');

Route::path('admin/cookie')
    ->match(function (AdminAuthService $adminAuthService, ?string $key) {
        sessionStart();
        if (!$adminAuthService->registerAdminCookie($key))
            return false;

        return redirect();
    });

// 管理者かどうかをサーバ側で検証する軽量エンドポイント（広告ブロック検出 ad_guard から呼ぶ）。
// admin-enable クッキー(JS可視・偽造可)を信用せず、HttpOnly の admin クッキーを auth() で検証する。
// Cloudflare 側で X-Ocg-Client ヘッダ必須＋レート制限（直叩き・総当たり対策）を併用する。
Route::path('admin-check')
    ->match(function (AdminAuthService $adminAuthService) {
        try {
            $ok = $adminAuthService->auth();
        } catch (\Throwable $e) {
            $ok = false;
        }
        noStore();
        return response($ok ? '1' : '0', $ok ? 200 : 403);
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
        if (MimimalCmsConfig::$urlRoot !== '')
            return false;

        $adminAuthService->auth();
        noStore();
    });

// 管理者操作ログ
Route::path('admin/log/admin-action', [AdminCommentLogController::class, 'index'])
    ->matchNum('page', min: 1, default: 1, emptyAble: true)
    ->match(function (AdminAuthService $adminAuthService) {
        return MimimalCmsConfig::$urlRoot === '' && $adminAuthService->auth() ? noStore() : false;
    });

Route::path('admin/log/admin-action/detail', [AdminCommentLogController::class, 'detail'])
    ->matchNum('id', min: 1)
    ->match(function (AdminAuthService $adminAuthService) {
        return MimimalCmsConfig::$urlRoot === '' && $adminAuthService->auth() ? noStore() : false;
    });

Route::path('admin/log/{type}', [LogController::class, 'cronLog'])
    ->matchStr('type', regex: ['ja-cron', 'th-cron', 'tw-cron'])
    ->matchNum('page', min: 1, default: 1, emptyAble: true)
    ->match(function () {
        noStore();
        return MimimalCmsConfig::$urlRoot === '';
    });

// 管理者画像管理ページ
Route::path('admin/comment-images', [AdminCommentImageController::class, 'commentImages'])
    ->matchStr('tab', regex: ['deleted', 'active'], default: 'active', emptyAble: true)
    ->matchNum('page', min: 1, default: 1, emptyAble: true)
    ->match(function (AdminAuthService $adminAuthService) {
        if (MimimalCmsConfig::$urlRoot !== '')
            return false;
        if (!$adminAuthService->auth())
            return false;
        noStore();
    });

// シャドウバンユーザー一覧
Route::path('admin/ban-users', [AdminBanUserController::class, 'index'])
    ->matchNum('page', min: 1, default: 1, emptyAble: true)
    ->match(function (AdminAuthService $adminAuthService) {
        return MimimalCmsConfig::$urlRoot === '' && $adminAuthService->auth() ? noStore() : false;
    });

// おすすめタグ定義(data/{lang}.json)編集GUI（管理者専用・ja/th/tw対応・ローカル編集用途）
// 編集対象は urlRoot に対応する {lang}.json（'' => ja, /tw => tw, /th => th）。
// GETでもVerifyCsrfTokenを通し、CSRF-Tokenクッキーを発行する（保存POSTのX-CSRF-Token用）
Route::path('admin/recommend-tags', [AdminRecommendTagController::class, 'index'])
    ->middleware([VerifyCsrfToken::class])
    ->match(function (AdminAuthService $adminAuthService) {
        if (!$adminAuthService->auth())
            return false;
        noStore();
    });

// おすすめタグ定義の保存（CSRF必須・管理者専用・urlRootの{lang}.jsonへ）
Route::path('admin/recommend-tags/save@post', [AdminRecommendTagController::class, 'save'])
    ->middleware([VerifyCsrfToken::class]);

// 全レコードへの即時再適用をバックグラウンドで開始（CSRF必須・管理者専用・urlRoot別）
Route::path('admin/recommend-tags/rebuild@post', [AdminRecommendTagController::class, 'rebuild'])
    ->middleware([VerifyCsrfToken::class]);

// Adminer Database Tool
Route::path('admin/adminer@get@post', [AdminPageController::class, 'adminer'])
    ->match(function () {
        return MimimalCmsConfig::$urlRoot === '';
    });

// ルーム個別ページ静的キャッシュ(oc_page_cache)のバックフィル実行（admin・背景実行）
Route::path('admin/genocpagecache/{lang}', [AdminPageController::class, 'genocpagecache'])
    ->match(function () {
        return MimimalCmsConfig::$urlRoot === '';
    })
    ->matchStr('lang', regex: ['ja', 'th', 'tw']);

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
    ->matchNum('flag', min: 0, max: 5);

Route::path(
    'admin-api/deleteuser@post@get',
    [AdminEndPointController::class, 'deleteuser']
)
    ->matchNum('id')
    ->match(fn() => MimimalCmsConfig::$urlRoot === '')
    ->matchNum('commentId');

Route::path(
    'admin-api/commentbanroom@post@get',
    [AdminEndPointController::class, 'commentbanroom']
)
    ->match(fn() => MimimalCmsConfig::$urlRoot === '')
    ->matchNum('id');

Route::path(
    'admin-api/commentunbanroom@post@get',
    [AdminEndPointController::class, 'commentunbanroom']
)
    ->match(fn() => MimimalCmsConfig::$urlRoot === '')
    ->matchNum('id');

Route::path('admin-api/commentimagestorage@post', [AdminEndPointController::class, 'commentImageStorageSize'])
    ->match(fn() => MimimalCmsConfig::$urlRoot === '');

Route::path(
    'admin-api/deletecommentimage@post@get',
    [AdminEndPointController::class, 'deleteCommentImage']
)
    ->match(fn() => MimimalCmsConfig::$urlRoot === '')
    ->matchNum('imageId');

Route::path(
    'admin-api/deletedcommentimages@post@get',
    [AdminEndPointController::class, 'deleteDeletedCommentImages']
)
    ->match(fn() => MimimalCmsConfig::$urlRoot === '');

Route::path(
    'admin-api/deletecommentsall@post@get',
    [AdminEndPointController::class, 'deletecommentsall']
)
    ->match(fn() => MimimalCmsConfig::$urlRoot === '')
    ->matchNum('id');

Route::path(
    'admin-api/restorecommentsall@post@get',
    [AdminEndPointController::class, 'restorecommentsall']
)
    ->match(fn() => MimimalCmsConfig::$urlRoot === '')
    ->matchNum('id');

Route::path(
    'admin-api/harddeletecommentsall@post@get',
    [AdminEndPointController::class, 'harddeletecommentsall']
)
    ->match(fn() => MimimalCmsConfig::$urlRoot === '')
    ->matchNum('id');

Route::path(
    'admin-api/bulkshadowban@post@get',
    [AdminEndPointController::class, 'bulkshadowban']
)
    ->match(fn() => MimimalCmsConfig::$urlRoot === '')
    ->matchNum('id');

Route::path(
    'admin-api/unbanuser@post@get',
    [AdminEndPointController::class, 'unbanuser']
)
    ->match(fn() => MimimalCmsConfig::$urlRoot === '')
    ->matchNum('banId');

Route::path(
    'admin-api/comment-image@get',
    [AdminEndPointController::class, 'commentImage']
)
    ->match(fn() => MimimalCmsConfig::$urlRoot === '')
    ->matchStr('filename');

Route::path('oc/0/admin', [PolicyPageController::class, 'index'])
    ->match(function (AdminAuthService $adminAuthService) {
        if (!$adminAuthService->auth())
            return false;
        noStore();
        return ['isAdmin' => true];
    });

Route::path(
    'oc/{open_chat_id}/admin',
    [OpenChatPageController::class, 'index']
)
    ->matchNum('open_chat_id', min: 1)
    ->match(function (AdminAuthService $adminAuthService) {
        if (!$adminAuthService->auth())
            return false;
        noStore();
        return ['isAdminPage' => '1'];
    });

Route::path('furigana@POST')
    ->match(fn() => MimimalCmsConfig::$urlRoot === '')
    ->matchStr('json');

Route::path('furigana/guideline')
    ->match(function () {
        if (MimimalCmsConfig::$urlRoot !== '')
            return false;
    });

Route::path(
    'furigana/defamation-guideline',
    [FuriganaPageController::class, 'defamationGuideline']
)
    ->match(function () {
        if (MimimalCmsConfig::$urlRoot !== '')
            return false;
    });

// データベースAPI（読み取り専用）
// /database/{username}/query・/schema 用の認証（いずれも POST）:
//   - 全ユーザー Basic認証必須
//   - 管理者: {username}=admin、Basic認証（admin / adminApiKey）のみで許可。
//     POSTボディの password は不要（URLパスはフレームワークが小文字化するため、
//     大文字を含むキーは {username} に直接入れても照合できない。Basic認証は小文字化されない）
//   - 登録ユーザー: Basic認証（username / 登録パスワード）に加えて、
//     POSTボディの password（登録パスワードを SHA256 した16進文字列）も必要（二重認証）
$databaseApiPostAuth = function (string $username, $password = null) {
    if (MimimalCmsConfig::$urlRoot !== '') {
        return false;
    }

    allowCORS(); // OPTIONS プリフライトはここで終了

    $requireBasicAuth = function (): void {
        header('WWW-Authenticate: Basic realm="Database SQL API"');
        response([
            'status' => 'error',
            'message' => 'Basic authentication is required to access the database API. '
                . 'Sorry, we initially forgot to include this requirement in the docs.',
        ], 401)->send();
        exit;
    };

    // 1. 管理者: Basic認証（admin / adminApiKey）のみで許可（POST password 不要）
    if ($username === 'admin') {
        $auth = getBasicAuthCredentials();
        if (
            SecretsConfig::$adminApiKey !== ''
            && $auth['user'] === 'admin'
            && hash_equals(SecretsConfig::$adminApiKey, $auth['pass'])
        ) {
            return true;
        }
        $requireBasicAuth();
    }

    // 2. 登録ユーザー: Basic認証 + POSTの password（SHA256）の二重認証
    $apiUsers = class_exists(ApiUser::class) ? ApiUser::$apiUser : [];
    foreach ($apiUsers as $apiUser) {
        if ($apiUser['username'] === $username) {
            $auth = getBasicAuthCredentials();
            if ($auth['user'] !== $apiUser['username'] || $auth['pass'] !== $apiUser['password']) {
                $requireBasicAuth();
            }

            $expected = hash('sha256', $apiUser['password']);
            if (!is_string($password) || !hash_equals($expected, strtolower($password))) {
                response([
                    'status' => 'error',
                    'message' => 'Authentication failed.',
                ], 401)->send();
                exit;
            }
            return true;
        }
    }

    // 3. 未登録ユーザーは 403
    response([
        'status' => 'error',
        'message' => 'User not found',
    ], 403)->send();
    exit;
};

Route::path(
    'database/{username}/query@post@options',
    [DatabaseApiController::class, 'index']
)
    ->match(function (string $username, $password = null, $stmt = null) use ($databaseApiPostAuth) {
        if (!$databaseApiPostAuth($username, $password)) {
            return false;
        }

        if (!Validator::str($stmt)) {
            response([
                'status' => 'error',
                'message' => 'The "stmt" parameter is required and must be a string.',
            ], 400)->send();
            exit;
        }
    });

Route::path(
    'database/{username}/schema@post@options',
    [DatabaseApiController::class, 'schema']
)
    ->match($databaseApiPostAuth);

// 公開 MCP エンドポイント（AI アシスタント向け・認証なし）
// レートリミット・SQLガード・非公開テーブルの遮断は McpServerService 側。
// CORS/OPTIONS・405 はコントローラ側で処理するため GET/OPTIONS もルートに含める。
// Cloudflare の bot チャレンジ除外(/mcp)とセット（oc-infra 参照）。
Route::path(
    'mcp@post@get@options',
    [McpApiController::class, 'index']
)
    ->match(function () {
        if (MimimalCmsConfig::$urlRoot !== '') {
            return false;
        }
    });

cache();
Route::run();
