<?php

declare(strict_types=1);

namespace App\Controllers\Pages;;

use App\Models\Repositories\OpenChatPageRepositoryInterface;

class JumpOpenChatPageController
{
    function index(
        OpenChatPageRepositoryInterface $ocRepo,
        int $open_chat_id,
    ) {
        $oc = $ocRepo->getOpenChatById($open_chat_id);

        $_meta = meta()->setTitle('【参加確認】' . $oc['name'])
            ->setDescription('【参加確認】' . $oc['description'])
            ->setOgpDescription('【参加確認】' . $oc['description'])
            ->setImageUrl(imgUrl($oc['id'], $oc['img_url']));

        $_css = [
            'room_list',
            'site_header',
            'site_footer',
            'recommend_page',
            'room_page',
            'react/OpenChat',
            'graph_page',
            'ads_element'
        ];

        return view('oc_content_jump', compact(
            '_meta',
            '_css',
            'oc',
        ));
    }
}
