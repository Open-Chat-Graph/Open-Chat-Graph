<?php

declare(strict_types=1);

namespace App\Controllers\Pages;

use Shadow\Kernel\Response;

/**
 * 旧ブログ（/blog・/blog/{slug}）の URL を受けて 301 で既存ページへ送る。
 *
 * ブログは 2026-10 に撤去した（4か月で検索からの着地が合計 100 回台にとどまったため）。
 * 検索結果・外部リンク・AI 検索に残った旧 URL を 404 にせず、記事の主題に近い機能ページへ送る。
 * 記事本体・Markdown・BlogService は削除済みで、この転送表だけが残る。
 */
class BlogRedirectController
{
    /** 旧 slug → 移転先パス。未掲載の slug と一覧（/blog）はトップへ */
    private const MOVED = [
        'openchat-kensaku-ranking-ochi' => 'labs/publication-analytics', // 検索落ち・掲載圏外の実データ
        'openchat-tsuho-tobei' => 'labs/publication-analytics',
        'openchat-ninzu-jogen' => 'ranking',                              // 人数順に並べられる
        'growing-openchat-features' => 'ranking',
        'openchat-kyujosho-ranking' => 'ranking',
        'openchat-ranking-shikumi' => 'ranking',
        'openchat-member-fuyasu' => 'ranking',
        'openchat-sagashikata' => 'ranking',
    ];

    public function index(): Response
    {
        return redirect('', 301);
    }

    public function article(string $slug): Response
    {
        return redirect(self::MOVED[$slug] ?? '', 301);
    }
}
