<?php

declare(strict_types=1);

namespace App\Controllers\Pages;

use App\Config\AppConfig;
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

    /** 認証に成功してからトップページへ自動で移動するまでの秒数 */
    private const REDIRECT_SECONDS = 4;

    private const TITLE = 'Staff Entrance';
    private const DESC = '合言葉を知っている人だけが通れるページです。';

    public function index()
    {
        $optedOut = AdOptOutService::isOptedOut();
        // 直前に合言葉を通したときだけ true（会員証の発行演出を出す状態）
        $premium = false;
        $message = null;
        $error = null;

        if (Reception::$requestMethod === 'POST') {
            if ((string) Reception::input('action') === 'clear') {
                AdOptOutService::clearCookie();
                $optedOut = false;
                $message = '広告を再表示しました。';
            } elseif ($this->isLockedOut()) {
                $error = '試行回数が多すぎます。時間をおいてからもう一度お試しください。';
            } elseif (AdOptOutService::verifyPassphrase((string) Reception::input('passphrase'))) {
                $this->resetAttempts();
                AdOptOutService::issueCookie();
                $optedOut = true;
                $premium = true;
            } else {
                $this->countAttempt();
                // 総当たりを鈍らせる（このページは滅多に使われないので体感上の不利益はない）
                usleep(500000);
                $error = '合言葉が違います。';
            }
        }

        $_meta = meta()
            ->setTitle(self::TITLE)
            ->setDescription(self::DESC)
            ->setOgpDescription(self::DESC)
            ->setImageUrl(url(['urlRoot' => '', 'paths' => [AppConfig::OGP_PREMIUM_IMAGE_FILE_PATH]]))
            ->setTwitterCard('summary_large_image');

        $redirectSeconds = self::REDIRECT_SECONDS;

        return view('ad_opt_out', compact('optedOut', 'premium', 'message', 'error', '_meta', 'redirectSeconds'));
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
