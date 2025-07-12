<?php

declare(strict_types=1);

namespace App\Controllers\Pages;

use App\Models\Repositories\OpenChatPageRepositoryInterface;
use App\Views\Schema\PageBreadcrumbsListSchema;
use Shadow\Kernel\Reception;

class RegisterOpenChatPageController
{
    function index(
        OpenChatPageRepositoryInterface $openChatRepository,
        PageBreadcrumbsListSchema $breadcrumbsShema,
    ) {
        if (Reception::input('recently-registered-page')!== null) return false;

        $view = [
            '_css' => ['room_list', 'site_header', 'site_footer'],
            '_meta' => meta()
                ->setTitle('オープンチャットを登録する')
                ->setDescription('オプチャグラフは公式ランキングからオプチャを自動で登録します。このフォームは公式ランキングに未掲載のオプチャを手動で登録できます。'),
            '_breadcrumbsShema' => $breadcrumbsShema->generateSchema('オプチャ')
        ];

        $viewBeforeRegister = fn () => view('register_form_content', $view);
        $viewAfterRegister = fn ($requestOpenChat) => view('register_form_content', $view + compact('requestOpenChat'));

        if (!session()->has('id')) {
            return $viewBeforeRegister();
        }

        // セッションにIDのリクエストがある場合
        $requestOpenChat = $openChatRepository->getOpenChatById(session('id'));
        if (!$requestOpenChat) {
            return $viewBeforeRegister();
        }

        return $viewAfterRegister($requestOpenChat);
    }
}
