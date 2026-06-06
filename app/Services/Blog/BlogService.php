<?php

declare(strict_types=1);

namespace App\Services\Blog;

use App\Config\AppConfig;
use App\Services\Blog\Dto\BlogArticleDto;
use App\Services\Blog\Dto\BlogSummaryDto;
use App\Services\Blog\Dto\FaqItemDto;
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
    /** FAQ セクションの見出し（H2）。get() と extractFaq() で境界判定を共有する。 */
    private const FAQ_HEADING = '/^##[ \t]+(?:よくある質問|FAQ|Q&A).*$/mu';

    /** 任意の H2 見出し（"## " 直後にスペース必須なので ### 以降は除外）。FAQ セクションの終端検出に使う。 */
    private const NEXT_H2 = '/^##[ \t]+\S/mu';

    /** @var BlogSummaryDto[]|null 同一リクエスト内での list() 結果キャッシュ（毎回の glob + 全ファイル read を防ぐ）。 */
    private ?array $listCache = null;

    /**
     * 記事一覧（frontmatter のみ・日付降順・draft 除外）。本文はレンダリングしない。
     * @return BlogSummaryDto[]
     */
    public function list(): array
    {
        if ($this->listCache !== null) return $this->listCache;

        $out = [];
        foreach (glob(AppConfig::BLOG_CONTENT_DIR . '*.md') ?: [] as $path) {
            [$fm] = $this->parse((string)file_get_contents($path));
            if (($fm['draft'] ?? '') === 'true') continue;
            $slug = basename($path, '.md');
            $out[] = new BlogSummaryDto(
                slug: $slug,
                title: $fm['title'] ?? $slug,
                description: $fm['description'] ?? '',
                date: $fm['date'] ?? '',
                updated: $fm['updated'] ?? ($fm['date'] ?? ''),
                category: $fm['category'] ?? '',
            );
        }
        usort($out, static fn(BlogSummaryDto $a, BlogSummaryDto $b) => strcmp($b->date, $a->date));
        return $this->listCache = $out;
    }

    /**
     * 1 記事（frontmatter + 本文 HTML）。無ければ null。
     */
    public function get(string $slug): ?BlogArticleDto
    {
        if (!preg_match('/\A[a-z0-9\-]+\z/', $slug)) return null; // ディレクトリトラバーサル防止
        $path = AppConfig::BLOG_CONTENT_DIR . $slug . '.md';
        if (!is_file($path)) return null;

        [$fm, $body] = $this->parse((string)file_get_contents($path));
        if (($fm['draft'] ?? '') === 'true') return null;

        // FAQ セクション（## よくある質問 〜 次の H2 直前）を本文から分離し、専用スタイルで表示する。
        [$mainBody, $faqBody] = $this->splitFaq($body);

        $html = $this->render($mainBody);
        $faqHtml = $faqBody !== '' ? $this->render($faqBody) : '';
        $plain = (string)(preg_replace('/\s+/u', '', strip_tags($html . $faqHtml)) ?? '');

        return new BlogArticleDto(
            slug: $slug,
            title: $fm['title'] ?? $slug,
            description: $fm['description'] ?? '',
            date: $fm['date'] ?? '',
            updated: $fm['updated'] ?? ($fm['date'] ?? ''),
            category: $fm['category'] ?? '',
            wordCount: mb_strlen($plain),
            readingMinutes: max(1, (int)ceil(mb_strlen($plain) / 500)),
            faq: $faqBody !== '' ? $this->extractFaq($faqBody) : [],
            html: $html,
            faqHtml: $faqHtml,
        );
    }

    /**
     * 関連記事（同カテゴリ優先・日付降順・自身を除く）。
     * @return BlogSummaryDto[]
     */
    public function related(string $slug, string $category, int $limit = 4): array
    {
        $all = array_values(array_filter($this->list(), static fn(BlogSummaryDto $a) => $a->slug !== $slug));
        usort($all, static function (BlogSummaryDto $a, BlogSummaryDto $b) use ($category) {
            $sa = ($a->category === $category) ? 0 : 1;
            $sb = ($b->category === $category) ? 0 : 1;
            return ($sa <=> $sb) ?: strcmp($b->date, $a->date);
        });
        return array_slice($all, 0, $limit);
    }

    /**
     * 本文を [本文(FAQ除く), FAQセクション(見出し含む)] に分割。FAQ が無ければ [body, '']。
     * FAQ セクションは「## よくある質問」から「次の H2」直前まで。FAQ の後ろに別セクションが
     * 続く場合はそれを本文側へ戻す（extractFaq() と同じ境界判定にして表示と構造化データを一致させる）。
     * @return array{0:string,1:string}
     */
    private function splitFaq(string $body): array
    {
        if (!preg_match(self::FAQ_HEADING, $body, $m, PREG_OFFSET_CAPTURE)) {
            return [$body, ''];
        }
        $start = $m[0][1];
        $headingLen = strlen($m[0][0]);
        $end = strlen($body);

        // FAQ 見出しの後ろにさらに H2 があれば、そこを FAQ セクションの終端にする。
        $rest = substr($body, $start + $headingLen);
        if (preg_match(self::NEXT_H2, $rest, $mm, PREG_OFFSET_CAPTURE)) {
            $end = $start + $headingLen + $mm[0][1];
        }

        $faqBody = substr($body, $start, $end - $start);
        $mainBody = rtrim(substr($body, 0, $start));
        $tail = trim(substr($body, $end));
        if ($tail !== '') $mainBody .= "\n\n" . $tail;

        return [$mainBody, $faqBody];
    }

    /**
     * FAQ セクション本文から Q&A を抽出（FAQPage 構造化データ用）。
     * ### を質問、続くテキストを回答（プレーン化）とする。無ければ空配列。
     * @return FaqItemDto[]
     */
    private function extractFaq(string $faqBody): array
    {
        $faq = [];
        if (preg_match_all('/^###[ \t]+(.+?)[ \t]*$([\s\S]*?)(?=^###[ \t]+|\z)/mu', $faqBody, $sets, PREG_SET_ORDER)) {
            foreach ($sets as $s) {
                $q = trim($s[1]);
                $a = trim((string)(preg_replace('/\s+/u', ' ', strip_tags($this->render($s[2]))) ?? ''));
                if ($q !== '' && $a !== '') $faq[] = new FaqItemDto(q: $q, a: $a);
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
        // 「結論」ボックス（Smart Brevity の要点先出し）: :::point ... ::: で囲んだ箇条書きを
        // ラベル付きカードにする。中身は Markdown として描画してから div で包む（html_input=allow で保持）。
        $markdown = (string)(preg_replace_callback(
            '/^:::point[ \t]*\n(.*?)\n:::[ \t]*$/msu',
            fn($m) => "\n\n<div class=\"blog-point\"><p class=\"blog-point__label\">結論</p>"
                . $this->render(trim($m[1])) . "</div>\n\n",
            $markdown
        ) ?? $markdown);

        // CJK 特有の CommonMark バグ回避: 太字 ** が全角約物（（。、「等）に隣接すると emphasis を
        // 閉じ/開きできず「**」が文字のまま残る。太字は先に <strong> へ変換しておく（html_input=allow で保持）。
        // [^*\n] は '*' を跨がないので、長さ上限を付けずとも隣接ペアを正しく閉じる
        // （上限を付けると、長い太字の閉じ ** と次の太字の開き ** が誤結合して本文が壊れる）。
        $markdown = (string)(preg_replace('/\*\*([^*\n]+?)\*\*/u', '<strong>$1</strong>', $markdown) ?? $markdown);

        $environment = new Environment(['html_input' => 'allow', 'allow_unsafe_links' => false]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new TaskListExtension()); // - [ ] チェックリストをチェックボックス描画に
        return (string)(new MarkdownConverter($environment))->convert($markdown);
    }
}
