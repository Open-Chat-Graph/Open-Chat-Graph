<?php

declare(strict_types=1);

namespace App\Controllers\Pages;

use App\Config\AppConfig;
use App\Services\StaticData\StaticDataFile;
use App\Views\Schema\PageBreadcrumbsListSchema;

class LabsPageController
{
    const Title = '分析Labsの試験機能';
    const Desc = 'オプチャグラフの新しい分析機能をお試しいただけます。開発初期段階にある試験運用版でテストを行い、有用な機能は本採用します。';

    function index(
        PageBreadcrumbsListSchema $breadcrumbsShema,
        StaticDataFile $staticDataGeneration
    ) {
        $_css = ['site_header', 'site_footer', 'room_list', 'terms'];
        $_meta = meta()->setTitle(self::Title);
        $_meta->setDescription(self::Desc)->setOgpDescription(self::Desc);
        $_breadcrumbsShema = $breadcrumbsShema->generateSchema(self::Title, 'policy');

        $_recommendDto = $staticDataGeneration->getRecommendPageDto();

        $view = view('labs_content', compact(
            '_meta',
            '_css',
            '_breadcrumbsShema',
            '_recommendDto',
        ));

        handleRequestWithETagAndCache($view->getRenderCache(), ...AppConfig::ETAG_ARG);
        return $view;
    }

    function cacheclear()
    {
        noStore();
        header('Clear-Site-Data: "cache"');
        return view('refresh-cache');
    }
}
