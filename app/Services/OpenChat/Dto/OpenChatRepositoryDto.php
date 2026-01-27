<?php

declare(strict_types=1);

namespace App\Services\OpenChat\Dto;

class OpenChatRepositoryDto
{
    public int $open_chat_id;
    public string $emid;
    public string $name;
    public string $desc;
    public string $profileImageObsHash;
    public int $memberCount;
    public ?int $createdAt;
    public ?int $category;
    public ?int $emblem;
    public int $joinMethodType;
    public ?string $invitationTicket;

    /**
     * @param array{ emid: string, name: string, description: string, img_url: string, member: int, api_created_at: int | null, category: int | null, emblem: int | null } $openChatData
     */
    function __construct(int $id, array $openChatData)
    {
        $this->open_chat_id = $id;
        $this->emid = $openChatData['emid'];
        $this->name = $openChatData['name'];
        $this->desc = $openChatData['description'];
        $this->profileImageObsHash = $openChatData['img_url'];
        $this->memberCount = $openChatData['member'];
        $this->createdAt = $openChatData['api_created_at'];
        $this->category = $openChatData['category'];
        $this->emblem = $openChatData['emblem'];
        $this->joinMethodType = $openChatData['join_method_type'];
        $this->invitationTicket = $openChatData['url'];
    }
}
