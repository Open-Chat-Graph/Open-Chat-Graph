<?php

declare(strict_types=1);

namespace App\Services\StaticData;

use App\Config\AppConfig;
use App\Models\Repositories\OpenChatPageRepositoryInterface;
use App\Models\SQLite\Repositories\OcPageCacheRepository;
use App\Services\Narrative\OcNarrativeService;
use App\Services\Recommend\RecommendGenarator;
use App\Services\Recommend\SimilarSizeRoomService;
use App\Views\Classes\CollapseKeywordEnumerationsInterface;
use Shared\MimimalCmsConfig;

/**
 * ルーム個別ページの「分析文(narrative)」「関連ルーム(recommend/similarSize)」を事前計算し、
 * レンダリング済みHTML断片として oc_page_cache(SQLite) に保存する。
 *
 * /oc 表示時はこのキャッシュをPK一発SELECTで読むだけにし、bot クロール時に
 * narrative の重い読み取りや recommend/similarSize の MySQL を発生させない。
 *
 * 注: 出力HTMLは url()/t() 等が現在の MimimalCmsConfig::$urlRoot に依存するため、
 * 言語別 cron 文脈（urlRoot 設定済み）から呼ぶこと。保存先 SQLite も urlRoot の storage 配下になる。
 */
class OcPageCacheGenerator
{
    public function __construct(
        private OpenChatPageRepositoryInterface $ocRepo,
        private RecommendGenarator $recommendGenarator,
        private SimilarSizeRoomService $similarSizeRoomService,
        private OcNarrativeService $narrativeService,
        private CollapseKeywordEnumerationsInterface $collapseKeywordEnumerations,
        private OcPageCacheRepository $cacheRepo,
    ) {
    }

    /**
     * 指定IDの集合についてキャッシュを生成・保存する。
     *
     * @param int[] $ids
     * @return int 保存した件数
     */
    public function generateForIds(array $ids): int
    {
        $rows = [];

        foreach ($ids as $id) {
            $id = (int)$id;
            $oc = $this->ocRepo->getOpenChatByIdWithTag($id);
            if (!$oc) {
                continue;
            }

            $recommend = $this->recommendGenarator->getRecommend(
                $oc['tag1'],
                $oc['tag2'],
                $oc['tag3'],
                $oc['category']
            );
            $similarSize = $this->similarSizeRoomService->fetch(
                (int)$oc['id'],
                (int)$oc['member'],
                $oc['tag1'] !== null && $oc['tag1'] !== '' ? (string)$oc['tag1'] : null,
                isset($oc['category']) ? (int)$oc['category'] : null
            );

            $collapsed = $this->collapseKeywordEnumerations->collapse($oc['description'], extraText: $oc['name']);
            $formatedDescription = trim(preg_replace("/(\r\n){3,}|\r{3,}|\n{3,}/", "\n\n", $collapsed));

            $categoryLabel = null;
            $catId = $oc['category'] ?? null;
            if (is_int($catId) && $catId > 0) {
                $catMap = AppConfig::OPEN_CHAT_CATEGORY[MimimalCmsConfig::$urlRoot] ?? [];
                $label = array_search($catId, $catMap, true);
                $categoryLabel = $label !== false ? (string)$label : null;
            }
            $narrative = $this->narrativeService->generate(
                $id,
                [...$oc, 'description' => $formatedDescription],
                $categoryLabel
            );

            ob_start();
            viewComponent('oc_narrative_section', ['narrative' => $narrative, 'oc' => $oc]);
            $narrativeHtml = ob_get_clean();

            ob_start();
            viewComponent('oc_recommend_aside', ['similarSize' => $similarSize, 'recommend' => $recommend, 'oc' => $oc]);
            $recommendHtml = ob_get_clean();

            $rows[] = [
                'open_chat_id' => $id,
                'narrative_html' => (string)$narrativeHtml,
                'recommend_html' => (string)$recommendHtml,
            ];
        }

        $this->cacheRepo->upsertMany($rows);

        return count($rows);
    }
}
