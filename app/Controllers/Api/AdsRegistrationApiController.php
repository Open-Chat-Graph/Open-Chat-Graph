<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\AdsRepositories\AdsRepository;
use App\Services\Admin\AdminAuthService;
use App\Services\Recommend\RecommendUpdater;
use Shadow\Kernel\Reception;
use Shared\Exceptions\NotFoundException;

class AdsRegistrationApiController
{
    function __construct(AdminAuthService $adminAuthService)
    {
        if (!$adminAuthService->auth()) {
            throw new NotFoundException();
        }
    }

    function register(
        AdsRepository $repo,
        string $ads_title,
        string $ads_sponsor_name,
        string $ads_paragraph,
        string $ads_href,
        string $ads_img_url,
        string $ads_tracking_url,
        string $ads_title_button,
    ) {
        $id = $repo->insertAds(
            ...compact(
                'ads_title',
                'ads_sponsor_name',
                'ads_paragraph',
                'ads_href',
                'ads_img_url',
                'ads_tracking_url',
                'ads_title_button',
            )
        );

        return redirect('ads?id=' . $id);
    }

    function update(
        AdsRepository $repo,
        int $id,
        string $ads_title,
        string $ads_sponsor_name,
        string $ads_paragraph,
        string $ads_href,
        string $ads_img_url,
        string $ads_tracking_url,
        string $ads_title_button,
    ) {
        $repo->updateAds(
            ...compact(
                'id',
                'ads_title',
                'ads_sponsor_name',
                'ads_paragraph',
                'ads_href',
                'ads_img_url',
                'ads_tracking_url',
                'ads_title_button',
            )
        );

        return redirect('ads?id=' . $id);
    }

    function delete(
        AdsRepository $repo,
        int $id,
    ) {
        $repo->deleteAdsById($id);
        return redirect('ads');
    }

    function updateTagsMap(
        RecommendUpdater $recommendUpdater,
        AdsRepository $repo,
        int $ads_id,
        string $tag,
    ) {
        Reception::$isJson = true;

        if (array_search($tag, $recommendUpdater->getAllTagNames()) === false)
            return response("存在しないタグ: {$tag}", 400);
        //throw new Bad("存在しないタグ: {$tag}");

        $ads_id !== 0 ? $repo->insertTagMap($ads_id, $tag) : $repo->deleteTagMap($tag);
        return response(null);
        //return redirect('labs/tags/ads');
    }
}
