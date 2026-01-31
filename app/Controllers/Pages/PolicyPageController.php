<?php

declare(strict_types=1);

namespace App\Controllers\Pages;

use App\Views\Content\TopPageNews;
use App\Views\Schema\PageBreadcrumbsListSchema;

class PolicyPageController
{
    function index(PageBreadcrumbsListSchema $breadcrumbsShema)
    {
        $_css = ['site_header', 'site_footer', 'room_list', 'terms'];
        $_meta = meta()->setTitle(t('オプチャグラフとは？'));
        $_meta->image_url = '';
        $desc = t('オプチャグラフはユーザーがオープンチャットを見つけて、成長傾向をグラフやランキングで比較できるWEBサイトです。');
        $_meta->setDescription($desc)->setOgpDescription($desc);
        $_breadcrumbsShema = $breadcrumbsShema->generateSchema(t('オプチャグラフとは？'));

        $_news = array_reverse(TopPageNews::getTopPageNews());

        return etag(view('policy_content', compact('_meta', '_css', '_breadcrumbsShema', '_news')));
    }

    function privacy(PageBreadcrumbsListSchema $breadcrumbsShema)
    {
        $_css = ['site_header', 'site_footer', 'room_list', 'terms'];
        $_meta = meta()->setTitle(t('プライバシーポリシー'));
        $_meta->image_url = '';
        $desc = t('オプチャグラフはユーザーがオープンチャットを見つけて、成長傾向をグラフやランキングで比較できるWEBサイトです。');
        $_meta->setDescription($desc)->setOgpDescription($desc);
        $_breadcrumbsShema = $breadcrumbsShema->generateSchema(t('オプチャグラフとは？'), 'policy', t('プライバシーポリシー'));

        return etag(view('privacy_content', compact('_meta', '_css', '_breadcrumbsShema')));
    }

    function term()
    {
        return etag(view('term_content'));
    }

    function ads()
    {
        $_css = ['site_header', 'site_footer', 'room_list', 'terms', 'ads_element'];
        $_meta = meta()->setTitle('広告について');
        $_meta->image_url = '';
        $desc = 'この広告は行動ターゲティング広告ではないため、クッキーの取得を行いません。サイト内のコンテンツに関連するアフィリエイトプログラム広告を自動的に表示しています。';
        $_meta->setDescription($desc)->setOgpDescription($desc);

        return etag(view('ads_policy_content', compact('_meta', '_css')));
    }
}
