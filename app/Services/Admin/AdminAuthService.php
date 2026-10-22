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
        if (
            !is_string($key)
            || SecretsConfig::$adminApiKey === ''
            || !hash_equals(SecretsConfig::$adminApiKey, $key)
        ) {
            return false;
        }

        $expires = time() + 3600 * 24 * 365;

        // 管理クッキーは SameSite=Strict。管理操作の多くが GET で動く（AdminPageController の動的ルート・
        // admin-api 等）ため、外部サイトのリンク遷移にクッキーが乗ると踏むだけで実行される。Strict なら
        // サイト外からの遷移には一切付かない（外部リンクで管理ページを開いたときは一度リロードが要る）。
        cookie(
            ['admin' => $this->cryptor->hashHkdf(SecretsConfig::$adminApiKey)],
            $expires,
            samesite: 'strict'
        );

        cookie(
            ['admin-enable' => '1'],
            $expires,
            httpOnly: false
        );

        return true;
    }
}
