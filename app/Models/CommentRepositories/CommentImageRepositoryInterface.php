<?php

namespace App\Models\CommentRepositories;

interface CommentImageRepositoryInterface
{
    function addImages(int $commentId, array $filenames): void;

    /** @return array{ filename: string, sort_order: int }[] */
    function getImagesByCommentId(int $commentId): array;

    /**
     * @param int[] $commentIds
     * @return array<int, array{ id: int, filename: string }[]> commentId => images
     */
    function getImagesByCommentIds(array $commentIds): array;

    /**
     * @return string[] deleted filenames
     */
    function deleteByCommentId(int $commentId): array;

    /**
     * @return array{ id: int, comment_id: int, filename: string }[]
     */
    function getDeletedCommentImages(int $limit = 50, int $offset = 0): array;

    function getDeletedCommentImageCount(): int;

    /**
     * @return array{ id: int, comment_id: int, filename: string }[]
     */
    function getActiveCommentImages(int $limit = 50, int $offset = 0): array;

    function getActiveCommentImageCount(): int;

    /** @return array{ count: int } */
    function getImageStats(): array;

    function deleteImageById(int $imageId): string|false;

    function getCommentIdByImageId(int $imageId): int|false;

    function getFilenameByImageId(int $imageId): string|false;

    function getCommentFlagByFilename(string $filename): int|false;
}
