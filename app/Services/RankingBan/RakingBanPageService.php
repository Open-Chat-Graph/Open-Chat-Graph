<?php

declare(strict_types=1);

namespace App\Services\RankingBan;

use App\Services\Traits\TraitPaginationRecordsCalculator;
use App\Models\RankingBanRepositories\RankingBanPageRepository;
use App\Services\RankingBan\Dto\RankingBanPageDto;

class RakingBanPageService
{
    use TraitPaginationRecordsCalculator;

    /**
     * ページ番号の上限。深い OFFSET は ranking_ban(open_chat との JOIN＋計算式 ORDER BY による
     * 全件 filesort)を数十秒に膨らませ、本番 DB を飽和させた(2026-06-24 障害。クローラが
     * page=962〜2372 を深掘り巡回し MySQL が gone away を多発)。実利用で必要な範囲を十分に
     * 超えるこのページ数で頭打ちにし、超過は範囲外(null→コントローラが404→フロントが1ページ目へ
     * 復帰)として重いクエリ自体を実行しない。CF ヘッダゲート(一次対策)に対する多層防御で、
     * cf.client.bot(Googlebot 等)はゲートを通れるためコード側でも最悪値を bound する。
     */
    private const MAX_PAGE_NUMBER = 100;

    public function __construct(
        private RankingBanPageRepository $rankingBanPageRepository
    ) {
    }

    /**
     * @param int $publish 0:掲載中のみ, 1:未掲載のみ, 2:すべて
     * @param int $change 0:内容変更ありのみ, 1:変更なしのみ, 2:すべて
     * @param string $since 消えた日の開始 YYYY-MM-DD（検証済み・空文字なら条件なし）
     * @param string $until 消えた日の終了 YYYY-MM-DD（同上）
     * @param int $dmin 消えていた期間の下限（時間・0なら条件なし）
     * @param int $dmax 消えていた期間の上限（時間・0なら条件なし）
     * @param string $now 未掲載中の経過時間を数える基準時刻（毎時クロールの最新時刻）
     * @param list<string> $items 変更内容で絞り込むキー（空配列なら条件なし・AND 条件）
     * @return RankingBanPageDto|null ページ範囲外なら null
     */
    public function getAllOrderByDateTime(int $change, int $publish, int $percent, string $keyword, int $pageNumber, int $limit, string $since = '', string $until = '', int $dmin = 0, int $dmax = 0, string $now = '', array $items = []): ?RankingBanPageDto
    {
        // 上限を超える深いページは件数取得すら走らせず範囲外として返す（重い OFFSET から DB を守る）
        if ($pageNumber > self::MAX_PAGE_NUMBER) {
            return null;
        }

        $labelArray = $this->rankingBanPageRepository->findAllDatetimeColumn($change, $publish, $percent, $keyword, $since, $until, $dmin, $dmax, $now, $items);

        // ページの最大数を取得する（実データの最大ページと上限の小さい方で頭打ち）
        $totalRecords = count($labelArray);
        $maxPageNumber = min($this->calcMaxPages($totalRecords, $limit), self::MAX_PAGE_NUMBER);
        if ($pageNumber > $maxPageNumber) {
            // 現在のページ番号が最大ページ番号を超えている場合
            return null;
        }

        // リストを取得する
        $list = $this->rankingBanPageRepository->findAllOrderByIdDesc(
            $change,
            $publish,
            $percent,
            $keyword,
            $this->calcOffset($pageNumber, $limit),
            $limit,
            $since,
            $until,
            $dmin,
            $dmax,
            $now,
            $items
        );

        $openChatList = array_map(function ($oc) {
            if (!$oc['update_items']) return $oc;
            $oc['update_items'] = array_keys(
                array_filter(json_decode($oc['update_items'], true))
            );
            return $oc;
        }, $list);

        return new RankingBanPageDto(
            pageNumber: $pageNumber,
            maxPageNumber: $maxPageNumber,
            openChatList: $openChatList,
            totalRecords: $totalRecords,
            labelArray: $labelArray,
        );
    }
}
