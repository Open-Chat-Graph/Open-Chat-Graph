<?php

namespace App\Services\Comment;

interface CommentImageServiceInterface
{
    /**
     * @param array[] $files Array of $_FILES-style arrays
     * @return string[] Array of saved filenames
     */
    function processAndStore(array $files): array;

    /** @param string[] $filenames */
    function hideImages(array $filenames): void;

    /** @param string[] $filenames */
    function restoreImages(array $filenames): void;

    /** @param string[] $filenames */
    function deleteImages(array $filenames): void;

    /** @return array{ total_size: int, deleted_size: int } sizes in bytes */
    function calculateStorageSize(array $deletedFilenames): array;
}
