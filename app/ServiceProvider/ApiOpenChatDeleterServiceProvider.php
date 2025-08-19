<?php

declare(strict_types=1);

namespace App\ServiceProvider;

use App\Services\OpenChat\Api\ApiOpenChatDeleter;
use App\Services\OpenChat\Updater\OpenChatDeleterInterface;

class ApiOpenChatDeleterServiceProvider implements ServiceProviderInterface
{
    function register(): void
    {
        app()->bind(OpenChatDeleterInterface::class, ApiOpenChatDeleter::class);
    }
}
