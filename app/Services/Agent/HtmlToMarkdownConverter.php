<?php

declare(strict_types=1);

namespace App\Services\Agent;

/**
 * レンダリング済みHTMLページをAIエージェント向けのMarkdownへ変換する。
 *
 * `Accept: text/markdown` コンテンツネゴシエーション（AgentMarkdownResponder）用。
 * 外部ライブラリには依存せず、PHP 8.4+ の HTML5パーサー（\Dom\HTMLDocument）を使う。
 *
 * 変換方針:
 * - <head> の title / meta description を冒頭の見出し・引用として出力する
 * - スクリプト・装飾(svg等)・フォーム・サイト共通ヘッダー/フッターは除去する
 * - リンクは絶対URL化して残す（エージェントがサイト内を回遊できるようにする）
 * - 画像は出力しない（トークン節約のため）
 */
final class HtmlToMarkdownConverter
{
    /** 変換対象から除去する要素（スクリプト・装飾・フォーム・サイト共通クローム） */
    private const REMOVE_SELECTORS = [
        'script',
        'style',
        'svg',
        'noscript',
        'iframe',
        'form',
        'input',
        'button',
        'select',
        'textarea',
        'template',
        'canvas',
        '[aria-hidden="true"]',
        '#site_header',
        'footer',
        '.backdrop',
    ];

    private string $baseUrl = '';

