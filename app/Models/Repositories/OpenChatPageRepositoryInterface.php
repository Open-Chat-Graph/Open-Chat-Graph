<?php

declare(strict_types=1);

namespace App\Models\Repositories;

interface OpenChatPageRepositoryInterface
{
    public function getOpenChatById(int $id): array|false;

    public function getOpenChatByIdWithTag(int $id): array|false;

    public function isExistsOpenChat(int $id): bool;

    /**
     * 指定した id が「過去に発番されていてもおかしくない範囲」内か。
     * MAX(open_chat.id) 以下なら true (= 過去存在していた / 現在削除済みの可能性あり)。
     */
    public function isWithinIdRange(int $id): bool;

    /** @param int[] $ids
     *  @return array<int, string> [id => name] */
    public function getOpenChatNamesByIds(array $ids): array;
}
