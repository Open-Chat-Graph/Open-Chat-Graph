<?php

declare(strict_types=1);

namespace App\Services\Agent;

use App\Config\AppConfig;
use App\Middleware\AgentMarkdownNegotiation;
use Shadow\Kernel\View;

/**
 * AIエージェント向けMarkdown出力用のView実装。
 *
 * Accept: text/markdown を含むGETリクエストのとき、AgentMarkdownNegotiation ミドルウェアが
 * ViewInterface の実装をこのクラスへ差し替える。テンプレートのレンダリング
 * （renderCacheへのHTML蓄積）は親クラス（\Shadow\Kernel\View）のままで、
 * 出力時(render)にHTML全体をMarkdownへ変換して text/markdown で返す。
 *
 * 変換対象外のレスポンス（非200・HTMLページ全体でないもの・変換失敗）は
 * そのままHTMLを出力するため、エラーページ等の挙動は変わらない。
 */
class AgentMarkdownView extends View
{
    public function render(): void
    {
        $markdown = $this->convert();
        if ($markdown === null) {
            parent::render();
            return;
        }

        header('Content-Type: text/markdown; charset=UTF-8');
        header('Vary: Accept');
        // 概算トークン数（日本語主体のため2文字≒1トークンで概算）
        header('X-Markdown-Tokens: ' . (int)ceil(mb_strlen($markdown) / 2));

        // Acceptヘッダー単独のMarkdownリクエストはHTMLとCDNキャッシュキーが同一になるため、
        // キャッシュ汚染が起きないよう no-store にする。
        // ?md=1 はURLでキャッシュキーが分かれるので checkLastModified() が設定した
        // Last-Modified / CDNキャッシュ制御をそのまま使う（上書きしない）
        if (!AgentMarkdownNegotiation::isCacheableMarkdownRequest()) {
            header('Cache-Control: no-store');
            header('Cloudflare-CDN-Cache-Control: no-store');
        }

        echo $markdown;
    }

    /**
     * @return string|null 変換対象外のときは null（通常のHTML出力へフォールバック）
     */
    private function convert(): ?string
    {
        $html = $this->renderCache;

        // エラーページ(404/410等)や、HTMLページ全体でないレスポンスは変換しない
        if ($html === '' || http_response_code() !== 200 || stripos($html, '<html') === false) {
            return null;
        }

        try {
            $markdown = (new HtmlToMarkdownConverter())->convert($html, AppConfig::$siteDomain);
        } catch (\Throwable) {
            return null;
        }

        return trim($markdown) === '' ? null : $markdown;
    }
}
