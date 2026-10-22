<?php

declare(strict_types=1);

namespace App\Controllers\Pages;

use App\Services\Admin\AdminAuthService;
use App\Services\OpenChatAdmin\AdminOpenChat;
use App\Views\Schema\PageBreadcrumbsListSchema;

class PolicyPageController
{
    function index(PageBreadcrumbsListSchema $breadcrumbsShema, AdminAuthService $adminAuthService, ?string $isAdmin = null)
    {
        $_css = ['components/site_header', 'components/site_footer', 'components/room_list', 'pages/terms'];
        $_meta = meta()->setTitle(t('オプチャグラフとは？'));
        $_meta->image_url = '';
        $desc = t('オプチャグラフはユーザーがオープンチャットを見つけて、成長傾向をグラフやランキングで比較できるWEBサイトです。');
        $_meta->setDescription($desc)->setOgpDescription($desc);
        $_breadcrumbsShema = $breadcrumbsShema->generateSchema(t('オプチャグラフとは？'));

        // 管理表示フラグは /oc/0/admin ルートの match が注入するが、同名のクエリ文字列でも同じ引数に
        // 入ってしまうため、管理クッキーの検証を通った場合だけ有効にする
        $_adminDto = ($isAdmin && $adminAuthService->auth()) ? app(AdminOpenChat::class)->getDto(0) : null;

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
