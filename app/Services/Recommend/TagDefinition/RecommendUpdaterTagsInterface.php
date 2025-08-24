<?php

namespace App\Services\Recommend\TagDefinition;

interface RecommendUpdaterTagsInterface
{
    /**
     * @param 'oc.name'|'oc.description'|null $column
     * @return array<string,(string|array{string, string[]})[]>
     */
    function getStrongestTags(?string $column = null): array;

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
