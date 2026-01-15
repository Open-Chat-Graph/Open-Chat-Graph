<?php

namespace Shared;

class MimimalCmsConfig
{
    /**
     * Maps interface classes to their concrete implementations.
     * Keys are interface classes, and values are the corresponding injected concrete classes.
     *
     * @var array<string, string>
     */
    public static array $constructorInjectionMap = [
        \Shadow\StringCryptorInterface::class => \Shadow\StringCryptor::class,
        \Shadow\DBInterface::class => \Shadow\DB::class,
        \Shadow\Kernel\ViewInterface::class => \Shadow\Kernel\View::class,
        \Shadow\File\FileValidatorInterface::class => \Shadow\File\FileValidator::class,
        \Shadow\File\Image\ImageStoreInterface::class => \Shadow\File\Image\ImageStore::class,
        \Shadow\File\Image\GdImageFactoryInterface::class => \Shadow\File\Image\GdImageFactory::class,

        \App\Models\Repositories\Log\LogRepositoryInterface::class => \App\Models\Repositories\Log\LogRepository::class,

        \App\Models\Repositories\OpenChatRepositoryInterface::class => \App\Models\Repositories\OpenChatRepository::class,
        \App\Models\Repositories\UpdateOpenChatRepositoryInterface::class => \App\Models\Repositories\UpdateOpenChatRepository::class,
        \App\Models\Repositories\DeleteOpenChatRepositoryInterface::class => \App\Models\Repositories\DeleteOpenChatRepository::class,
        \App\Models\Repositories\SyncOpenChatStateRepositoryInterface::class => \App\Models\Repositories\SyncOpenChatStateRepository::class,
        \App\Models\Repositories\MemberChangeFilterCacheRepositoryInterface::class => \App\Models\Repositories\MemberChangeFilterCacheRepository::class,

        \App\Models\Repositories\Statistics\StatisticsRepositoryInterface::class => \App\Models\SQLite\Repositories\Statistics\SqliteStatisticsRepository::class,
        \App\Models\Repositories\Statistics\StatisticsRankingUpdaterRepositoryInterface::class => \App\Models\SQLite\Repositories\Statistics\SqliteStatisticsRankingUpdaterRepository::class,
        \App\Models\Repositories\Statistics\StatisticsPageRepositoryInterface::class => \App\Models\SQLite\Repositories\Statistics\SqliteStatisticsPageRepository::class,

        \App\Services\Crawler\Config\OpenChatCrawlerConfigInterface::class => \App\Services\Crawler\Config\OpenChatCrawlerConfig::class,
        \App\Models\Repositories\RankingPosition\RankingPositionRepositoryInterface::class => \App\Models\SQLite\Repositories\RankingPosition\SqliteRankingPositionRepository::class,
        \App\Models\Repositories\RankingPosition\RankingPositionPageRepositoryInterface::class => \App\Models\SQLite\Repositories\RankingPosition\SqliteRankingPositionPageRepository::class,
        \App\Models\Repositories\RankingPosition\RankingPositionHourRepositoryInterface::class => \App\Models\RankingPositionDB\Repositories\RankingPositionHourRepository::class,
        \App\Models\Repositories\RankingPosition\RankingPositionHourPageRepositoryInterface::class => \App\Models\RankingPositionDB\Repositories\RankingPositionHourPageRepository::class,
        \App\Models\Repositories\RankingPosition\HourMemberRankingUpdaterRepositoryInterface::class => \App\Models\RankingPositionDB\Repositories\HourMemberRankingUpdaterRepository::class,
        
        \App\Models\Repositories\OpenChatListRepositoryInterface::class => \App\Models\Repositories\OpenChatListRepository::class,
        \App\Models\Repositories\OpenChatRecentListRepositoryInterface::class => \App\Models\Repositories\OpenChatListRepository::class,
        \App\Models\Repositories\OpenChatPageRepositoryInterface::class => \App\Models\Repositories\OpenChatPageRepository::class,

        \App\Models\Repositories\OpenChatDataForUpdaterWithCacheRepositoryInterface::class => \App\Models\Repositories\OpenChatDataForUpdaterWithCacheRepository::class,

        \App\Models\CommentRepositories\CommentListRepositoryInterface::class => \App\Models\CommentRepositories\CommentListRepository::class,
        \App\Models\CommentRepositories\CommentLogRepositoryInterface::class => \App\Models\CommentRepositories\CommentLogRepository::class,
        \App\Models\CommentRepositories\CommentPostRepositoryInterface::class => \App\Models\CommentRepositories\CommentPostRepository::class,
        \App\Models\CommentRepositories\DeleteCommentRepositoryInterface::class => \App\Models\CommentRepositories\DeleteCommentRepository::class,
        \App\Models\CommentRepositories\LikePostRepositoryInterface::class => \App\Models\CommentRepositories\LikePostRepository::class,
        \App\Models\CommentRepositories\RecentCommentListRepositoryInterface::class => \App\Models\CommentRepositories\RecentCommentListRepository::class,
        
        \App\Services\Auth\AuthInterface::class => \App\Services\Auth\Auth::class,
        
        \App\Services\OpenChat\Updater\OpenChatDeleterInterface::class => \App\Services\OpenChat\Updater\OpenChatDeleter::class,
        
        \App\Views\Classes\Dto\RankingPositionChartArgDtoFactoryInterface::class => \App\Views\Classes\Dto\RankingPositionChartArgDtoFactory::class,
        \App\Views\Classes\Dto\CommentArgDtoFactoryInterface::class => \App\Views\Classes\Dto\CommentArgDtoFactory::class,
        \App\Views\Classes\CollapseKeywordEnumerationsInterface::class => \App\Views\Classes\CollapseKeywordEnumerations::class,

        \App\Services\Storage\FileStorageInterface::class => \App\Services\Storage\FileStorageService::class,
    ];

