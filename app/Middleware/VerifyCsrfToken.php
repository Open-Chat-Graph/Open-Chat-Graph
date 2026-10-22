<?php

declare(strict_types=1);

namespace App\Middleware;

use Shadow\Kernel\Reception;
use Shadow\Kernel\Cookie;
use Shared\Exceptions\BadRequestException;

class VerifyCsrfToken
{
    public function __construct()
    {
        sessionStart();
    }

    public function handle(Reception $reception)
    {
        if ($reception->isMethod('GET')) {
            // POST 側は CSRF-Token クッキーを JS が読んで X-CSRF-Token ヘッダで送り返す（クッキー単体では通らない）。
            // 以前は HttpOnly で配っていて JS から読めないので、既存のトークンでも HttpOnly なしで送り直す。
            Cookie::push(['CSRF-Token' => Cookie::csrfToken()], httpOnly: false);
            return;
        }

        $this->verifyCsrfToken();
    }

    static function verifyCsrfToken()
    {
        try {
            verifyCsrfToken();
        } catch (BadRequestException $e) {
            if (Reception::$isJson) {
                throw $e;
            }

            http_response_code(400);
            view('errors/invalid_cookie')->render();
            exit;
        } catch (\Exception $e) {
            Cookie::remove('CSRF-Token');
            throw $e;
        }
    }
}
