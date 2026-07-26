<?php

declare(strict_types=1);

namespace App\Controllers\Pages;

use App\Services\Ads\AdOptOutService;
use Shadow\Kernel\Reception;

/**
 * 広告オプトアウト設定ページ（/admin/disable-ads）
 *
 * 合言葉を入力すると、そのブラウザにだけ「広告を出さない」クッキーが入る。
 * 管理者クッキー（admin）とは独立していて、管理者でなくても合言葉さえ知っていれば使える。
 *
 * 実際に広告を止めるのはクライアント側のガード（App\Views\Ads\AdOptOutGuard）。
 * サイトのページは CDN でキャッシュされていてサーバ側では出し分けられないため
 * （理由の詳細は AdOptOutService のコメント）。
 *
 * このページ自体は noStore() でキャッシュせず、合言葉はサーバから外に出さない。
 */
class AdOptOutPageController
{
    /** 同一セッションで許す合言葉の失敗回数（超えたらそのセッションは受け付けない） */
    private const MAX_ATTEMPTS = 10;

    public function index()
    {
        $optedOut = AdOptOutService::isOptedOut();
        $message = null;
        $error = null;

        if (Reception::$requestMethod === 'POST') {
            if ((string) Reception::input('action') === 'clear') {
                AdOptOutService::clearCookie();
                $optedOut = false;
                $message = '広告を再表示するようにしました。';
            } elseif ($this->isLockedOut()) {
                $error = '試行回数が多すぎます。時間をおいて再度お試しください。';
            } elseif (AdOptOutService::verifyPassphrase((string) Reception::input('passphrase'))) {
                $this->resetAttempts();
                AdOptOutService::issueCookie();
                $optedOut = true;
                $message = 'このブラウザで広告を非表示にしました。';
            } else {
                $this->countAttempt();
                // 総当たりを鈍らせる（このページは滅多に使われないので体感上の不利益はない）
                usleep(500000);
                $error = '合言葉が違います。';
            }
        }

        return view('ad_opt_out', compact('optedOut', 'message', 'error'));
    }

    private function isLockedOut(): bool
    {
        return (int) ($_SESSION['_ad_opt_out_attempts'] ?? 0) >= self::MAX_ATTEMPTS;
    }

    private function countAttempt(): void
    {
        $_SESSION['_ad_opt_out_attempts'] = (int) ($_SESSION['_ad_opt_out_attempts'] ?? 0) + 1;
    }

    private function resetAttempts(): void
    {
        unset($_SESSION['_ad_opt_out_attempts']);
    }
}
