<?php

declare(strict_types=1);

namespace App\Services\PublicApi;

use App\Config\SecretsConfig;

final class CursorCodec
{
    private int $ttl;

    public function __construct(
        private ?string $secret = null,
        ?int $ttl = null,
    ) {
        $this->secret ??= SecretsConfig::$adminApiKey ?: hash('sha256', __FILE__);
        $this->ttl = $ttl ?? 3600;
    }

    /** @param array<string,scalar|null> $filters */
    public function encode(int $offset, string $locale, string $snapshot, array $filters, ?int $now = null): string
    {
        $now ??= time();
        $payload = [
            'v' => 1,
            'offset' => $offset,
            'locale' => $locale,
            'snapshot' => $snapshot,
            'filters' => self::filterHash($filters),
            'exp' => $now + $this->ttl,
        ];
        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        return self::base64Url($json) . '.' . self::base64Url(hash_hmac('sha256', $json, $this->secret, true));
    }

    /**
     * @param array<string,scalar|null> $filters
     * @return array{offset:int,locale:string,snapshot:string,filters:string,exp:int,v:int}
     */
    public function decode(string $cursor, string $locale, array $filters, ?int $now = null): array
    {
        $now ??= time();
        $parts = explode('.', $cursor, 2);
        if (count($parts) !== 2) {
            throw new \InvalidArgumentException('Invalid cursor.');
        }
        $json = self::base64UrlDecode($parts[0]);
        $signature = self::base64UrlDecode($parts[1]);
        if ($json === false || $signature === false || !hash_equals(hash_hmac('sha256', $json, $this->secret, true), $signature)) {
            throw new \InvalidArgumentException('Invalid cursor signature.');
        }
        $payload = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($payload)
            || ($payload['v'] ?? null) !== 1
            || ($payload['locale'] ?? null) !== $locale
            || ($payload['filters'] ?? null) !== self::filterHash($filters)
            || (int)($payload['exp'] ?? 0) < $now
            || (int)($payload['offset'] ?? -1) < 0
            || !is_string($payload['snapshot'] ?? null)
        ) {
            throw new \InvalidArgumentException('Cursor expired or does not match this request.');
        }
        return $payload;
    }

    /** @param array<string,scalar|null> $filters */
    private static function filterHash(array $filters): string
    {
        ksort($filters);
        return hash('sha256', json_encode($filters, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    private static function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): string|false
    {
        $padding = strlen($value) % 4;
        if ($padding !== 0) {
            $value .= str_repeat('=', 4 - $padding);
        }
        return base64_decode(strtr($value, '-_', '+/'), true);
    }
}
