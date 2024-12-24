<?php

declare(strict_types=1);

namespace App\Services\OpenChat\Dto;

use App\Services\OpenChat\Utility\OpenChatServicesUtility;

class OpenChatUpdaterDto
{
    public int $open_chat_id;
    public bool $delete_flag = false;
    public ?string $updated_at = null;

    public ?string $emid = null;
    public ?string $name = null;
    public ?string $desc = null;
    public ?string $profileImageObsHash = null;
    public ?int $memberCount = null;
    public ?int $createdAt = null;
    public ?int $category = null;
    public ?int $emblem = null;
    public ?int $joinMethodType = null;
    public ?string $invitationTicket = null;

    function __construct(int $open_chat_id)
    {
        $this->open_chat_id = $open_chat_id;
    }

    function rewriteUpdateAtTime(string $dateTime)
    {
        $this->updated_at = $dateTime;
    }

    function getUpdateItems(): ?string
    {
        /** @var array{ name:bool,description:bool,img_url:bool,join_method_type:bool,category:bool } */
        $updateItems = [
            'name' => $this->name !== null,
            'description' => $this->desc !== null,
            'img_url' => $this->profileImageObsHash !== null,
            'join_method_type' => $this->joinMethodType !== null,
            'category' => $this->category !== null,
            'emblem' => $this->emblem !== null,
        ];

        return array_filter($updateItems, fn($item) => $item) ? json_encode($updateItems) : null;
    }
}
