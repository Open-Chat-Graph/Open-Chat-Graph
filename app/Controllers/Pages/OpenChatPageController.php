<?php

declare(strict_types=1);

namespace App\Controllers\Pages;

use App\Config\AppConfig;
use App\Config\SecretsConfig;
use App\Models\RecommendRepositories\RecommendRankingRepository;
use App\Models\Repositories\OpenChatPageRepositoryInterface;
use App\Models\SQLite\Repositories\OcPageCacheRepository;
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
        PageBreadcrumbsListSchema $breadcrumbsShema,
        OcPageSchema $ocPageSchema,
        StaticDataFile $staticDataGeneration,
        RecommendGenarator $recommendGenarator,
        RankingPositionChartArgDtoFactoryInterface $rankingPositionChartArgDtoFactory,
        CollapseKeywordEnumerationsInterface $collapseKeywordEnumerations,
        FileStorageInterface $fileStorage,
        OcPageCacheRepository $ocPageCacheRepository,
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

        } else {
            // TW/TH も ja と同じくタグ表示・関連ルームを出す。
            // tag(recommend) / oc_tag / oc_tag2 は言語別DBに格納済みのため、
            // ja と同じ getOpenChatByIdWithTag で tag1/2/3 を引き、同じ生成ロジックを通す。
            $oc = $ocRepo->getOpenChatByIdWithTag($open_chat_id);
            if (!$oc) {
                if (!$ocRepo->isWithinIdRange($open_chat_id)) {
                    return false;
                }
                return $this->deletedResponse($recommendGenarator, $open_chat_id, $topPageDto);
            }

        }

        // 分析文・関連ルームは事前計算済みHTML断片を oc_page_cache(SQLite) からPK一発で読むだけ。
        // 未生成（バックフィル前/生成不可）は null → 空表示。bot が叩く /oc 本体で重い計算をしない。
        $ocPageCache = $ocPageCacheRepository->get($open_chat_id);
        $_narrativeHtml = $ocPageCache['narrative_html'] ?? '';
        $_recommendHtml = $ocPageCache['recommend_html'] ?? '';

        $categoryValue = $oc['category'] ? array_search($oc['category'], AppConfig::OPEN_CHAT_CATEGORY[MimimalCmsConfig::$urlRoot]) : null;
        $category = $categoryValue ?? t('未指定');

        // 統計チャートデータは graph(React)が /oc/{id}/stats から初回も非同期取得する
        // （bot が叩く /oc 本体から統計の重い SQLite 読み取りを外す）。
        // ヘッダーの「1週間」差分は getOpenChatByIdWithTag の statistics_ranking_week JOIN(rw_*) で取得済み。

        $_css = [
            'components/room_list',
            'components/site_header',
            'components/site_footer',
            'pages/recommend_page',
            'pages/room_page',
            'react/OpenChat',
            'pages/graph_page',
            'components/ads_element'
        ];

        $collapsedDescription = $collapseKeywordEnumerations->collapse($oc['description'], extraText: $oc['name']);
        $formatedDescription = trim(preg_replace("/(\r\n){3,}|\r{3,}|\n{3,}/", "\n\n", $collapsedDescription));

        // 分析文(narrative)・関連ルームは oc_page_cache から読み済み（$_narrativeHtml/$_recommendHtml）。
        // meta への narrative 連携は無し(null)＝#372以降の現行挙動を踏襲（meta description は room description ベース）。
        $_meta = $meta->generateMetadata($open_chat_id, [...$oc, 'description' => $formatedDescription], null)->setImageUrl(imgUrl($oc['img_url']));
        $_meta->thumbnail = imgPreviewUrl($oc['img_url']);

        $_breadcrumbsShema = $breadcrumbsShema->generateSchema(
            $oc['tag1'] ?: $category,
        );

        $_schema = $ocPageSchema->generateSchema(
            $_meta->title,
            $_meta->description,
            new \DateTime($oc['created_at']),
            new \DateTime($oc['updated_at']),
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
            '_commentArgDto',
            '_breadcrumbsShema',
            '_schema',
            '_narrativeHtml',
            '_recommendHtml',
            '_hourlyRange',
            '_adminDto',
            'officialDto',
            'topPageDto',
            'formatedDescription',
            'formatedRowDescription',
        ));
    }

    /**
     * 統計チャートデータ（メンバー数推移・期間タブ可用性）を返す。
     * graph(React) が初回ロード時にこれを fetch する。これにより bot が叩く /oc 本体の
     * サーバーレンダリングから統計の重い SQLite 読み取り（member/OHLC/順位）を外す。
     */
    function stats(
        StatisticsChartArrayService $statisticsChartArrayService,
        int $open_chat_id,
        int $category,
    ) {
        $dto = $statisticsChartArrayService->buildStatisticsChartArray(
            $open_chat_id,
            $category > 0 ? $category : null
        );
        if (!$dto) {
            $dto = new StatisticsChartDto(
                (new \DateTime('-1day'))->format('Y-m-d'),
                (new \DateTime('now'))->format('Y-m-d')
            );
        }

        return response($dto);
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
        $_css = ['components/room_list', 'components/site_header', 'components/site_footer', 'components/recommend_list'];

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
