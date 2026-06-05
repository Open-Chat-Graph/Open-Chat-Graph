<?php

declare(strict_types=1);

namespace App\Services\Blog;

use App\Config\AppConfig;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\TaskList\TaskListExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Markdown 記事ブログ。記事は content/blog/{slug}.md（frontmatter + 本文）。
 * frontmatter は簡易 key: value（外部 YAML 依存を避ける）、本文は commonmark でレンダリング。
 */
class BlogService
{
    private const DIR = AppConfig::ROOT_PATH . 'content/blog/';

    /**
     * 記事一覧（frontmatter のみ・日付降順・draft 除外）。本文はレンダリングしない。
     * @return array<int, array{slug:string,title:string,description:string,date:string,category:string}>
     */
    public function list(): array
    {
        $out = [];
        foreach (glob(self::DIR . '*.md') ?: [] as $path) {
            [$fm] = $this->parse((string)file_get_contents($path));
            if (($fm['draft'] ?? '') === 'true') continue;
            $out[] = [
                'slug' => basename($path, '.md'),
                'title' => $fm['title'] ?? basename($path, '.md'),
                'description' => $fm['description'] ?? '',
                'date' => $fm['date'] ?? '',
                'category' => $fm['category'] ?? '',
            ];
        }
        usort($out, static fn($a, $b) => strcmp($b['date'], $a['date']));
        return $out;
    }

    /**
     * 1 記事（frontmatter + 本文 HTML）。無ければ null。
     * @return array{slug:string,title:string,description:string,date:string,updated:string,category:string,html:string}|null
     */
    public function get(string $slug): ?array
    {
        if (!preg_match('/\A[a-z0-9\-]+\z/', $slug)) return null; // ディレクトリトラバーサル防止
        $path = self::DIR . $slug . '.md';
        if (!is_file($path)) return null;

        [$fm, $body] = $this->parse((string)file_get_contents($path));
        if (($fm['draft'] ?? '') === 'true') return null;

        // FAQ セクション（## よくある質問 以降）を本文から分離し、専用スタイルで表示できるようにする。
        $mainBody = $body;
        $faqBody = '';
        if (preg_match('/^##[ \t]*(?:よくある質問|FAQ|Q&A).*$/mu', $body, $m, PREG_OFFSET_CAPTURE)) {
            $mainBody = rtrim(substr($body, 0, $m[0][1]));
            $faqBody = substr($body, $m[0][1]);
        }

        $html = $this->render($mainBody);
        $faqHtml = $faqBody !== '' ? $this->render($faqBody) : '';
        $plain = (string)preg_replace('/\s+/u', '', strip_tags($html . $faqHtml));

        return [
            'slug' => $slug,
            'title' => $fm['title'] ?? $slug,
            'description' => $fm['description'] ?? '',
            'date' => $fm['date'] ?? '',
            'updated' => $fm['updated'] ?? ($fm['date'] ?? ''),
            'category' => $fm['category'] ?? '',
            'wordCount' => mb_strlen($plain),
            'readingMinutes' => max(1, (int)ceil(mb_strlen($plain) / 500)),
            'faq' => $this->extractFaq($body),
            'html' => $html,
            'faqHtml' => $faqHtml,
        ];
    }

    /**
     * 関連記事（同カテゴリ優先・日付降順・自身を除く）。
     * @return array<int, array{slug:string,title:string,description:string,date:string,category:string}>
     */
    public function related(string $slug, string $category, int $limit = 4): array
    {
        $all = array_values(array_filter($this->list(), static fn($a) => $a['slug'] !== $slug));
        usort($all, static function ($a, $b) use ($category) {
            $sa = ($a['category'] === $category) ? 0 : 1;
            $sb = ($b['category'] === $category) ? 0 : 1;
            return ($sa <=> $sb) ?: strcmp($b['date'], $a['date']);
        });
        return array_slice($all, 0, $limit);
    }

    /**
     * 本文中の「## よくある質問 / FAQ」セクションから Q&A を抽出（FAQPage 構造化データ用）。
     * ### を質問、続くテキストを回答（プレーン化）とする。無ければ空配列。
     * @return array<int, array{q:string,a:string}>
     */
    private function extractFaq(string $rawBody): array
    {
        if (!preg_match('/^##[ \t]*(?:よくある質問|FAQ|Q&A).*$/mu', $rawBody, $m, PREG_OFFSET_CAPTURE)) {
            return [];
        }
        $rest = substr($rawBody, $m[0][1] + strlen($m[0][0]));
        if (preg_match('/^##[ \t]+\S/mu', $rest, $mm, PREG_OFFSET_CAPTURE)) {
            $rest = substr($rest, 0, $mm[0][1]); // 次の H2 までを FAQ セクションとする
        }

        $faq = [];
        if (preg_match_all('/^###[ \t]+(.+?)[ \t]*$([\s\S]*?)(?=^###[ \t]+|\z)/mu', $rest, $sets, PREG_SET_ORDER)) {
            foreach ($sets as $s) {
                $q = trim($s[1]);
                $a = trim((string)preg_replace('/\s+/u', ' ', strip_tags($this->render($s[2]))));
                if ($q !== '' && $a !== '') $faq[] = ['q' => $q, 'a' => $a];
            }
        }
        return $faq;
    }

    /**
     * 先頭の「---」で囲まれた frontmatter（簡易 key: value）と本文を分離。
     * @return array{0: array<string,string>, 1: string}
     */
    private function parse(string $raw): array
    {
        $raw = ltrim($raw);
        if (!str_starts_with($raw, '---')) return [[], $raw];

        $parts = preg_split('/^---\s*$/m', $raw, 3);
        if (!is_array($parts) || count($parts) < 3) return [[], $raw];

        $fm = [];
        foreach (preg_split('/\r\n|\n|\r/', trim($parts[1])) as $line) {
            if (!str_contains($line, ':')) continue;
            [$k, $v] = explode(':', $line, 2);
            $fm[trim($k)] = trim(trim($v), " \"'");
        }
        return [$fm, ltrim($parts[2])];
    }

    /** 本文はサイト運営者が執筆する信頼ソースなので raw HTML を許可（CTA ブロック等を埋め込める）。 */
    private function render(string $markdown): string
    {
        $environment = new Environment(['html_input' => 'allow', 'allow_unsafe_links' => false]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new TaskListExtension()); // - [ ] チェックリストをチェックボックス描画に
        return (string)(new MarkdownConverter($environment))->convert($markdown);
    }
}
