<?php

declare(strict_types=1);

namespace App\Controllers\Pages;

use App\Config\AppConfig;
use App\Services\StaticData\StaticDataFile;
use App\Services\Seo\SeoLinks;

class IndexPageController
{
    function index(
        StaticDataFile $staticDataGeneration,
    ) {
        AppConfig::$listLimitTopRanking = 3;
        $dto = $staticDataGeneration->getTopPageData();

        $_css = ['components/room_list', 'components/site_header', 'components/site_footer', 'components/search_form', 'components/recommend_list', 'pages/recommend_page', 'pages/top_page'];
        $_meta = meta();
        $_meta->title = "{$_meta->title}｜" . t('オープンチャットの統計情報');
        $canonical = rtrim(url(), '/');
        $_meta->setCanonicalUrl($canonical);
        $hreflang = SeoLinks::localeAlternates();

        $_schema = $_meta->generateTopPageSchema();

        $_updatedAt = $dto->rankingUpdatedAt;

        return view('top_content', compact(
            '_meta',
            '_css',
            '_schema',
            'canonical',
            'hreflang',
            '_updatedAt',
            'dto',
        ));
    }
}
