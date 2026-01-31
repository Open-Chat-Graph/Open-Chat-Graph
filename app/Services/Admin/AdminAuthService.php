<?php

declare(strict_types=1);

namespace App\Services\Admin;

use Shadow\StringCryptorInterface as StringCryptor;
use Shared\Exceptions\UnauthorizedException;
use App\Config\SecretsConfig;

class AdminAuthService
{
    function __construct(
        private StringCryptor $cryptor
    ) {
    }

    /**
     * @throws UnauthorizedException
     */
    function auth(): bool
    {
        if (!cookie()->has('admin')) {
            if (cookie()->has('admin-enable')) {
                cookie()->remove('admin-enable');
            }

            return false;
        }

        try {
            $result = $this->cryptor->hkdfEquals(SecretsConfig::$adminApiKey, cookie('admin'));
            if (!$result) {
                cookie()->remove('admin');
                cookie()->remove('admin-enable');
                throw new UnauthorizedException('無効なAdminキー');
                return false;
            }
        } catch (\RuntimeException $e) {
            throw new UnauthorizedException($e->getMessage());
            return false;
        }

        return true;
    }

    function registerAdminCookie(mixed $key): bool
    {
        if ($key !== SecretsConfig::$adminApiKey) {
            return false;
        }

        $expires = time() + 3600 * 24 * 365;

        cookie(
            ['admin' => $this->cryptor->hashHkdf(SecretsConfig::$adminApiKey)],
            $expires
        );

        cookie(
            ['admin-enable' => '1'],
            $expires,
            httpOnly: false
        );

        return true;
    }
}
