<?php

declare(strict_types=1);

namespace App\Controllers\Pages;

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
        $_breadcrumbsShema = $breadcrumbsShema->generateSchema(self::Title);

        $_recommendDto = $staticDataGeneration->getRecommendPageDto();

        return view('labs_content', compact(
            '_meta',
            '_css',
            '_breadcrumbsShema',
            '_recommendDto',
        ));
    }

    function live()
    {
        $name = 'ライブトーク利用時間分析ツール';
        $desc = "オープンチャットのトーク履歴から、ライブトークの通話時間・開催メンバーをグラフで表示することができます。";
        $ogpDesc = "トーク履歴から、ライブトークの利用時間をグラフで表示することができます。";
        $_meta = meta()->setTitle($name)->setDescription($desc)->setOgpDescription($ogpDesc);

        $_css = ['room_page', 'site_header', 'site_footer', 'live_ana'];

        return view('live_content', compact('_meta', '_css'));
    }
}
