<?php

declare(strict_types=1);

namespace App\Models\CommentRepositories\Dto;

class CommentPostApiArgs
{
    function __construct(
        public string $user_id,
        public int $open_chat_id,
        public string $name,
        public string $text,
        public int $flag,
    ) {
    }
}
