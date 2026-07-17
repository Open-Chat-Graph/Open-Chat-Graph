<?php

declare(strict_types=1);

namespace App\Controllers\Pages;

use App\Services\OpenChatAdmin\AdminOpenChat;
use App\Views\Schema\PageBreadcrumbsListSchema;
use App\Services\Seo\SeoLinks;

class PolicyPageController
{
    function index(PageBreadcrumbsListSchema $breadcrumbsShema, ?bool $isAdmin = null)
    {
        $_css = ['components/site_header', 'components/site_footer', 'components/room_list', 'pages/terms'];
        $_meta = meta()->setTitle(t('オプチャグラフとは？'));
        $_meta->image_url = '';
        $desc = t('オプチャグラフはユーザーがオープンチャットを見つけて、成長傾向をグラフやランキングで比較できるWEBサイトです。');
        $_meta->setDescription($desc)->setOgpDescription($desc);
        $canonical = url('policy');
        $_meta->setCanonicalUrl($canonical);
        $hreflang = SeoLinks::localeAlternates('policy');
        $_breadcrumbsShema = $breadcrumbsShema->generateSchema(t('オプチャグラフとは？'));

        $_adminDto = $isAdmin ? app(AdminOpenChat::class)->getDto(0) : null;

        return view('policy_content', compact('_meta', '_css', '_breadcrumbsShema', '_adminDto', 'canonical', 'hreflang'));
    }

    function privacy(PageBreadcrumbsListSchema $breadcrumbsShema)
    {
        $_css = ['components/site_header', 'components/site_footer', 'components/room_list', 'pages/terms'];
        $_meta = meta()->setTitle(t('プライバシーポリシー'));
        $_meta->image_url = '';
        $desc = t('オプチャグラフはユーザーがオープンチャットを見つけて、成長傾向をグラフやランキングで比較できるWEBサイトです。');
        $_meta->setDescription($desc)->setOgpDescription($desc);
        $canonical = url('privacy');
        $_meta->setCanonicalUrl($canonical);
        $hreflang = SeoLinks::localeAlternates('privacy');
        $_breadcrumbsShema = $breadcrumbsShema->generateSchema(t('オプチャグラフとは？'), 'policy', t('プライバシーポリシー'));

        return view('privacy_content', compact('_meta', '_css', '_breadcrumbsShema', 'canonical', 'hreflang'));
    }

    function term()
    {
        $_css = ['pages/terms'];
        $canonical = url('terms');
        $_meta = meta()
            ->setTitle('利用規約')
            ->setDescription('オプチャグラフの利用規約')
            ->setOgpDescription('オプチャグラフの利用規約')
            ->setCanonicalUrl($canonical);
        return view('term_content', compact('_meta', '_css', 'canonical'));
    }

}
