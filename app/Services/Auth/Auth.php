<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Config\SecretsConfig;
use App\Services\Admin\AdminAuthService;

class Auth implements AuthInterface
{
    function __construct(
        private CookieUserLogin $cookieUserLogin,
        private AdminAuthService $adminAuthService
    ) {
    }

    /**
     * ユーザーIDを取得する
     *
     * @throws \Shared\Exceptions\ValidationException 復号に失敗した場合
     */
    function loginCookieUserId(): string
    {
        return $this->adminAuthService->auth() ? SecretsConfig::$adminApiKey : $this->cookieUserLogin->login();
    }

    /**
     * クッキーのユーザーIDを検証する
     *
     * @throws \Shared\Exceptions\ValidationException 復号に失敗した場合
     * @throws \Shared\Exceptions\UnauthorizedException クッキーが空の場合
     */
    function verifyCookieUserId(): string
    {
        return $this->adminAuthService->auth() ? SecretsConfig::$adminApiKey : $this->cookieUserLogin->verifyLogin();
    }
}
