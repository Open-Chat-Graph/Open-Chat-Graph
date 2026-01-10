<?php

declare(strict_types=1);

namespace App\Views\Classes\Dto;

class CommentArgDtoFactory implements CommentArgDtoFactoryInterface
{
    /**
     * Create comment argument DTO for standard pages
     *
     * @param int $openChatId OpenChat ID
     * @return array{baseUrl: string, openChatId: int}
     */
    public function create(int $openChatId): array
    {
        return [
            'baseUrl' => url(),
            'openChatId' => $openChatId
        ];
    }
}
