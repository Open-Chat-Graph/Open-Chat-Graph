<?php

declare(strict_types=1);

namespace App\Services\Auth;

interface AuthInterface
{
    /**
     * ユーザーIDを取得する
     *
     * @throws \Shared\Exceptions\ValidationException 復号に失敗した場合
     */
    function loginCookieUserId(): string;

    /**
     * クッキーのユーザーIDを検証する
     *
     * @throws \Shared\Exceptions\ValidationException 復号に失敗した場合
     * @throws \Shared\Exceptions\UnauthorizedException クッキーが空の場合
     */
    function verifyCookieUserId(): string;
}
