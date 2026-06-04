<?php

declare(strict_types=1);

namespace App\Controllers\Pages;

use App\Services\Blog\BlogService;
use App\Views\Schema\PageBreadcrumbsListSchema;
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

        return view('blog_index_content', compact('_meta', '_css', '_breadcrumbsShema', 'articles'));
    }

    public function article(BlogService $blog, PageBreadcrumbsListSchema $breadcrumbsShema, string $slug)
    {
        $article = $blog->get($slug);
        if (!$article) return false; // 404

        $_css = self::CSS;
        $_meta = meta()->setTitle($article['title']);
        $_meta->setDescription($article['description'])->setOgpDescription($article['description']);

        $_breadcrumbsShema = $breadcrumbsShema->generateSchema('ブログ', 'blog', $article['title']);

        $_schema = Schema::article()
            ->headline($article['title'])
            ->description($article['description'])
            ->datePublished(new \DateTime($article['date'] ?: 'now'))
            ->dateModified(new \DateTime(($article['updated'] ?: $article['date']) ?: 'now'))
            ->inLanguage('ja')
            ->author(Schema::organization()->name(t('オプチャグラフ'))->url(url()))
            ->publisher(
                Schema::organization()
                    ->name(t('オプチャグラフ'))
                    ->url(url())
                    ->logo(url(['urlRoot' => '', 'paths' => ['assets/icon-192x192.png']]))
            )
            ->mainEntityOfPage(url('blog/' . $slug))
            ->toScript();

        // 本文 HTML は commonmark 済みの信頼ソース。View の自動エスケープ(非 _ 変数)を避けるため
        // アンダースコア接頭辞で生出力する。title 等のテキストは $article 側で自動エスケープされる。
        $_html = $article['html'];
        unset($article['html']);

        return view('blog_article_content', compact('_meta', '_css', '_breadcrumbsShema', '_schema', 'article', '_html'));
    }
}
