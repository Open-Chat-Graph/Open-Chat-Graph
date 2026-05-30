<?php

declare(strict_types=1);

namespace App\Controllers\Pages;

use App\Config\AppConfig;
use App\Config\SecretsConfig;
use App\Models\CommentRepositories\RecentCommentListRepositoryInterface;
use App\Models\RecommendRepositories\RecommendRankingRepository;
use App\Models\Repositories\OpenChatPageRepositoryInterface;
use App\Services\OpenChatAdmin\AdminOpenChat;
use App\Services\Recommend\Dto\RecommendListDto;
use App\Services\Recommend\OfficialPageList;
use App\Services\Recommend\RecommendGenarator;
use App\Services\Recommend\SimilarSizeRoomService;
use App\Services\StaticData\Dto\StaticTopPageDto;
use App\Services\StaticData\StaticDataFile;
use App\Services\Narrative\OcNarrativeService;
use App\Services\Statistics\StatisticsChartArrayService;
use App\Views\Meta\OcPageMeta;
use App\Views\Schema\OcPageSchema;
use App\Views\Schema\PageBreadcrumbsListSchema;
use App\Views\StatisticsViewUtility;
use App\Services\Statistics\Dto\StatisticsChartDto;
use App\Views\Classes\CollapseKeywordEnumerationsInterface;
use App\Views\Classes\Dto\RankingPositionChartArgDtoFactoryInterface;
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
        CollapseKeywordEnumerationsInterface $collapseKeywordEnumerations,
        FileStorageInterface $fileStorage,
        OcNarrativeService $narrativeService,
        SimilarSizeRoomService $similarSizeRoomService,
        int $open_chat_id,
        ?string $isAdminPage,
    ) {
        $this->fileStorage = $fileStorage;
        AppConfig::$listLimitTopRanking = 5;

        $_adminDto = isset($isAdminPage) ? $this->getAdminDto($open_chat_id) : null;
        $topPageDto = $staticDataGeneration->getTopPageData();

        if (MimimalCmsConfig::$urlRoot === '') {
            $oc = $ocRepo->getOpenChatByIdWithTag($open_chat_id);
            if (!$oc) {
                if (isset($isAdminPage) || !$ocRepo->isWithinIdRange($open_chat_id)) {
                    return false;
                }
                return $this->deletedResponse($recommendGenarator, $open_chat_id, $topPageDto);
            }

            $recommend = $recommendGenarator->getRecommend($oc['tag1'], $oc['tag2'], $oc['tag3'], $oc['category']);
            $similarSize = $similarSizeRoomService->fetch(
                (int)$oc['id'],
                (int)$oc['member'],
                $oc['tag1'] !== null && $oc['tag1'] !== '' ? (string)$oc['tag1'] : null,
                isset($oc['category']) ? (int)$oc['category'] : null
            );
        } else {
            $oc = $ocRepo->getOpenChatById($open_chat_id);
            if (!$oc) {
                if (!$ocRepo->isWithinIdRange($open_chat_id)) {
                    return false;
                }
                return $this->deletedResponse($recommendGenarator, $open_chat_id, $topPageDto);
            }

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
            // 非 ja は similarSize を出さない（既存挙動を維持）
            $similarSize = null;
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

        // narrative section 用データ (全言語対応)。
        // - Service の出力文字列は t()/sprintfT() で多言語化済 (ja/tw/th)。
        // - category label の locale-aware 解決もここで行い、Service は平に文字列を消費する
        //   (OPEN_CHAT_CATEGORY は現在の urlRoot キーで引くため、各言語の表示名になる)
        // - Service が失敗しても null が返るので section / meta は既存挙動を完全維持
        $categoryLabel = null;
        $catId = $oc['category'] ?? null;
        if (is_int($catId) && $catId > 0) {
            $catMap = AppConfig::OPEN_CHAT_CATEGORY[MimimalCmsConfig::$urlRoot] ?? [];
            $label = array_search($catId, $catMap, true);
            $categoryLabel = $label !== false ? (string)$label : null;
        }
        $narrative = $narrativeService->generate($open_chat_id, [...$oc, 'description' => $formatedDescription], $categoryLabel);

        $_meta = $meta->generateMetadata($open_chat_id, [...$oc, 'description' => $formatedDescription], $narrative)->setImageUrl(imgUrl($oc['img_url']));
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
        $_commentArgDto = [
            'openChatId' => $open_chat_id,
            'recaptchaKey' => SecretsConfig::$googleRecaptchaSiteKey,
            'openChatName' => $oc['name'],
        ];

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
            'similarSize',
            '_hourlyRange',
            '_adminDto',
            'officialDto',
            'topPageDto',
            'formatedDescription',
            'formatedRowDescription',
            'narrative',
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

    /**
     * 過去に発番されていた範囲の id への !$oc アクセス時に呼ばれる削除済みレスポンス。
     *
     * - HTTP 410 Gone を返す (Google の再クロールキューから速やかに外す目的;
     *   元の 404 だと数ヶ月〜年単位で再クロールされ続ける)
     * - JP & recommend タグあり: errors/oc_error.php (リッチ UI: 「削除されました」+ recommend)
     * - JP & タグなし / TW / TH: errors/error.php (汎用、TW/TH は翻訳済み)
     *   ※ oc_error.php は本文が JP ハードコードのため他言語では使えない
     *
     * 範囲外 (=過去にも一度も発番されていない適当な id) の場合はこの関数は呼ばれず、
     * 呼び出し側で return false → framework デフォルト 404 が走る。
     */
    private function deletedResponse(
        RecommendGenarator $recommendGenarator,
        int $open_chat_id,
        StaticTopPageDto $topPageDto
    ) {
        http_response_code(410);

        // TW/TH: oc_error.php は JP 本文ハードコードのため framework error.php へ
        if (MimimalCmsConfig::$urlRoot !== '') {
            return view('errors/error', ['httpCode' => 410]);
        }

        /** @var RecommendRankingRepository $repo */
        $repo = app(RecommendRankingRepository::class);
        $tag = $repo->getRecommendTag($open_chat_id);

        $titlePrefix = $tag
            ? "「{$tag}」タグ ID:{$open_chat_id}"
            : "ID:{$open_chat_id}";
        $_meta = meta()->setTitle("{$titlePrefix} （オプチャグラフから削除済み）")
            ->setDescription("{$titlePrefix} （オプチャグラフから削除済み）")
            ->setOgpDescription(($tag ? "「{$tag}」タグのオープンチャット" : 'オープンチャット') . " ID:{$open_chat_id} （オプチャグラフから削除済み）");
        $_css = ['room_list', 'site_header', 'site_footer', 'recommend_list'];

        $recommend = [];
        if ($tag) {
            [$tag2, $tag3] = $repo->getTags($open_chat_id);
            $recommend = $recommendGenarator->getRecommend($tag, $tag2 ?: null, $tag3 ?: null, null);
        }

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
