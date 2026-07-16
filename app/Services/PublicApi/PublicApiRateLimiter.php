<?php

declare(strict_types=1);

namespace App\Services\PublicApi;

final class PublicApiRateLimiter
{
    public function __construct(private ?string $directory = null)
    {
        $this->directory ??= sys_get_temp_dir() . '/ocg-public-api-rate';
    }

    /** Returns null when allowed, otherwise the Retry-After seconds. */
    public function hit(string $key, int $limit, int $window = 60, ?int $now = null): ?int
    {
        $now ??= time();
        if (!is_dir($this->directory) && !mkdir($this->directory, 0770, true) && !is_dir($this->directory)) {
            return null; // fail open: public data must remain available if temp storage is unavailable
        }
        $path = $this->directory . '/' . hash('sha256', $key) . '.json';
        $handle = fopen($path, 'c+');
        if ($handle === false) {
            return null;
        }
        try {
            flock($handle, LOCK_EX);
            $raw = stream_get_contents($handle);
            $state = $raw ? json_decode($raw, true) : null;
            if (!is_array($state) || (int)($state['reset'] ?? 0) <= $now) {
                $state = ['count' => 0, 'reset' => $now + $window];
            }
            if ((int)$state['count'] >= $limit) {
                return max(1, (int)$state['reset'] - $now);
            }
            $state['count']++;
            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, json_encode($state, JSON_THROW_ON_ERROR));
            fflush($handle);
            return null;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
