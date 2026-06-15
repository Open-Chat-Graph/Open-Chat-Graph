<?php

declare(strict_types=1);

namespace App\Services\StaticData;

use App\Config\AppConfig;
use App\Models\Repositories\OpenChatPageRepositoryInterface;
use App\Models\Repositories\OcPageCacheRepositoryInterface;
use App\Services\Narrative\OcNarrativeService;
use App\Services\Statistics\ChartMeta\ChartMetaBuilder;
use App\Views\Classes\CollapseKeywordEnumerationsInterface;
use Shared\MimimalCmsConfig;

/**
 * ルーム個別ページの「分析(narrative)」データを事前計算し、
 * JSON({summary,detail,meta_description,pattern}) として oc_page_cache(MySQL) に保存する。
 *
 * キャッシュに入れるのは「データ」のみ。HTMLは保存しない——レンダリング
 * (oc_narrative_section テンプレート・url() 等のURLヘルパー)はリクエスト時に行う。
 * CLIでHTMLを生成すると HTTP_HOST 不在で url() が壊れる事故(#400)の再発を構造的に防ぐ。
 *
 * /oc 表示時はこのキャッシュを open_chat への JOIN(getOpenChatByIdWithTag)で一緒に読むだけにし、
 * bot クロール時に narrative の重い統計読み取りを発生させない。
 *
 * 関連ルーム(recommend/similarSize)はここでは生成しない。/oc 表示時に
 * recommend 静的キャッシュ(.dat / 母集団300件)から都度組み立てる（SimilarSizeRoomService）。
 *
 * 注: 言語別 cron 文脈（urlRoot 設定済み）から呼ぶこと。分類ラベルと保存先 DB が
 * urlRoot に依存するため。
 */
class OcPageCacheGenerator
{
    public function __construct(
        private OpenChatPageRepositoryInterface $ocRepo,
        private OcNarrativeService $narrativeService,
        private CollapseKeywordEnumerationsInterface $collapseKeywordEnumerations,
        private OcPageCacheRepositoryInterface $cacheRepo,
        private ChartMetaBuilder $chartMetaBuilder,
    ) {
    }

    /**
     * 指定IDの集合についてキャッシュを生成・保存する。
     *
     * @param int[] $ids
     * @param array<int, array{member: bool, ranking: int[], rising: int[]}> $hourMap
     *   呼び出し側が一括取得した「最新24時間集計」（open_chat_id => 集計）。
     *   該当 id がキーに無い＝直近24hに出現なし（hour 全 false）。
     * @return int 保存した件数
     */
    public function generateForIds(array $ids, array $hourMap = []): int
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

            // グラフ初回ロードのタブ/ボタン出し分け「可用性メタ」を一緒に事前計算する。
            // /oc 表示時にこれを HTML へ埋め込み、初回 XHR(meta=1) を撃たせない。null は未生成扱い。
            // 最新24時間集計はループ外で一括取得済みの $hourMap から渡す（per-room クエリを撃たない）。
            // 直近24hに出現が無い部屋（マップに居ない）は空の集計を渡す。build() に null を渡すと
            // 「ライブ＝per-room 取得」を意味してしまい、バックフィルで毎時クエリが出てしまうため。
            $meta = $this->chartMetaBuilder->build(
                $id,
                is_int($oc['category'] ?? null) ? $oc['category'] : null,
                $hourMap[$id] ?? ChartMetaBuilder::hourEntryNone(),
            );

            // 分析(narrative)に rising(急上昇/ランキング)掲載状態を相乗りさせる（ブログ導線の状態駆動用）。
            // narrative が null（生成不可）の部屋は分析セクション自体が出ないため付与不要。
            if ($narrative !== null && $meta !== null && isset($meta['risingStatus'])) {
                $narrative['rising'] = $meta['risingStatus'];
            }

            $rows[] = [
                'open_chat_id' => $id,
                'narrative_data' => $narrative === null
                    ? ''
                    : json_encode($narrative, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'chart_meta' => $meta === null
                    ? null
                    : json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ];
        }

        $this->cacheRepo->upsertMany($rows);

        return count($rows);
    }
}
