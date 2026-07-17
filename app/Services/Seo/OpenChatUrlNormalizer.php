<?php

declare(strict_types=1);

namespace App\Services\Seo;

/**
 * Canonicalises public room URLs and keeps graph UI state out of the HTTP query.
 *
 * A fragment is deliberately used for graph state: crawlers and the origin see one
 * stable room URL while visitors can still deep-link to a particular graph view.
 */
final class OpenChatUrlNormalizer
{
    /** @var array<string, list<string>> */
    private const GRAPH_VALUES = [
        'limit' => ['hour', 'week', 'month', 'all'],
        'bar' => ['ranking', 'rising', 'none'],
        'category' => ['in', 'all'],
        'chart' => ['line', 'candlestick'],
    ];

    /** @var list<string> */
    private const TRACKING_KEYS = [
        'gclid', 'dclid', 'gbraid', 'wbraid', 'fbclid', 'msclkid', 'ttclid',
    ];

    /**
     * Returns a redirect target for a non-canonical room request, or null when the
     * request is already canonical/not a room detail URL.
     */
    public static function normalizeRequestUri(string $requestUri, string $method = 'GET'): ?string
    {
        if (!in_array(strtoupper($method), ['GET', 'HEAD'], true)) {
            return null;
        }

        $parts = parse_url($requestUri);
        $path = $parts['path'] ?? '/';
        if (!preg_match('~^/(?:(tw|th)/)?oc/(\d+)/?$~', $path, $matches)) {
            return null;
        }

        $locale = isset($matches[1]) && $matches[1] !== '' ? $matches[1] . '/' : '';
        $id = (string) (int) $matches[2];
        $canonicalPath = '/' . $locale . 'oc/' . $id;

        $rawQuery = $parts['query'] ?? '';
        parse_str($rawQuery, $query);

        $tracking = [];
        $graph = [];
        foreach ($query as $key => $value) {
            if (!is_string($value)) {
                continue;
            }

            if (self::isTrackingKey((string) $key)) {
                $tracking[(string) $key] = $value;
                continue;
            }

            if (isset(self::GRAPH_VALUES[$key]) && in_array($value, self::GRAPH_VALUES[$key], true)) {
                $graph[$key] = $value;
            }
        }

        $target = $canonicalPath;
        if ($tracking !== []) {
            $target .= '?' . http_build_query($tracking, '', '&', PHP_QUERY_RFC3986);
        }
        if ($graph !== []) {
            $target .= self::graphFragment($graph);
        }

        $currentComparable = $path . ($rawQuery !== '' ? '?' . $rawQuery : '');
        return $target === $currentComparable ? null : $target;
    }

    /** @param array<string, scalar|null> $state */
    public static function graphFragment(array $state): string
    {
        $valid = [];
        foreach (self::GRAPH_VALUES as $key => $allowed) {
            $value = $state[$key] ?? null;
            if (is_string($value) && in_array($value, $allowed, true)) {
                $valid[$key] = $value;
            }
        }

        return $valid === []
            ? '#graph'
            : '#graph?' . http_build_query($valid, '', '&', PHP_QUERY_RFC3986);
    }

    /** @param array<string, scalar|null> $graphState */
    public static function roomUrl(int $id, array $graphState = []): string
    {
        return url('oc', (string) $id) . ($graphState === [] ? '' : self::graphFragment($graphState));
    }

    private static function isTrackingKey(string $key): bool
    {
        return str_starts_with(strtolower($key), 'utm_')
            || in_array(strtolower($key), self::TRACKING_KEYS, true);
    }
}
