<?php

namespace App\Config\Shadow;

class ConstructorInjectionMapper
{
    static $map = [
        \Shadow\StringCryptorInterface::class => \Shadow\StringCryptor::class,
        \Shadow\JsonStorageInterface::class => \Shadow\JsonStorage::class,
        \Shadow\DBInterface::class => \Shadow\DB::class,
        \Shadow\Kernel\ViewInterface::class => \Shadow\Kernel\View::class,
        \Shadow\File\FileValidatorInterface::class => \Shadow\File\FileValidator::class,
        \Shadow\File\Image\ImageStoreInterface::class => \Shadow\File\Image\ImageStore::class,
        \Shadow\File\Image\GdImageFactoryInterface::class => \Shadow\File\Image\GdImageFactory::class,

        \App\Models\Repositories\Log\LogRepositoryInterface::class => \App\Models\Repositories\Log\LogRepository::class,

        \App\Models\Repositories\OpenChatRepositoryInterface::class => \App\Models\Repositories\OpenChatRepository::class,
        \App\Models\Repositories\UpdateOpenChatRepositoryInterface::class => \App\Models\Repositories\UpdateOpenChatRepository::class,
        \App\Models\Repositories\DeleteOpenChatRepositoryInterface::class => \App\Models\Repositories\DeleteOpenChatRepository::class,
        \App\Models\Repositories\ParallelDownloadOpenChatStateRepositoryInterface::class => \App\Models\Repositories\ParallelDownloadOpenChatStateRepository::class,
        \App\Models\Repositories\SyncOpenChatStateRepositoryInterface::class => \App\Models\Repositories\SyncOpenChatStateRepository::class,

        \App\Models\Repositories\Statistics\StatisticsRepositoryInterface::class => \App\Models\SQLite\Repositories\Statistics\SqliteStatisticsRepository::class,
        \App\Models\Repositories\Statistics\StatisticsRankingUpdaterRepositoryInterface::class => \App\Models\SQLite\Repositories\Statistics\SqliteStatisticsRankingUpdaterRepository::class,
        \App\Models\Repositories\Statistics\StatisticsPageRepositoryInterface::class => \App\Models\SQLite\Repositories\Statistics\SqliteStatisticsPageRepository::class,

        \App\Models\Repositories\RankingPosition\RankingPositionRepositoryInterface::class => \App\Models\SQLite\Repositories\RankingPosition\SqliteRankingPositionRepository::class,
        \App\Models\Repositories\RankingPosition\RankingPositionPageRepositoryInterface::class => \App\Models\SQLite\Repositories\RankingPosition\SqliteRankingPositionPageRepository::class,
        \App\Models\Repositories\RankingPosition\RankingPositionHourRepositoryInterface::class => \App\Models\RankingPositionDB\Repositories\RankingPositionHourRepository::class,
        \App\Models\Repositories\RankingPosition\RankingPositionHourPageRepositoryInterface::class => \App\Models\RankingPositionDB\Repositories\RankingPositionHourPageRepository::class,
        \App\Models\Repositories\RankingPosition\HourMemberRankingUpdaterRepositoryInterface::class => \App\Models\RankingPositionDB\Repositories\HourMemberRankingUpdaterRepository::class,
        
        \App\Models\Repositories\OpenChatListRepositoryInterface::class => \App\Models\Repositories\OpenChatListRepository::class,
        \App\Models\Repositories\OpenChatPageRepositoryInterface::class => \App\Models\Repositories\OpenChatPageRepository::class,

        \App\Models\Repositories\OpenChatDataForUpdaterWithCacheRepositoryInterface::class => \App\Models\Repositories\OpenChatDataForUpdaterWithCacheRepository::class,

        \App\Models\CommentRepositories\CommentListRepositoryInterface::class => \App\Models\CommentRepositories\CommentListRepository::class,
        \App\Models\CommentRepositories\CommentLogRepositoryInterface::class => \App\Models\CommentRepositories\CommentLogRepository::class,
        \App\Models\CommentRepositories\CommentPostRepositoryInterface::class => \App\Models\CommentRepositories\CommentPostRepository::class,
        \App\Models\CommentRepositories\DeleteCommentRepositoryInterface::class => \App\Models\CommentRepositories\DeleteCommentRepository::class,
        \App\Models\CommentRepositories\LikePostRepositoryInterface::class => \App\Models\CommentRepositories\LikePostRepository::class,
        \App\Models\CommentRepositories\RecentCommentListRepositoryInterface::class => \App\Models\CommentRepositories\RecentCommentListRepository::class,
        
        \App\Services\Auth\AuthInterface::class => \App\Services\Auth\Auth::class,
    ];
}
