<?php

declare(strict_types=1);

namespace App\Services\StaticData;

use App\Config\AppConfig;
use App\Models\Repositories\OpenChatPageRepositoryInterface;
use App\Models\SQLite\Repositories\OcPageCacheRepository;
use App\Services\Narrative\OcNarrativeService;
use App\Views\Classes\CollapseKeywordEnumerationsInterface;
use Shared\MimimalCmsConfig;

/**
 * ルーム個別ページの「分析文(narrative)」を事前計算し、
 * レンダリング済みHTML断片として oc_page_cache(SQLite) に保存する。
 *
 * /oc 表示時はこのキャッシュをPK一発SELECTで読むだけにし、bot クロール時に
 * narrative の重い読み取りを発生させない。
 *
 * 関連ルーム(recommend/similarSize)はここでは生成しない。/oc 表示時に
 * recommend 静的キャッシュ(.dat / 母集団300件)から都度組み立てる方式に移行した
 * （SimilarSizeRoomService）。recommend_html カラムは互換のため空文字で埋める。
 *
 * 注: 出力HTMLは url()/t() 等が現在の MimimalCmsConfig::$urlRoot に依存するため、
 * 言語別 cron 文脈（urlRoot 設定済み）から呼ぶこと。保存先 SQLite も urlRoot の storage 配下になる。
 */
class OcPageCacheGenerator
{
    public function __construct(
        private OpenChatPageRepositoryInterface $ocRepo,
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

            $rows[] = [
                'open_chat_id' => $id,
                'narrative_html' => (string)$narrativeHtml,
                'recommend_html' => '',
            ];
        }

        $this->cacheRepo->upsertMany($rows);

        return count($rows);
    }
}
