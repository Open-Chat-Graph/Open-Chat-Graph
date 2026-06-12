<?php

declare(strict_types=1);

namespace App\Services\Agent;

use App\Config\AppConfig;

/**
 * AIエージェント向けMarkdownコンテンツネゴシエーション。
 *
 * `Accept: text/markdown` を含むGETリクエストに対して、レンダリング済みHTMLページを
 * Markdownへ変換して `Content-Type: text/markdown` で返す（通常のブラウザにはHTMLのまま）。
 * Cloudflareの「Is Your Site Agent-Ready?」診断のMarkdown Negotiation項目に対応する。
 *
 * shared/bootstrap.php から register() を呼ぶ。対象リクエストのときだけ出力バッファを
 * 仕込むため、通常トラフィックへのオーバーヘッドはゼロ。
 *
 * 注意: Cloudflareのエッジキャッシュは Accept をキャッシュキーに含めないため、
 * Markdownレスポンスは no-store とし、エッジにHTMLと同一キーでキャッシュさせない。
 * エッジでHTMLのキャッシュHITになるとオリジンに届かず変換できないので、
 * Cloudflare側で「Accept に text/markdown を含むリクエストはキャッシュをバイパスする」
 * Cache Rule を併用すること。
 */
final class AgentMarkdownResponder
{
    public static function register(): void
    {
        if (!self::shouldNegotiate()) {
            return;
        }

        ob_start([self::class, 'transform']);
    }

    private static function shouldNegotiate(): bool
    {
        if (PHP_SAPI === 'cli') {
            return false;
        }

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

    /**
     * 出力バッファのコールバック。HTMLの200レスポンスのみMarkdownへ変換する
     */
    public static function transform(string $buffer): string
    {
        if ($buffer === '' || http_response_code() !== 200) {
            return $buffer;
        }

        // Content-Typeが明示されているレスポンス（text/plain・application/json等）は対象外。
        // 未指定の場合はPHPデフォルトのtext/htmlとして扱う
        foreach (headers_list() as $header) {
            if (stripos($header, 'content-type:') === 0 && stripos($header, 'text/html') === false) {
                return $buffer;
            }
        }

        if (stripos($buffer, '<html') === false) {
            return $buffer;
        }

        try {
            $markdown = (new HtmlToMarkdownConverter())->convert($buffer, AppConfig::$siteDomain);
        } catch (\Throwable) {
            return $buffer;
        }

        if (trim($markdown) === '') {
            return $buffer;
        }

        header('Content-Type: text/markdown; charset=UTF-8');
        header('Vary: Accept');
        // 概算トークン数（日本語主体のため2文字≒1トークンで概算）
        header('X-Markdown-Tokens: ' . (int)ceil(mb_strlen($markdown) / 2));
        // エッジ・ブラウザ共にキャッシュさせない（HTMLとキャッシュキーが衝突するため）
        header('Cache-Control: no-store');
        header('Cloudflare-CDN-Cache-Control: no-store');

        return $markdown;
    }
}
