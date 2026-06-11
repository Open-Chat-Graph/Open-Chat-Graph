<?php

declare(strict_types=1);

namespace App\Controllers\Pages;

use App\Config\AppConfig;
use App\Services\Recommend\OfficialPageList;
use App\Services\StaticData\StaticDataFile;

class IndexPageController
{
    function index(
        StaticDataFile $staticDataGeneration,
        OfficialPageList $officialPageList,
    ) {
        AppConfig::$listLimitTopRanking = 10;
        $dto = $staticDataGeneration->getTopPageData();

        $_css = ['components/room_list', 'components/site_header', 'components/site_footer', 'components/search_form', 'components/recommend_list', 'pages/recommend_page', 'pages/top_page'];
        $_meta = meta();
        $_meta->title = "{$_meta->title}｜" . t('オープンチャットの統計情報');

        $_schema = $_meta->generateTopPageSchema();

        $officialDto = $officialPageList->getListDto(1);
        $officialDto2 = $officialPageList->getListDto(2);

        $_updatedAt = $dto->rankingUpdatedAt;

        return view('top_content', compact(
            '_meta',
            '_css',
            '_schema',
            '_updatedAt',
            'dto',
            'officialDto',
            'officialDto2',
        ));
    }
}
