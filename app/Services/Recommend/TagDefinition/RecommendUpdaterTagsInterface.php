<?php

namespace App\Services\Recommend\TagDefinition;

interface RecommendUpdaterTagsInterface
{
    /**
     * @return array<string,(string|array{string, string[]})[]>
     */
    function getStrongestTags(): array;

    /**
     * @return array<string,(string|array{string,string[]})[]>
     */
    function getBeforeCategoryNameTags(): array;

    /**
     * @return (string|array{string,string[]})[]
     */
    function getNameStrongTags(): array;

    /**
     * @return (string|array{string,string[]})[]
     */
    function getDescStrongTags(): array;

    /**
     * @return (string|array{string,string[]})[]
     */
    function getAfterDescStrongTags(): array;
}