    /**
     * @param string $html    レンダリング済みのHTMLページ全体
     * @param string $baseUrl 相対URLの絶対化に使うオリジン（例: https://openchat-review.me）
     */
    public function convert(string $html, string $baseUrl): string
    {
        $this->baseUrl = rtrim($baseUrl, '/');

        $doc = \Dom\HTMLDocument::createFromString($html, LIBXML_NOERROR, 'UTF-8');

        $head = '';
        $title = $doc->querySelector('title')?->textContent;
        if ($title !== null && trim($title) !== '') {
            $head .= '# ' . trim($this->collapse($title)) . "\n\n";
        }

        $description = $doc->querySelector('meta[name="description"]')?->getAttribute('content');
        if ($description !== null && trim($description) !== '') {
            $head .= '> ' . trim($this->collapse($description)) . "\n\n";
        }

        $canonical = $doc->querySelector('link[rel="canonical"]')?->getAttribute('href');
        if ($canonical !== null && trim($canonical) !== '') {
            $head .= trim($canonical) . "\n\n";
        }

        $body = $doc->querySelector('body');
        if ($body === null) {
            return trim($head);
        }

        foreach (self::REMOVE_SELECTORS as $selector) {
            foreach (iterator_to_array($body->querySelectorAll($selector)) as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        $markdown = $head . $this->renderChildren($body);

        // 行頭・行末の空白を除去（行頭4スペースは意図しないコードブロックになるため）し、
        // 3連以上の空行を1つの空行へ詰める
        $markdown = preg_replace('/^[ \t]+|[ \t]+$/mu', '', $markdown) ?? $markdown;
        $markdown = preg_replace("/\n{3,}/", "\n\n", $markdown) ?? $markdown;

        return trim($markdown) . "\n";
    }

    private function renderChildren(\Dom\Node $node): string
    {
        $output = '';
        foreach ($node->childNodes as $child) {
            $output .= $this->renderNode($child);
        }

        return $output;
    }

    private function renderNode(\Dom\Node $node): string
    {
        if ($node instanceof \Dom\Text) {
            return $this->collapse($node->textContent);
        }

        if (!$node instanceof \Dom\Element) {
            return '';
        }

        $tag = strtolower($node->localName);

        switch ($tag) {
            case 'h1':
            case 'h2':
            case 'h3':
            case 'h4':
            case 'h5':
            case 'h6':
                $text = trim($this->inline($this->renderChildren($node)));
                return $text === '' ? '' : "\n\n" . str_repeat('#', (int)$tag[1]) . ' ' . $text . "\n\n";

            case 'p':
            case 'div':
            case 'section':
            case 'article':
            case 'main':
            case 'aside':
            case 'header':
            case 'nav':
            case 'figure':
            case 'figcaption':
            case 'details':
            case 'summary':
            case 'address':
            case 'dl':
            case 'dt':
            case 'dd':
            case 'li': // リスト外の孤立した li
                $inner = trim($this->renderChildren($node));
                return $inner === '' ? '' : "\n\n" . $inner . "\n\n";

            case 'ul':
            case 'ol':
                return $this->renderList($node, $tag === 'ol');

            case 'table':
                return $this->renderTable($node);

            case 'a':
                $text = trim($this->inline($this->renderChildren($node)));
                if ($text === '') {
                    return '';
                }
                $url = $this->absoluteUrl($node->getAttribute('href') ?? '');
                return $url === null ? $text : '[' . $text . '](' . $url . ')';

            case 'strong':
            case 'b':
                $text = trim($this->inline($this->renderChildren($node)));
                return $text === '' ? '' : '**' . $text . '**';

            case 'em':
            case 'i':
                $text = trim($this->inline($this->renderChildren($node)));
                return $text === '' ? '' : '*' . $text . '*';

            case 'code':
                $text = trim($this->collapse($node->textContent));
                return $text === '' ? '' : '`' . $text . '`';

            case 'pre':
                $text = trim($node->textContent, "\n");
                return $text === '' ? '' : "\n\n```\n" . $text . "\n```\n\n";

            case 'blockquote':
                $inner = trim($this->renderChildren($node));
                if ($inner === '') {
                    return '';
                }
                return "\n\n" . (preg_replace('/^/m', '> ', $inner) ?? $inner) . "\n\n";

            case 'br':
                return "\n";

            case 'hr':
                return "\n\n---\n\n";

            case 'img':
            case 'picture':
            case 'video':
            case 'audio':
            case 'source':
                return '';

            default:
                // span / time / small / label 等のインライン要素は中身のみ出力する
                return $this->renderChildren($node);
        }
    }

    private function renderList(\Dom\Element $node, bool $ordered): string
    {
        $output = '';
        $index = 0;
        foreach ($node->childNodes as $child) {
            if (!$child instanceof \Dom\Element || strtolower($child->localName) !== 'li') {
                continue;
            }

            $text = trim($this->inline($this->renderChildren($child)));
            if ($text === '') {
                continue;
            }

            $index++;
            $output .= ($ordered ? "{$index}. " : '- ') . $text . "\n";
        }

        return $output === '' ? '' : "\n\n" . $output . "\n";
    }

    private function renderTable(\Dom\Element $node): string
    {
        $rows = [];
        foreach ($node->querySelectorAll('tr') as $tr) {
            $cells = [];
            foreach ($tr->childNodes as $cell) {
                if (!$cell instanceof \Dom\Element) {
                    continue;
                }
                $cellTag = strtolower($cell->localName);
                if ($cellTag !== 'td' && $cellTag !== 'th') {
                    continue;
                }
                $cells[] = str_replace('|', '\\|', trim($this->collapse($cell->textContent)));
            }
            if ($cells !== []) {
                $rows[] = $cells;
            }
        }

        if ($rows === []) {
            return '';
        }

        $output = '| ' . implode(' | ', $rows[0]) . " |\n";
        $output .= '|' . str_repeat(' --- |', count($rows[0])) . "\n";
        foreach (array_slice($rows, 1) as $cells) {
            $output .= '| ' . implode(' | ', $cells) . " |\n";
        }

        return "\n\n" . $output . "\n";
    }

    /** 連続する空白文字を1つの半角スペースへ詰める（前後の境界スペースは保持） */
    private function collapse(string $text): string
    {
        return preg_replace('/\s+/u', ' ', $text) ?? $text;
    }

    /** 改行を含む文字列を1行のインラインテキストへ詰める（リンクテキスト・見出し用） */
    private function inline(string $text): string
    {
        $text = preg_replace('/\s*\n+\s*/u', ' ', $text) ?? $text;
        return preg_replace('/ {2,}/u', ' ', $text) ?? $text;
    }

    /**
     * href を絶対URLへ変換する。リンクとして残せないもの（ページ内アンカー・相対パス等）は null
     */
    private function absoluteUrl(string $href): ?string
    {
        if ($href === '' || str_starts_with($href, '#') || str_starts_with($href, 'javascript:')) {
            return null;
        }

        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://') || str_starts_with($href, 'mailto:')) {
            return $href;
        }

        if (str_starts_with($href, '//')) {
            return 'https:' . $href;
        }

        if (str_starts_with($href, '/')) {
            return $this->baseUrl . $href;
        }

        return null;
    }
}
