<?php

declare(strict_types=1);

namespace App\Controllers\Pages;

use App\Services\Admin\AdminAuthService;

/**
 * 管理クッキーの発行（/admin/cookie）。
 *
 * GET でキー入力フォームを出し、POST で照合して発行する。以前は GET のクエリ(?key=)で受けていたため、
 * 管理 API キーがブラウザ履歴・アクセスログ・Cloudflare のログに残っていた。
 */
class AdminCookieController
{
    function form()
    {
        noStore();
        return view('admin/admin_cookie_form', ['error' => '']);
    }

    function register(AdminAuthService $adminAuthService, #[\SensitiveParameter] string $key)
    {
        noStore();
        if (!$adminAuthService->registerAdminCookie($key)) {
            // 鍵が違うときは 400 ページではなくフォームに戻して知らせる（環境ごとに鍵が違うため取り違えやすい）
            http_response_code(403);
            return view('admin/admin_cookie_form', ['error' => '鍵が違います（本番と stg では鍵が別です）']);
        }

        return redirect();
    }
}
