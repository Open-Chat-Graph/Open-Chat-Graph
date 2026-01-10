<?php

declare(strict_types=1);

namespace App\Services\OpenChat\Api;

use App\Config\SecretsConfig;
use App\Views\Classes\Dto\CommentArgDtoFactoryInterface;

class ApiCommentArgDtoFactory implements CommentArgDtoFactoryInterface
{
    /**
     * Create comment argument DTO for archive API pages
     *
     * @param int $openChatId OpenChat ID
     * @return array{baseUrl: string, openChatId: int}
     */
    public function create(int $openChatId): array
    {
        return [
            'baseUrl' => url('comment', SecretsConfig::$adminApiKey, 'oc'),
            'openChatId' => $openChatId
        ];
    }
}