    // URL root
    public static string $urlRoot = '';

    // Directories
    public static string $publicDir = __DIR__ . '/../public';
    public static string $viewsDir = __DIR__ . '/../app/Views';

    // Default options for cookies
    public static bool $cookieDefaultSecure = false;
    public static bool $cookieDefaultHttpOnly = true;
    public static string $cookieDefaultSameSite = 'lax';

    // Options for session
    public static string $flashSessionKeyName = 'mimimalFlashSession';
    public static string $sessionKeyName = 'mimimalSession';

    // File validator
    public static int $defaultMaxFileSize = 20480;

    // Database configuration
    public static string $dbHost = '';
    public static string $dbName = 'cf782105_ocreview';
    public static string $dbUserName = '';
    public static string $dbPassword = '';
    public static bool $dbAttrPersistent = false;

    // String cryptor configuration
    public static string $stringCryptorHkdfKey = '';
    public static string $stringCryptorOpensslKey = '';

    /**
     * Defines a mapping of HTTP errors to their corresponding HTTP status codes and messages.
     * 
     * Keys are the classes of the exceptions, and values are arrays with two elements:
     *   - httpCode: the HTTP status code to be returned
     *   - httpStatusMessage: the corresponding HTTP status message
     * 
     * @var array<string, array{httpCode: int, log: bool, httpStatusMessage: string}>
     */
    public static array $httpErrors = [
        \Shared\Exceptions\NotFoundException::class =>         ['httpCode' => 404, 'log' => false, 'httpStatusMessage' => 'Not Found'],
        \Shared\Exceptions\MethodNotAllowedException::class => ['httpCode' => 405, 'log' => false, 'httpStatusMessage' => 'Method Not Allowed'],
        \Shared\Exceptions\BadRequestException::class =>       ['httpCode' => 400, 'log' => false,  'httpStatusMessage' => 'Bad Request'],
        \Shared\Exceptions\ValidationException::class =>       ['httpCode' => 400, 'log' => false,  'httpStatusMessage' => 'Bad Request'],
        \Shared\Exceptions\InvalidInputException::class =>     ['httpCode' => 400, 'log' => false,  'httpStatusMessage' => 'Bad Request'],
        \Shared\Exceptions\UploadException::class =>           ['httpCode' => 400, 'log' => true,  'httpStatusMessage' => 'Bad Request'],
        \Shared\Exceptions\SessionTimeoutException::class =>   ['httpCode' => 401, 'log' => true,  'httpStatusMessage' => 'Unauthorized'],
        \Shared\Exceptions\UnauthorizedException::class =>     ['httpCode' => 401, 'log' => true,  'httpStatusMessage' => 'Unauthorized'],
        \Shared\Exceptions\ThrottleRequestsException::class => ['httpCode' => 429, 'log' => true,  'httpStatusMessage' => 'Too Many Requests'],
    ];

    // Display exceptions
    public static bool $exceptionHandlerDisplayBeforeObClean = true;
    public static bool $exceptionHandlerDisplayErrorTraceDetails = true;

    // Exceptions Log directory.
    public static string $exceptionLogDirectory = __DIR__ . '/../storage/exception.log';

    /**
     * The path to hide from exception error trace.
     * This constant is used to remove the unnecessary path from the beginning of
     * the path included in exception error trace.
     */
    public static string $errorPageHideDirectory = '';

    /**
     * This constant is used to specify the document root path name.
     * The path name after this constant is concatenated with the GitHub URL.
     */
    public static string $errorPageDocumentRootName = '';

    /**
     * This constant is used to specify the GitHub URL for displaying the source code in the exception error trace.
     * The path name after the DOCUMENT_ROOT_NAME constant is concatenated with this URL.
     */
    public static string $errorPageGitHubUrl = 'https://github.com/mimimiku778/Open-Chat-Graph/blob/main/';
}
