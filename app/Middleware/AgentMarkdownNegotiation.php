<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\Agent\AgentMarkdownView;
use Shadow\Kernel\ViewInterface;

/**
 * AIエージェント向けMarkdownコンテンツネゴシエーション（全ルート共通のkernel middleware）。
 *
 * Route::run() に渡して全リクエストで実行する。Markdown対象リクエストのとき、
 * ViewInterface の実装を AgentMarkdownView へ差し替える（サービスプロバイダ的な役割）。
 * 各コントローラーが返す view() はそのままで、render() 時にHTMLがMarkdownへ変換されて
 * text/markdown で出力される。
 *
 * Markdown対象になるリクエストは2種類（どちらもGETのみ・/admin配下は除外）:
 *
 * 1. `?md=1` クエリパラメータ（推奨・キャッシュ可能）
 *    HTMLとURL（=CDNのキャッシュキー）が分かれるため、通常ページと同じ
 *    Last-Modified / 304 / CDNエッジキャッシュがそのまま効く。
 * 2. `Accept: text/markdown` ヘッダー（Cloudflare診断のMarkdown Negotiation対応）
 *    HTMLと同一URLになりCloudflareはAcceptをキャッシュキーに含めないため、
 *    キャッシュ汚染防止に no-store とする（毎回オリジン処理。isCacheable… = false）。
 *
 * response()（JSON API）や echo直書きのルート（robots.txt・llms.txt等）には影響しない。
 */
class AgentMarkdownNegotiation
{
    public function handle(): void
    {
        if (!self::isAgentMarkdownRequest()) {
            self::sendMarkdownAlternateLinkHeader();
            return;
        }

        app()->bind(ViewInterface::class, AgentMarkdownView::class);
    }

    /**
     * 通常のHTMLレスポンスに、同一ページのMarkdown版（?md=1）の場所を知らせる
     * Linkヘッダー（RFC 8288 / rel="alternate"）を付ける。
     *
     * ?md=1 は独自パラメータでエージェントが自発的には知り得ないため、
     * llms.txt の案内に加えてレスポンスヘッダーでも機械的に発見可能にする。
     */
    private static function sendMarkdownAlternateLinkHeader(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET' || isset($_GET['md'])) {
            return;
        }

        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);
        if (!is_string($path) || preg_match('#^(?:/tw|/th)?/admin(?:/|$)#', $path)) {
            return;
        }

        // REQUEST_URIは生のリクエスト行由来のため、RFC 3986の許可文字以外
        // （生マルチバイト・ヘッダー構文を壊す > " 等）をパーセントエンコードする
        $uri = preg_replace_callback(
            '/[^a-zA-Z0-9\-._~:\/?#\[\]@!$&\'()*+,;=%]/',
            fn(array $m): string => rawurlencode($m[0]),
            $uri
        ) ?? $uri;

        $separator = str_contains($uri, '?') ? '&' : '?';
        header(
            'Link: <' . \App\Config\AppConfig::$siteDomain . $uri . $separator . 'md=1>; rel="alternate"; type="text/markdown"',
            false
        );
    }

    /**
     * Markdownレスポンスを返すべきリクエストか
     */
    public static function isAgentMarkdownRequest(): bool
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
            return false;
        }

        if (
            !self::hasMarkdownQueryParam()
            && !str_contains(strtolower($_SERVER['HTTP_ACCEPT'] ?? ''), 'text/markdown')
        ) {
            return false;
        }

        // 管理画面はMarkdown変換の対象外
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if (is_string($path) && preg_match('#^(?:/tw|/th)?/admin(?:/|$)#', $path)) {
            return false;
        }

        return true;
    }

    /**
     * CDNキャッシュ可能なMarkdownリクエストか（= ?md=1 でHTMLとキャッシュキーが分かれている）。
     *
     * trueのとき checkLastModified() のLast-Modified/304/CDNキャッシュ制御を通常どおり適用し、
     * AgentMarkdownView も no-store を付けない。
     */
    public static function isCacheableMarkdownRequest(): bool
    {
        return self::isAgentMarkdownRequest() && self::hasMarkdownQueryParam();
    }

    private static function hasMarkdownQueryParam(): bool
    {
        return ($_GET['md'] ?? null) === '1';
    }
}
