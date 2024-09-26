<?php

declare(strict_types=1);

namespace App\Controllers\Pages;

use App\Config\AppConfig;
use App\Models\CommentRepositories\RecentCommentListRepositoryInterface;
use App\Models\RecommendRepositories\RecommendRankingRepository;
use App\Models\Repositories\OpenChatPageRepositoryInterface;
use App\Services\Accreditation\Recommend\AcrreditationRecommend;
use App\Services\OpenChatAdmin\AdminOpenChat;
use App\Services\Recommend\Dto\RecommendListDto;
use App\Services\Recommend\OfficialPageList;
use App\Services\Recommend\RecommendGenarator;
use App\Services\StaticData\Dto\StaticTopPageDto;
use App\Services\StaticData\StaticDataFile;
use App\Services\Statistics\DownloadCsvService;
use App\Services\Statistics\StatisticsChartArrayService;
use App\Views\Dto\RankingPositionChartArgDto;
use App\Views\Meta\OcPageMeta;
use App\Views\Schema\OcPageSchema;
use App\Views\Schema\PageBreadcrumbsListSchema;
use App\Views\StatisticsViewUtility;
use Shadow\DB;

class OpenChatPageController
{
    private function deletedResponse(
        RecommendGenarator $recommendGenarator,
        int $open_chat_id,
        StaticTopPageDto $topPagedto
    ) {
        /** @var RecommendRankingRepository $repo */
        $repo = app(RecommendRankingRepository::class);
        $tag = $repo->getRecommendTag($open_chat_id);
        if (!$tag)
            return false;

        $_meta = meta()->setTitle("「{$tag}」タグ ID:{$open_chat_id} （オプチャグラフから削除済み）")
            ->setDescription("「{$tag}」タグ ID:{$open_chat_id} （オプチャグラフから削除済み）")
            ->setOgpDescription("「{$tag}」タグのオープンチャット ID:{$open_chat_id} （オプチャグラフから削除済み）");
        $_css = ['room_list', 'site_header', 'site_footer', 'recommend_list'];

        $_deleted = DB::fetch("SELECT * FROM open_chat_deleted WHERE id = :open_chat_id", compact('open_chat_id'));
        if (!$_deleted)
            return false;

        [$tag2, $tag3] = $repo->getTags($open_chat_id);
        $recommend = $recommendGenarator->getRecommend($tag, $tag2 ?: null, $tag3 ?: null, null);

        return view('errors/oc_error', compact('_meta', '_css', 'recommend', 'open_chat_id', '_deleted', 'topPagedto'));
    }

    private function buildChartDto(array $oc, string $categoryName): RankingPositionChartArgDto
    {
        $_chartArgDto = new RankingPositionChartArgDto;
        $_chartArgDto->id = $oc['id'];
        $_chartArgDto->categoryKey = $oc['category'] ?? (is_int($oc['api_created_at']) ? 0 : null);
        $_chartArgDto->categoryName = $categoryName;
        $_chartArgDto->baseUrl = url();
        return $_chartArgDto;
    }

    private function buildHourlyRange(array $oc): ?string
    {
        if (!isset($oc['rh_diff_member']) || $oc['rh_diff_member'] < AppConfig::MIN_MEMBER_DIFF_HOUR)
            return null;

        $hourlyUpdatedAt =  new \DateTime(getHouryUpdateTime());
        $hourlyTime = $hourlyUpdatedAt->format(\DateTime::ATOM);
        $hourlyUpdatedAt->modify('-1hour');

        return '<time datetime="' . $hourlyTime . '">' . '1時間' . '</time>';
    }

    private function getAdminDto(int $open_chat_id)
    {
        /** @var AdminOpenChat $admin */
        $admin = app(AdminOpenChat::class);
        return $admin->getDto($open_chat_id);
    }

    private function buildOfficialDto(int $emblem): RecommendListDto
    {
        /** @var OfficialPageList $officialPageList */
        $officialPageList = app(OfficialPageList::class);
        return $officialPageList->getListDto($emblem)[0];
    }

    function index(
        OpenChatPageRepositoryInterface $ocRepo,
        OcPageMeta $meta,
        StatisticsChartArrayService $statisticsChartArrayService,
        StatisticsViewUtility $statisticsViewUtility,
        PageBreadcrumbsListSchema $breadcrumbsShema,
        OcPageSchema $ocPageSchema,
        StaticDataFile $staticDataGeneration,
        RecommendGenarator $recommendGenarator,
        RecentCommentListRepositoryInterface $recentCommentListRepository,
        AcrreditationRecommend $acrreditation,
        int $open_chat_id,
        ?string $isAdminPage,
    ) {
        $_adminDto = isset($isAdminPage) && adminMode() ? $this->getAdminDto($open_chat_id) : null;
        $topPagedto = $staticDataGeneration->getTopPageData();
        $oc = $ocRepo->getOpenChatById($open_chat_id);
        if (!$oc)
            return $this->deletedResponse($recommendGenarator, $open_chat_id, $topPagedto);

        $tag = $oc['tag1'];
        $categoryValue = $oc['category'] ? array_search($oc['category'], AppConfig::OPEN_CHAT_CATEGORY) : null;
        $category = $categoryValue ?? 'その他';
        $recommend = $recommendGenarator->getRecommend($tag, $oc['tag2'], $oc['tag3'], $oc['category']);

        $_statsDto = $statisticsChartArrayService->buildStatisticsChartArray($open_chat_id);
        if (!$_statsDto) {
            http_response_code(503);
            echo 'メンテナンス中';
            exit;
        }

        $oc += $statisticsViewUtility->getOcPageArrayElementMemberDiff($_statsDto);

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
        $_meta = $meta->generateMetadata($open_chat_id, $oc)->setImageUrl(imgUrl($oc['id'], $oc['img_url']));
        $_meta->thumbnail = imgPreviewUrl($oc['id'], $oc['img_url']);

        $_breadcrumbsShema = $breadcrumbsShema->generateSchema(
            'オプチャ',
            'oc',
            $tag ?: $category,
            (string)$open_chat_id
        );

        $_schema = $ocPageSchema->generateSchema(
            $_meta->title,
            $_meta->description,
            new \DateTime($oc['created_at']),
            new \DateTime($_statsDto->endDate),
            $recommend,
            $oc,
        );

        $_hourlyRange = $this->buildHourlyRange($oc);

        $_chartArgDto = $this->buildChartDto($oc, $categoryValue ?? 'すべて');
        $_commentArgDto = [
            'baseUrl' => url(),
            'openChatId' => $oc['id']
        ];
        $officialDto = ($oc['emblem'] ?? 0) > 0 ? $this->buildOfficialDto($oc['emblem']) : null;

        return view('oc_content', compact(
            '_meta',
            '_css',
            'oc',
            'category',
            '_chartArgDto',
            '_statsDto',
            '_commentArgDto',
            '_breadcrumbsShema',
            '_schema',
            'recommend',
            '_hourlyRange',
            '_adminDto',
            'officialDto',
            'topPagedto',
            'acrreditation',
        ));
    }

    function csv(
        OpenChatPageRepositoryInterface $ocRepo,
        DownloadCsvService $downloadCsvService,
        int $open_chat_id
    ) {
        $oc = $ocRepo->getOpenChatById($open_chat_id);
        if (!$oc)
            return false;

        $downloadCsvService->sendCsv($open_chat_id, $oc['name']);
        exit;
    }
}
