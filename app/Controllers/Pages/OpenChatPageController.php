<?php

declare(strict_types=1);

namespace App\Controllers\Pages;

use App\Config\AppConfig;
use App\Models\CommentRepositories\RecentCommentListRepositoryInterface;
use App\Models\RecommendRepositories\RecommendRankingRepository;
use App\Models\Repositories\OpenChatPageRepositoryInterface;
use App\Services\OpenChatAdmin\AdminOpenChat;
use App\Services\Recommend\Dto\RecommendListDto;
use App\Services\Recommend\OfficialPageList;
use App\Services\Recommend\RecommendGenarator;
use App\Services\StaticData\Dto\StaticTopPageDto;
use App\Services\StaticData\StaticDataFile;
use App\Services\Statistics\StatisticsChartArrayService;
use App\Views\Meta\OcPageMeta;
use App\Views\Schema\OcPageSchema;
use App\Views\Schema\PageBreadcrumbsListSchema;
use App\Views\StatisticsViewUtility;
use App\Services\Statistics\Dto\StatisticsChartDto;
use App\Views\Classes\CollapseKeywordEnumerationsInterface;
use App\Views\Classes\Dto\RankingPositionChartArgDtoFactoryInterface;
use App\Views\Classes\Dto\CommentArgDtoFactoryInterface;
use App\Services\Storage\FileStorageInterface;
use Shared\MimimalCmsConfig;

class OpenChatPageController
{
    private FileStorageInterface $fileStorage;

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
        RankingPositionChartArgDtoFactoryInterface $rankingPositionChartArgDtoFactory,
        CommentArgDtoFactoryInterface $commentArgDtoFactory,
        CollapseKeywordEnumerationsInterface $collapseKeywordEnumerations,
        FileStorageInterface $fileStorage,
        int $open_chat_id,
        ?string $isAdminPage,
    ) {
        $this->fileStorage = $fileStorage;
        AppConfig::$listLimitTopRanking = 5;

        $_adminDto = isset($isAdminPage) && adminMode() ? $this->getAdminDto($open_chat_id) : null;
        $topPageDto = $staticDataGeneration->getTopPageData();

        if (MimimalCmsConfig::$urlRoot === '') {
            $oc = $ocRepo->getOpenChatByIdWithTag($open_chat_id);
            if (!$oc)
                return $this->deletedResponse($recommendGenarator, $open_chat_id, $topPageDto);

            $recommend = $recommendGenarator->getRecommend($oc['tag1'], $oc['tag2'], $oc['tag3'], $oc['category']);
        } else {
            $oc = $ocRepo->getOpenChatById($open_chat_id);
            if (!$oc)
                return false;

            /** @var RecommendRankingRepository $recommendRankingRepository */
            $recommendRankingRepository = app(RecommendRankingRepository::class);
            $tags1 = $recommendRankingRepository->getRecommendTags([$open_chat_id]);
            $tags2 = array_filter($recommendRankingRepository->getOcTags([$open_chat_id]), fn($tag) => !in_array($tag, $tags1));

            $tagFirst = null;
            $tagSecond = null;
            $tagThird = null;

            switch (count($tags1)) {
                case 0:
                    break;
                case 1:
                    $tagFirst = $tags1[array_rand($tags1)];
                    $tagSecond = $tags2 ? $tags2[array_rand($tags2)] : null;
                    break;
                case 2:
                    $tagFirst = $tags1[array_rand($tags1)];
                    $tags1 = array_filter($tags1, fn($tag) => $tag !== $tagFirst);
                    $tagSecond = $tags1[array_rand($tags1)];
                    $tagThird = $tags2 ? $tags2[array_rand($tags2)] : null;
                    break;
                default:
                    $tagFirst = $tags1[array_rand($tags1)];
                    $tags1 = array_filter($tags1, fn($tag) => $tag !== $tagFirst);
                    $tagSecond = $tags1[array_rand($tags1)];
                    $tags1 = array_filter($tags1, fn($tag) => $tag !== $tagSecond);
                    $tagThird = $tags1[array_rand($tags1)];
            }

            $recommend = $recommendGenarator->getRecommend(
                $tags1 ? $tags1[array_rand($tags1)] : null,
                $tags2 ? $tags2[array_rand($tags2)] : null,
                $oc['tag3'],
                $oc['category']
            );
        }

        $categoryValue = $oc['category'] ? array_search($oc['category'], AppConfig::OPEN_CHAT_CATEGORY[MimimalCmsConfig::$urlRoot]) : null;
        $category = $categoryValue ?? t('未指定');

        $_statsDto = $statisticsChartArrayService->buildStatisticsChartArray($open_chat_id);
        if (!$_statsDto) {
            $_statsDto = new StatisticsChartDto((new \DateTime('-1day'))->format('Y-m-d'), (new \DateTime('now'))->format('Y-m-d'));
        }

        $oc += $statisticsViewUtility->getOcPageArrayElementMemberDiff($_statsDto, $oc['member']);

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

        $collapsedDescription = $collapseKeywordEnumerations->collapse($oc['description'], extraText: $oc['name']);
        $formatedDescription = trim(preg_replace("/(\r\n){3,}|\r{3,}|\n{3,}/", "\n\n", $collapsedDescription));

        $_meta = $meta->generateMetadata($open_chat_id, [...$oc, 'description' => $formatedDescription])->setImageUrl(imgUrl($oc['img_url']));
        $_meta->thumbnail = imgPreviewUrl($oc['img_url']);

        $_breadcrumbsShema = $breadcrumbsShema->generateSchema(
            $oc['tag1'] ?: $category,
        );

        $_schema = $ocPageSchema->generateSchema(
            $_meta->title,
            $_meta->description,
            new \DateTime($oc['created_at']),
            new \DateTime($_statsDto->endDate),
            $oc,
        );

        $_hourlyRange = $this->buildHourlyRange($oc);

        $_chartArgDto = $rankingPositionChartArgDtoFactory->create($oc, $categoryValue ?? t('すべて'));
        $_commentArgDto = $commentArgDtoFactory->create($oc['id']);
        $officialDto = ($oc['emblem'] ?? 0) > 0 ? $this->buildOfficialDto($oc['emblem']) : null;

        $formatedRowDescription = trim(preg_replace("/(\r\n){3,}|\r{3,}|\n{3,}/", "\n\n", $oc['description']));

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
            'topPageDto',
            'formatedDescription',
            'formatedRowDescription',
        ));
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
        return $officialPageList->getListDto($emblem);
    }

    private function deletedResponse(
        RecommendGenarator $recommendGenarator,
        int $open_chat_id,
        StaticTopPageDto $topPageDto
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

        [$tag2, $tag3] = $repo->getTags($open_chat_id);
        $recommend = $recommendGenarator->getRecommend($tag, $tag2 ?: null, $tag3 ?: null, null);

        http_response_code(404);
        return view('errors/oc_error', compact('_meta', '_css', 'recommend', 'open_chat_id', 'topPageDto'));
    }

    private function buildHourlyRange(array $oc): ?string
    {
        if (!isset($oc['rh_diff_member']) || $oc['rh_diff_member'] < AppConfig::RECOMMEND_MIN_MEMBER_DIFF_HOUR)
            return null;

        $hourlyUpdatedAt =  new \DateTime($this->fileStorage->getContents('@hourlyCronUpdatedAtDatetime'));
        $hourlyTime = $hourlyUpdatedAt->format(\DateTime::ATOM);
        $hourlyUpdatedAt->modify('-1hour');

        return '<time datetime="' . $hourlyTime . '">' . t('1時間') . '</time>';
    }
}
