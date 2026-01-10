<?php

declare(strict_types=1);

namespace App\Views\Classes\Dto;

interface CommentArgDtoFactoryInterface
{
    /**
     * Create comment argument DTO (associative array)
     *
     * @param int $openChatId OpenChat ID
     * @return array{baseUrl: string, openChatId: int}
     */
    public function create(int $openChatId): array;
}
