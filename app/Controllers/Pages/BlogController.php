<?php

declare(strict_types=1);

namespace App\Controllers\Pages;

use App\Config\AppConfig;
use App\Services\Blog\BlogService;
use App\Services\Blog\Dto\BlogSummaryDto;
use App\Views\Schema\PageBreadcrumbsListSchema;
use Shadow\Kernel\ViewInterface;
use Spatie\SchemaOrg\Organization;
use Spatie\SchemaOrg\Schema;

class BlogController
{
    private const CSS = ['components/site_header', 'components/site_footer', 'components/room_list', 'pages/blog'];

    public function index(BlogService $blog, PageBreadcrumbsListSchema $breadcrumbsShema): ViewInterface
    {
        $_css = self::CSS;
        $_meta = meta()->setTitle('ブログ｜オープンチャットの運営・検索・トレンド情報');
        $desc = 'LINEオープンチャットの運営のコツ、検索・ランキングの仕組み、トレンドを、オプチャグラフ独自のデータをもとに解説します。';
        $_meta->setDescription($desc)->setOgpDescription($desc);
        $_breadcrumbsShema = $breadcrumbsShema->generateSchema('ブログ');

        $articles = $blog->list();

        // Blog 構造化データ（記事一覧＝itemList 相当の blogPost）。
        $_schema = Schema::blog()
            ->name('オプチャグラフ ブログ')
            ->description($desc)
            ->url(url('blog'))
            ->inLanguage('ja')
            ->publisher($this->publisher())
            ->blogPost(array_map(
                fn(BlogSummaryDto $a) => Schema::blogPosting()
                    ->headline($a->title)
                    ->url(url('blog/' . $a->slug))
                    ->datePublished($this->toDate($a->date))
                    ->dateModified($this->modifiedDate($a->date, $a->updated)),
                $articles
            ))
            ->toScript();

        return view('blog_index_content', compact('_meta', '_css', '_breadcrumbsShema', '_schema', 'articles'));
    }

    public function article(BlogService $blog, PageBreadcrumbsListSchema $breadcrumbsShema, string $slug): ViewInterface|false
    {
        $article = $blog->get($slug);
        if (!$article) return false; // 404

        $_css = self::CSS;
        $_meta = meta()->setTitle($article->title);
        $_meta->setDescription($article->description)->setOgpDescription($article->description);

        $_breadcrumbsShema = $breadcrumbsShema->generateSchema('ブログ', 'blog', $article->title);

        // リッチな BlogPosting 構造化データ。
        $_schema = Schema::blogPosting()
            ->headline($article->title)
            ->description($article->description)
            ->image($_meta->image_url)
            ->datePublished($this->toDate($article->date))
            ->dateModified($this->modifiedDate($article->date, $article->updated))
            ->inLanguage('ja')
            ->articleSection($article->category)
            ->wordCount($article->wordCount)
            ->author($this->publisher())
            ->publisher($this->publisher())
            ->mainEntityOfPage(url('blog/' . $slug))
            ->toScript();

        $related = $blog->related($slug, $article->category);

        // 本文 HTML は commonmark 済みの信頼ソース。View の自動エスケープ(非 _ 変数)を避けるため
        // アンダースコア接頭辞で生出力する。$article のテキストは View 層で自動エスケープされる。
        $_html = $article->html;
        $_faqHtml = $article->faqHtml;

        return view('blog_article_content', compact('_meta', '_css', '_breadcrumbsShema', '_schema', 'article', '_html', '_faqHtml', 'related'));
    }

    /**
     * frontmatter の日付文字列を安全にパースする。不正値でもページを落とさず「現在時刻」に倒す
     * （1記事の日付 typo で /blog 一覧全体が 500 になるのを防ぐ）。
     */
    private function toDate(string $value): \DateTimeImmutable
    {
        try {
            return new \DateTimeImmutable($value !== '' ? $value : 'now');
        } catch (\Throwable) {
            return new \DateTimeImmutable('now');
        }
    }

    /**
     * dateModified 用：更新日をパースし、公開日より過去にならないようクランプする
     * （frontmatter の入力ミスで dateModified < datePublished の不正な構造化データを出さない）。
     */
    private function modifiedDate(string $date, string $updated): \DateTimeImmutable
    {
        return max($this->toDate($date), $this->toDate($updated));
    }

    private function publisher(): Organization
    {
        return Schema::organization()
            ->name(t('オプチャグラフ'))
            ->url(url())
            ->logo(url(['urlRoot' => '', 'paths' => [AppConfig::SITE_ICON_FILE_PATH]]));
    }
}
