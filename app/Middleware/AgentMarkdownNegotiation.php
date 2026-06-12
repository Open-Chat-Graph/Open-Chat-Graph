<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\Agent\AgentMarkdownView;
use Shadow\Kernel\ViewInterface;

/**
 * AIエージェント向けMarkdownコンテンツネゴシエーション（全ルート共通のkernel middleware）。
 *
 * Route::run() に渡して全リクエストで実行する。`Accept: text/markdown` を含む
 * GETリクエストのとき、ViewInterface の実装を AgentMarkdownView へ差し替える
 * （サービスプロバイダ的な役割）。各コントローラーが返す view() はそのままで、
 * render() 時にHTMLがMarkdownへ変換されて text/markdown で出力される。
 *
 * response()（JSON API）や echo直書きのルート（robots.txt・llms.txt等）には影響しない。
 */
class AgentMarkdownNegotiation
{
    public function handle(): void
    {
        if (!self::isAgentMarkdownRequest()) {
            return;
        }

        app()->bind(ViewInterface::class, AgentMarkdownView::class);
    }

    /**
     * Markdownレスポンスを返すべきリクエストか
     */
    public static function isAgentMarkdownRequest(): bool
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
            return false;
        }

        if (!str_contains(strtolower($_SERVER['HTTP_ACCEPT'] ?? ''), 'text/markdown')) {
            return false;
        }

        // 管理画面はMarkdown変換の対象外
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if (is_string($path) && preg_match('#^(?:/tw|/th)?/admin(?:/|$)#', $path)) {
            return false;
        }

        return true;
    }
}
