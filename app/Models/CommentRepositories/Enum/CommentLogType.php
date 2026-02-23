<?php

declare(strict_types=1);

namespace App\Models\CommentRepositories\Enum;

enum CommentLogType: string
{
    case AddComment = 'AddComment';
    case Report = 'Report';
    case AddLike = 'AddLike';
    case DeleteLike = 'DeleteLike';
    case ImageReport = 'ImageReport';
}