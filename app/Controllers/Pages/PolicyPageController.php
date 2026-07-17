<?php

declare(strict_types=1);

namespace App\Controllers\Pages;

use App\Services\OpenChatAdmin\AdminOpenChat;
use App\Views\Schema\PageBreadcrumbsListSchema;

class PolicyPageController
{
    function index(PageBreadcrumbsListSchema $breadcrumbsShema, ?bool $isAdmin = null)
    {
        $_css = ['components/site_header', 'components/site_footer', 'components/room_list', 'pages/terms'];
        $_meta = meta()->setTitle(t('オプチャグラフとは？'));
        $_meta->image_url = '';
        $desc = t('オプチャグラフはユーザーがオープンチャットを見つけて、成長傾向をグラフやランキングで比較できるWEBサイトです。');
        $_meta->setDescription($desc)->setOgpDescription($desc);
        $_breadcrumbsShema = $breadcrumbsShema->generateSchema(t('オプチャグラフとは？'));

        $_adminDto = $isAdmin ? app(AdminOpenChat::class)->getDto(0) : null;

        return view('policy_content', compact('_meta', '_css', '_breadcrumbsShema', '_adminDto'));
    }

    function privacy(PageBreadcrumbsListSchema $breadcrumbsShema)
    {
        $_css = ['components/site_header', 'components/site_footer', 'components/room_list', 'pages/terms'];
        $_meta = meta()->setTitle(t('プライバシーポリシー'));
        $_meta->image_url = '';
        $desc = t('オプチャグラフはユーザーがオープンチャットを見つけて、成長傾向をグラフやランキングで比較できるWEBサイトです。');
        $_meta->setDescription($desc)->setOgpDescription($desc);
        $_breadcrumbsShema = $breadcrumbsShema->generateSchema(t('オプチャグラフとは？'), 'policy', t('プライバシーポリシー'));

        return view('privacy_content', compact('_meta', '_css', '_breadcrumbsShema'));
    }

    function term()
    {
        return view('term_content');
    }

}
