<?php

declare(strict_types=1);

namespace App\Services\RankingPosition\Persistence;

use App\Config\AppConfig;
use App\Models\Repositories\OpenChatDataForUpdaterWithCacheRepositoryInterface;
use App\Models\Repositories\RankingPosition\Dto\RankingPositionHourInsertDto;
use App\Models\Repositories\RankingPosition\RankingPositionHourRepositoryInterface;
use App\Services\OpenChat\Enum\RankingType;
use App\Services\RankingPosition\Store\RankingPositionStore;
use App\Services\RankingPosition\Store\RisingPositionStore;
use Shared\MimimalCmsConfig;

/**
 * 毎時ランキングDB反映の処理ロジック
 *
 * 1サイクル分のカテゴリ処理を担当。
 * ループ制御とタイムアウト管理は上位クラス（RankingPositionHourPersistence）が行う。
 */
class RankingPositionHourPersistenceProcess
{
    /** @var array<string, array{rising: bool, ranking: bool}> カテゴリごとの処理状態 */
    private array $processedState = [];

    /** 
     * @param ''|'tw'|'th' $urlRoot 
     */
    function __construct(
        private OpenChatDataForUpdaterWithCacheRepositoryInterface $openChatDataWithCache,
        private RankingPositionHourRepositoryInterface $rankingPositionHourRepository,
        private RisingPositionStore $risingPositionStore,
        private RankingPositionStore $rankingPositionStore,
        ?string $urlRoot = null,
    ) {
        // 処理状態を初期化
        $this->processedState = array_fill_keys(
            array_values(AppConfig::OPEN_CHAT_CATEGORY[$urlRoot ?? MimimalCmsConfig::$urlRoot]),
            ['rising' => false, 'ranking' => false]
        );
    }

    /**
     * OpenChatデータのキャッシュを初期化（emid→id変換用）
     */
    function initializeCache(): void
    {
        $this->openChatDataWithCache->clearCache();
        $this->openChatDataWithCache->cacheOpenChatData(true);
    }

    /**
     * キャッシュをクリア
     */
    function afterClearCache(): void
    {
        $this->openChatDataWithCache->clearCache();
    }

    /**
     * 処理状態を取得（ログ出力用）
     *
     * @return array<string, array{rising: bool, ranking: bool}> カテゴリごとの処理状態
     */
    function getProcessedState(): array
    {
        return $this->processedState;
    }

    /**
     * 1サイクル分の処理を実行
     *
     * 全カテゴリ×2種類（急上昇/ランキング）をチェックし、
     * ファイルが準備できているものから順次DB反映を行う。
     *
     * @param string $expectedFileTime 期待されるファイルタイムスタンプ
     * @param string $logSuffix ログ出力時のサフィックス（必要に応じて指定）
     * @return bool すべて完了した場合true、未完了がある場合false
     */
    function processOneCycle(string $expectedFileTime, string $logSuffix = ''): bool
    {
        $allCompleted = true;
        $categories = AppConfig::OPEN_CHAT_CATEGORY[MimimalCmsConfig::$urlRoot];

        // 処理対象の定義（急上昇とランキングの2種類）
        $processTargets = [
            ['type' => RankingType::Rising, 'store' => $this->risingPositionStore, 'key' => 'rising'],
            ['type' => RankingType::Ranking, 'store' => $this->rankingPositionStore, 'key' => 'ranking'],
        ];

        foreach ($categories as $key => $category) {
            foreach ($processTargets as $target) {
                // 既に処理済みならスキップ
                if ($this->processedState[$category][$target['key']]) {
                    continue;
                }

                // ファイルが準備できていたら処理、まだならfalseが返る
                if (!$this->processCategoryTarget($category, $key, $target, $expectedFileTime, $logSuffix)) {
                    $allCompleted = false;
                }
            }
        }

        return $allCompleted;
    }

    /**
     * カテゴリとターゲット（Rising/Ranking）の組み合わせを処理
     *
     * ストレージファイルのタイムスタンプをチェックし、期待値と一致していれば
     * DB反映処理を実行する。まだファイルが準備できていない場合はfalseを返す。
     *
     * @param int $category カテゴリID
     * @param string $categoryName カテゴリ名（ログ用）
     * @param array{ type: RankingType, store: RisingPositionStore, key: string }
     *         | array{ type: RankingType, store: RankingPositionStore, key: string }
     *        $target 処理対象情報（type, store, key）
     * @param string $expectedFileTime 期待されるファイルタイムスタンプ
     * @param string $logSuffix ログ出力時のサフィックス（必要に応じて指定）
     * @return bool 処理が完了した場合true、まだファイルが準備できていない場合false
     */
    private function processCategoryTarget(
        int $category,
        string $categoryName,
        array $target,
        string $expectedFileTime,
        string $logSuffix = ''
    ): bool {
        $categoryStr = (string)$category;

        // ストレージファイルのタイムスタンプをチェック
        $fileTime = $target['store']->getFileDateTime($categoryStr)->format('Y-m-d H:i:s');
        if ($fileTime !== $expectedFileTime)
            return false; // タイムスタンプが一致しない（まだダウンロード中）

        // DB反映処理を実行
        $label = "{$categoryName}の" . ($target['type'] === RankingType::Rising ? '急上昇' : 'ランキング');
        $perfStartTime = microtime(true);
        addVerboseCronLog("{$label}をデータベースに反映中" . $logSuffix);

        // ストレージからデータを取得してDTO配列に変換
        [, $ocDtoArray] = $target['store']->getStorageData($categoryStr);
        $insertDtoArray = $this->createInsertDtoArray($ocDtoArray);
        unset($ocDtoArray); // メモリ解放

        // ランキングデータをDBに挿入
        $this->rankingPositionHourRepository->insertFromDtoArray($target['type'], $expectedFileTime, $insertDtoArray);

        // ランキングタイプまたは全体カテゴリ（0）の場合、メンバー数も記録
        if ($target['type'] === RankingType::Ranking || (int)$category === 0) {
            $this->rankingPositionHourRepository->insertHourMemberFromDtoArray($expectedFileTime, $insertDtoArray);
        }

        addVerboseCronLog("{$label}をデータベースに反映完了（" . formatElapsedTime($perfStartTime) . "）", count($insertDtoArray) . $logSuffix);
        unset($insertDtoArray); // メモリ解放

        // 処理完了フラグを立てる
        $this->processedState[$category][$target['key']] = true;
        return true;
    }

    /**
     * OpenChatDto配列をRankingPositionHourInsertDto配列に変換
     *
     * emidからopenChatIdを取得し、IDが見つからないものは除外する。
     *
     * @param OpenChatDto[] $data ストレージから取得したランキングデータ
     * @return RankingPositionHourInsertDto[] DB挿入用DTO配列
     */
    private function createInsertDtoArray(array $data): array
    {
        return array_values(array_filter(array_map(
            fn($dto, $key) => ($id = $this->openChatDataWithCache->getOpenChatIdByEmid($dto->emid))
                ? new RankingPositionHourInsertDto($id, $key + 1, $dto->category ?? 0, $dto->memberCount)
                : null, // IDが見つからない場合はnull（後でfilterで除外）
            $data,
            array_keys($data)
        )));
    }
}
