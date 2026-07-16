<?php

declare(strict_types=1);

namespace App\Controllers\Pages;

use App\Config\AppConfig;
use App\Config\SecretsConfig;
use App\Models\RecommendRepositories\RecommendRankingRepository;
use App\Models\Repositories\OpenChatPageRepositoryInterface;
use App\Services\Narrative\OcBlogContextLinkResolver;
use App\Services\OpenChatAdmin\AdminOpenChat;
use App\Services\Recommend\RecommendGenarator;
use App\Services\Recommend\SimilarSizeRoomService;
use App\Services\StaticData\Dto\StaticTopPageDto;
use App\Services\StaticData\StaticDataFile;
use App\Views\Meta\OcPageMeta;
use App\Views\Schema\OcPageSchema;
use App\Views\Schema\PageBreadcrumbsListSchema;
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
        SimilarSizeRoomService $similarSizeRoomService,
        RankingPositionChartArgDtoFactoryInterface $rankingPositionChartArgDtoFactory,
        CollapseKeywordEnumerationsInterface $collapseKeywordEnumerations,
        OcBlogContextLinkResolver $blogLinkResolver,
        FileStorageInterface $fileStorage,
        int $open_chat_id,
        ?string $isAdminPage,
    ) {
        $this->fileStorage = $fileStorage;
        AppConfig::$listLimitTopRanking = 5;

        $_adminDto = isset($isAdminPage) ? $this->getAdminDto($open_chat_id) : null;

        if (MimimalCmsConfig::$urlRoot === '') {
            $oc = $ocRepo->getOpenChatByIdWithTag($open_chat_id);
            if (!$oc) {
                if (isset($isAdminPage) || !$ocRepo->isWithinIdRange($open_chat_id)) {
                    return false;
                }
                return $this->deletedResponse($recommendGenarator, $open_chat_id, $staticDataGeneration->getTopPageData());
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
                return $this->deletedResponse($recommendGenarator, $open_chat_id, $staticDataGeneration->getTopPageData());
            }

        }

        // 分析(narrative)は事前計算済み「データ」(JSON)を getOpenChatByIdWithTag の
        // oc_page_cache JOIN で open_chat と一緒に取得済み（$oc['narrative_data']）。
        // レンダリングはリクエスト時に oc_narrative_section テンプレートが行う。
        // 未生成（バックフィル前/生成不可）は null → 空表示。bot が叩く /oc 本体で重い計算をしない。
        $_narrative = !empty($oc['narrative_data'])
            ? (json_decode($oc['narrative_data'], true) ?: null)
            : null;

        // 分析の状態(pattern)に合うブログ導線を解決して付与する（ja のみ・該当なしは null）。
        // マッピングはサービス(OcBlogContextLinkResolver)が持ち、テンプレートは描画だけを行う。
        if ($_narrative !== null && MimimalCmsConfig::$urlRoot === '') {
            $_narrative['blog_link'] = $blogLinkResolver->resolve(
                (string)($_narrative['pattern'] ?? ''),
                is_array($_narrative['rising'] ?? null) ? $_narrative['rising'] : null,
            );
        }

        // 30日差分・ピーク・観測期間は narrative と同じ事前計算済み統計を使う。
        // キャッシュ未更新の部屋では空表示にし、ページリクエスト中の重い再集約は行わない。
        $_roomMetrics = is_array($_narrative['metrics'] ?? null) ? $_narrative['metrics'] : null;

        // グラフ初回ロードのタブ/ボタン出し分け「可用性メタ」も事前計算済み（oc_page_cache.chart_meta JOIN）。
        // これを HTML に埋め込むとフロントは初回 XHR(meta=1) を撃たずに済む。
        // 未生成（バックフィル前/生成不可）は null → フロントは従来通り meta=1 でライブ計算にフォールバック。
        $_chartMeta = !empty($oc['chart_meta'])
            ? (json_decode($oc['chart_meta'], true) ?: null)
            : null;

        // 関連ルームは recommend 静的キャッシュ(.dat / 母集団300件)から都度組み立てる。
        // ファイル読み＋unserialize のみで部屋ごとの MySQL クエリは発生しない。
        // キャッシュ未生成の部屋でも関連ルーム枠は常に表示される。
        $_similarSize = $similarSizeRoomService->fetch(
            (int)$oc['id'],
            (int)$oc['member'],
            $oc['tag1'] !== null && $oc['tag1'] !== '' ? (string)$oc['tag1'] : null,
            isset($oc['category']) ? (int)$oc['category'] : null
        );

        // 表示されるのは similarSize か category おすすめ枠のどちらか一方のみ
        // (oc_recommend_aside)。tag1/tag2 の .dat はこのページでは描画されないため読まない
        // (tag1 .dat は必要時に SimilarSizeRoomService が読む)。category .dat も
        // similarSize が確定したら不要なので読まない。
        $_recommend = $_similarSize
            ? [false, false, '', false]
            : $recommendGenarator->getRecommend(null, null, null, $oc['category']);

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
        ];

        $collapsedDescription = $collapseKeywordEnumerations->collapse($oc['description'], extraText: $oc['name']);
        $formatedDescription = trim(preg_replace("/(\r\n){3,}|\r{3,}|\n{3,}/", "\n\n", $collapsedDescription));

        // 分析(narrative)データは oc_page_cache から読み済み（$_narrative）。関連ルームは上で組み立て済み。
        // meta への narrative 連携は無し(null)＝#372以降の現行挙動を踏襲（meta description は room description ベース）。
        // og:image は統計焼き込みの動的カード（/oc/{id}/card）。SNSシェア時のクリック率を上げる。
        // thumbnail(検索用)は 1:1 の動的サムネ（/oc/{id}/thumb）。以前は部屋アイコンの LINE CDN 直リンク。
        // 日付クエリでSNS側のクロールキャッシュを1日単位で更新させる
        $_meta = $meta->generateMetadata($open_chat_id, [...$oc, 'description' => $formatedDescription], null)
            ->setCanonicalUrl(url('oc', (string)$open_chat_id))
            ->setImageUrl(url('oc', (string)$open_chat_id, 'card') . '?d=' . date('Ymd'))
            ->setTwitterCard('summary_large_image')
            ->setThumbnail(url('oc', (string)$open_chat_id, 'thumb') . '?d=' . date('Ymd'));

        $_breadcrumbsShema = $breadcrumbsShema->generateSchema(
            $oc['tag1'] ?: $category,
        );

        // dateModified は「ページ内容が実際に変わった日」を使う。open_chat.updated_at は
        // メタ情報(タイトル/説明/ステータス)変更時しか動かず、人数・推移など主役コンテンツの
        // 変化を反映しないため Google に誤った再クロールヒントを送ってしまう。sitemap と同じ
        // 内容ベースの lastmod (oc_sitemap_lastmod) を getOpenChatByIdWithTag が
        // COALESCE(sl.lastmod, oc.updated_at) として返すので、それを使う（未登録 room は
        // updated_at にフォールバック済み。念のため content_lastmod 欠落時も updated_at へ）。
        $_dateModified = new \DateTime($oc['content_lastmod'] ?? $oc['updated_at']);

        $_schema = $ocPageSchema->generateSchema(
            $_meta->title,
            $_meta->description,
            new \DateTime($oc['created_at']),
            $_dateModified,
            $oc,
            $_roomMetrics,
        );

        $_hourlyRange = $this->buildHourlyRange($oc);

        $_chartArgDto = $rankingPositionChartArgDtoFactory->create($oc, $categoryValue ?? t('すべて'));
        $_commentArgDto = [
            'openChatId' => $open_chat_id,
            'recaptchaKey' => SecretsConfig::$googleRecaptchaSiteKey,
            'openChatName' => $oc['name'],
        ];

        $formatedRowDescription = trim(preg_replace("/(\r\n){3,}|\r{3,}|\n{3,}/", "\n\n", $oc['description']));

        $canonical = url('oc', (string)$open_chat_id);
        $alternateJsonUrl = url('api/v1/rooms/' . $open_chat_id);

        // officialDto / topPageDto は oc_content では未使用のため取得も view 渡しもしない
        // (ファイル読み込みとビューの再帰エスケープの無駄を避ける)。410 ページは別経路で取得する。
        return view('oc_content', compact(
            '_meta',
            '_css',
            'oc',
            'category',
            '_chartArgDto',
            '_commentArgDto',
            '_breadcrumbsShema',
            '_schema',
            '_narrative',
            '_roomMetrics',
            '_chartMeta',
            '_recommend',
            '_similarSize',
            '_hourlyRange',
            '_adminDto',
            'formatedDescription',
            'formatedRowDescription',
            'canonical',
            'alternateJsonUrl',
        ));
    }


    private function getAdminDto(int $open_chat_id)
    {
        /** @var AdminOpenChat $admin */
        $admin = app(AdminOpenChat::class);
        return $admin->getDto($open_chat_id);
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
