<?php

declare(strict_types=1);

namespace App\Controllers\Pages;;

use App\Models\Repositories\OpenChatPageRepositoryInterface;
use Shared\MimimalCmsConfig;

class JumpOpenChatPageController
{
    function index(
        OpenChatPageRepositoryInterface $ocRepo,
        int $open_chat_id,
    ) {
        $oc = $ocRepo->getOpenChatById($open_chat_id);
        if (!$oc) return false;

        $_meta = meta()->setTitle(t('【参加確認】') . $oc['name'])
            ->setDescription(t('【参加確認】') . $oc['description'])
            ->setOgpDescription(t('【参加確認】') . $oc['description'])
            ->setImageUrl(imgUrl($oc['img_url']));

        $_css = [
            'components/room_list',
            'components/site_header',
            'components/site_footer',
            'pages/recommend_page',
            'pages/room_page',
            'react/OpenChat',
            'pages/graph_page',
        ];

        switch (MimimalCmsConfig::$urlRoot) {
            case '/th':
                $view = 'oc_content_jump_th';
                break;
            case '/tw':
                $view = 'oc_content_jump_tw';
                break;
            default:
                $view = 'oc_content_jump';
                break;
        }

        return view($view, compact(
            '_meta',
            '_css',
            'oc',
        ));
    }
}
