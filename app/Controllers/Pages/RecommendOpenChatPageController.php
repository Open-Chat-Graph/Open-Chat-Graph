<?php

declare(strict_types=1);

namespace App\Controllers\Pages;

use App\Config\AppConfig;
use App\Services\Recommend\RecommendPageList;
use App\Services\Recommend\TagDefinition\Ja\RecommendTagFilters;
use App\Services\Recommend\TagDefinition\Ja\RecommendUtility;
use App\Services\StaticData\StaticDataFile;
use App\Views\Schema\PageBreadcrumbsListSchema;
use Shared\MimimalCmsConfig;

class RecommendOpenChatPageController
{
    function __construct(
        private PageBreadcrumbsListSchema $breadcrumbsShema
    ) {}

    function index(
        RecommendPageList $recommendPageList,
        StaticDataFile $staticDataGeneration,
        string $tag
    ) {
        AppConfig::$listLimitTopRanking = 5;
        if (MimimalCmsConfig::$urlRoot === '') {
            if (isset(RecommendTagFilters::RedirectTags[$tag]))
                return redirect('recommend/' . urlencode(RecommendTagFilters::RedirectTags[$tag]), 301);
            
            $extractTag = RecommendUtility::getValidTag($tag);
        } else {
            $extractTag = $tag;
        }
        
        $tag = $recommendPageList->getValidTag($tag);
        if (!$tag)
            return false;

        $extractTag = $extractTag ?: $tag;

        $_dto = $staticDataGeneration->getRecommendPageDto();

        $count = 0;

        if (MimimalCmsConfig::$urlRoot === '') {
            $pageDesc =
                "2019年のサービス開始以来、累計3,000万人以上のユーザーに利用されているLINEオープンチャット。そこで、オプチャグラフでは、「{$tag}」をテーマにした中で、最近人数が急増しているルームのランキングを作成しました。このランキングは1時間ごとに更新され、新しいルームが継続的に追加されます。";
        } elseif (MimimalCmsConfig::$urlRoot === '/tw') {
            $pageDesc =
                "自 2019 年推出以來，LINE OpenChat 已累積超過 3,000 萬名用戶使用。在 這個網站 中，我們根據「{$tag}」主題，統計最近成長最快的聊天室排名。此排名每小時更新，並持續新增新的聊天室。";
        } elseif (MimimalCmsConfig::$urlRoot === '/th') {
            $pageDesc =
                "ตั้งแต่เปิดตัวในปี 2019 LINE OpenChat มีผู้ใช้สะสมมากกว่า 30 ล้านคนแล้ว บน เว็บไซต์นี้ เราจัดอันดับห้องแชทที่เติบโตเร็วที่สุดในหัวข้อ \"{$tag}\" การจัดอันดับนี้อัปเดตทุกชั่วโมง และมีห้องใหม่เพิ่มขึ้นอย่างต่อเนื่อง";
        }

        $_meta = meta()
            ->setDescription($pageDesc)
            ->setOgpDescription($pageDesc);

        $_css = ['room_list', 'site_header', 'site_footer', 'recommend_page'];

        $_breadcrumbsShema = $this->breadcrumbsShema->generateSchema(
            $extractTag,
        );

        $canonical = url('recommend/' . urlencode($tag));

        $topPageDto = $staticDataGeneration->getTopPageData();

        $recommend = $recommendPageList->getListDto($tag);
        if (!$recommend || !$recommend->getCount()) {
            $_schema = '';
            $_meta->setTitle(t('【最新】') . sprintfT("「%s」おすすめオープンチャットランキング", $tag));
            noStore();
            return view('recommend_content', compact(
                '_meta',
                '_css',
                'tag',
                'extractTag',
                '_breadcrumbsShema',
                'count',
                '_schema',
                '_dto',
                'topPageDto',
                'canonical',
            ));
        }

        $recommendList = $recommend->getList(false);
        $hourlyUpdatedAt = new \DateTime($recommend->hourlyUpdatedAt);

        $count = $recommend->getCount();
        $headline = t('【最新】') . sprintfT("「%s」おすすめオープンチャットランキング", $tag);
        $_meta->setTitle($headline);
        $_meta->setImageUrl(imgUrl($recommendList[0]['img_url']));
        $_meta->thumbnail = imgPreviewUrl($recommendList[0]['img_url']);

        $_schema = $this->breadcrumbsShema->generateRecommend(
            $headline,
            $_meta->description,
            $hourlyUpdatedAt,
            $tag
        );

        return view('recommend_content', compact(
            '_meta',
            '_css',
            '_breadcrumbsShema',
            'recommend',
            'tag',
            'extractTag',
            'count',
            '_schema',
            '_dto',
            'topPageDto',
            'canonical',
            'hourlyUpdatedAt',
        ));
    }
}
