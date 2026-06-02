<?php

declare(strict_types=1);

namespace App\Services\Recommend\TagDefinition\Ja;

use App\Services\Recommend\TagDefinition\TagMetadata;
use Shared\MimimalCmsConfig;

class RecommendUtility
{
    static function extractTag(string|int $str): string
    {
        if (MimimalCmsConfig::$urlRoot !== '') {
            return (string)$str;
        }

        $str = (string)$str;

        // 文末の括弧内のテキストを抽出する正規表現
        if (preg_match('/（(.*)）$/', $str, $matches)) {
            // 括弧内のテキストが見つかった場合
            $textInsideParentheses = $matches[1];

            // 「／」で分割し、最後の要素を取得
            $parts = explode('／', $textInsideParentheses);
            $str = array_pop($parts);
        }

        return TagMetadata::omitPattern()[$str] ?? $str;
    }

    static function getValidTag(string|int $str): string|false
    {
        $lowercaseTag = strtolower((string)$str);
        foreach (TagMetadata::omitPattern() as $key => $originalTag) {
            if (strtolower($key) === $lowercaseTag) {
                return $originalTag;
            }
        }
        return false;
    }
}
