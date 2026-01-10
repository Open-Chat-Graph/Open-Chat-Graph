<?php

declare(strict_types=1);

namespace App\ServiceProvider;

use App\Models\CommentRepositories\Api\ApiCommentListRepository;
use App\Models\CommentRepositories\Api\ApiCommentPostRepository;
use App\Models\CommentRepositories\CommentListRepositoryInterface;
use App\Models\CommentRepositories\CommentPostRepositoryInterface;

class ApiCommentListControllerServiceProvider implements ServiceProviderInterface
{
    function register(): void
    {
        app()->bind(CommentListRepositoryInterface::class, ApiCommentListRepository::class);
        app()->bind(CommentPostRepositoryInterface::class, ApiCommentPostRepository::class);
    }
}
