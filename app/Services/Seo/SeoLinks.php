<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\Config\AppConfig;

final class SeoLinks
{
    /**
     * Alternate locales are emitted only for routes whose content is guaranteed
     * to represent the same entity in every locale.
     *
     * @return array<string, string>
     */
    public static function localeAlternates(string $path = ''): array
    {
        $path = trim($path, '/');
        $suffix = $path === '' ? '' : '/' . $path;
        $base = rtrim(AppConfig::$siteDomain, '/');

        return [
            'ja' => $base . $suffix,
            'zh-Hant' => $base . '/tw' . $suffix,
            'th' => $base . '/th' . $suffix,
            'x-default' => $base . $suffix,
        ];
    }
}
