<?php

declare(strict_types=1);

namespace App\Services\Admin;

use Shadow\StringCryptorInterface as StringCryptor;
use Shared\Exceptions\UnauthorizedException;
use App\Config\AdminConfig;

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
            $result = $this->cryptor->hkdfEquals(AdminConfig::ADMIN_API_KEY, cookie('admin'));
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
        if ($key !== AdminConfig::ADMIN_API_KEY) {
            return false;
        }

        $expires = time() + 3600 * 24 * 365;

        cookie(
            ['admin' => $this->cryptor->hashHkdf(AdminConfig::ADMIN_API_KEY)],
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
