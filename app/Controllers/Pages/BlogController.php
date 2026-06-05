<?php

declare(strict_types=1);

namespace App\Controllers\Pages;

use App\Config\AppConfig;
use App\Services\Blog\BlogService;
use App\Views\Schema\PageBreadcrumbsListSchema;
use Spatie\SchemaOrg\Organization;
use Spatie\SchemaOrg\Schema;

class BlogController
{
    private const CSS = ['site_header', 'site_footer', 'room_list', 'terms'];

    public function index(BlogService $blog, PageBreadcrumbsListSchema $breadcrumbsShema)
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
                static fn(array $a) => Schema::blogPosting()
                    ->headline($a['title'])
                    ->url(url('blog/' . $a['slug']))
                    ->datePublished(new \DateTime($a['date'] ?: 'now')),
                $articles
            ))
            ->toScript();

        return view('blog_index_content', compact('_meta', '_css', '_breadcrumbsShema', '_schema', 'articles'));
    }

    public function article(BlogService $blog, PageBreadcrumbsListSchema $breadcrumbsShema, string $slug)
    {
        $article = $blog->get($slug);
        if (!$article) return false; // 404

        $_css = self::CSS;
        $_meta = meta()->setTitle($article['title']);
        $_meta->setDescription($article['description'])->setOgpDescription($article['description']);

        $_breadcrumbsShema = $breadcrumbsShema->generateSchema('ブログ', 'blog', $article['title']);

        // リッチな BlogPosting 構造化データ。
        $_schema = Schema::blogPosting()
            ->headline($article['title'])
            ->description($article['description'])
            ->image($_meta->image_url)
            ->datePublished(new \DateTime($article['date'] ?: 'now'))
            ->dateModified(new \DateTime(($article['updated'] ?: $article['date']) ?: 'now'))
            ->inLanguage('ja')
            ->articleSection($article['category'])
            ->wordCount((int)($article['wordCount'] ?? 0))
            ->author($this->publisher())
            ->publisher($this->publisher())
            ->mainEntityOfPage(url('blog/' . $slug))
            ->toScript();

        // 本文に「よくある質問」があれば FAQPage（リッチリザルト）。
        if (!empty($article['faq'])) {
            $_schema .= "\n" . Schema::fAQPage()->mainEntity(array_map(
                static fn(array $f) => Schema::question()
                    ->name($f['q'])
                    ->acceptedAnswer(Schema::answer()->text($f['a'])),
                $article['faq']
            ))->toScript();
        }

        $related = $blog->related($slug, $article['category']);

        // 本文 HTML は commonmark 済みの信頼ソース。View の自動エスケープ(非 _ 変数)を避けるため
        // アンダースコア接頭辞で生出力。title 等のテキストは $article 側で自動エスケープされる。
        $_html = $article['html'];
        unset($article['html'], $article['faq']);

        return view('blog_article_content', compact('_meta', '_css', '_breadcrumbsShema', '_schema', 'article', '_html', 'related'));
    }

    private function publisher(): Organization
    {
        return Schema::organization()
            ->name(t('オプチャグラフ'))
            ->url(url())
            ->logo(url(['urlRoot' => '', 'paths' => [AppConfig::SITE_ICON_FILE_PATH]]));
    }
}
