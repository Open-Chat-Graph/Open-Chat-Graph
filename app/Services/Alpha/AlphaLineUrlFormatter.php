<?php

declare(strict_types=1);

namespace App\Services\Alpha;

use App\Config\AppConfig;

/**
 * open_chat.url（ハッシュ or 完全URL）を LINE 形式の完全URLへ変換する。
 *
 * AlphaApiController の search / stats / batchStats / periodGrowth / Labsランキング整形に
 * 重複していた同一ロジックの統合先（AlphaPagePathNormalizer と同じ純粋 static 流儀）。
 */
final class AlphaLineUrlFormatter
{
    /**
     * すでに完全なURL(http...)はそのまま、ハッシュのみは
     * https://line.me/ti/g2/{hash} 形式に変換する。空は ''。
     */
    public static function toLineUrl(?string $url): string
    {
        if (empty($url)) {
            return '';
        }
        if (strpos($url, 'http') === 0) {
            return $url;
        }
        $hash = trim($url, '/');
        return $hash !== '' ? AppConfig::LINE_APP_URL . $hash : '';
    }
}
