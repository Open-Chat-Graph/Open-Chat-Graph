<?php

declare(strict_types=1);

namespace App\Controllers\Pages;

use App\Config\AppConfig;
use App\Models\Repositories\Recommend\RecommendGrowthRepository;
use App\Services\Recommend\RecommendPageList;
use App\Services\Recommend\TagDefinition\Ja\RecommendTagDescription;
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
            $redirectTags = RecommendTagFilters::redirectTags();
            if (isset($redirectTags[$tag]))
                return redirect('recommend/' . urlencode($redirectTags[$tag]), 301);

            $extractTag = RecommendUtility::getValidTag($tag);
        } else {
            $extractTag = $tag;
        }
        
        $tag = $recommendPageList->getValidTag($tag);
        if (!$tag)
            return false;

        $extractTag = $extractTag ?: $tag;

        // 高需要タグ向けのテーマ固有紹介文(ja専用)。無ければ null でベースライン文のみ。
        $tagDescription = MimimalCmsConfig::$urlRoot === '' ? RecommendTagDescription::get($tag) : null;

        $_dto = $staticDataGeneration->getRecommendPageDto();

        $count = 0;

        if (MimimalCmsConfig::$urlRoot === '') {
            $pageDesc =
                "「{$tag}」のLINEオープンチャット(オプチャ)を、いま活発に伸びている部屋順に一覧化。メンバー数の増減を1時間ごとに集計してランキング化しているので、今まさに参加者が増えている部屋・新しく募集中の部屋がひと目で分かります。新着ルームも随時追加。";
        } elseif (MimimalCmsConfig::$urlRoot === '/tw') {
            $pageDesc =
                "將「{$tag}」的 LINE 開放聊天室(OpenChat)依正在活躍成長的順序整理。我們每小時統計成員人數的增減並排名，讓你一眼看出此刻人數正在增加、正在招募新成員的聊天室。也會隨時新增新的聊天室。";
        } elseif (MimimalCmsConfig::$urlRoot === '/th') {
            $pageDesc =
                "รวม LINE OpenChat หัวข้อ \"{$tag}\" โดยเรียงตามห้องที่กำลังเติบโตและคึกคัก เรานับการเปลี่ยนแปลงจำนวนสมาชิกทุกชั่วโมงเพื่อจัดอันดับ คุณจึงเห็นได้ทันทีว่าห้องไหนกำลังมีคนเพิ่มขึ้นหรือกำลังรับสมาชิกใหม่ และมีการเพิ่มห้องใหม่อยู่เสมอ";
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
            $_meta->setTitle(sprintfT('「%s」のオープンチャット｜人気・活発な部屋ランキング', $tag));
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
                'tagDescription',
            ));
        }

        $recommendList = $recommend->getList(false);
        $hourlyUpdatedAt = new \DateTime($recommend->hourlyUpdatedAt);

        $count = $recommend->getCount();
        $headline = sprintfT('「%s」のオープンチャット｜人気・活発な部屋ランキング', $tag);
        $_meta->setTitle($headline);
        $_meta->setImageUrl(imgUrl($recommendList[0]['img_url']));
        $_meta->thumbnail = imgPreviewUrl($recommendList[0]['img_url']);

        $_schema = $this->breadcrumbsShema->generateRecommend(
            $headline,
            $_meta->description,
            $hourlyUpdatedAt,
            $tag
        );

        // テーマの勢い: rank/rising は ranking_position.db(ロケール別)、
        // member(合計人数)は statistics.db(ロケール別)から集計。ja/tw/th 全ロケール対応。
        $growth = RecommendGrowthRepository::themeMomentum(array_column($recommend->getList(false, null), 'id'));

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
            'tagDescription',
            'growth',
        ));
    }
}
