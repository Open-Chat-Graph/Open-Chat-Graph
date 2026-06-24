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

        // ページャのラベルは各ページ境界の日付だけ必要。表示できるのは上限ページまでなので、
        // 全件ではなく「上限ページ分＋1件」だけ取得する。+1 件取れたら総数が上限超え（正確な総数は不要）と分かる。
        // これで従来の「全マッチ行を取得して件数を数える」重い処理（全件 filesort＋転送）を避ける。
        $labelLimit = self::MAX_PAGE_NUMBER * $limit + 1;
        $labelArray = $this->rankingBanPageRepository->findAllDatetimeColumn($change, $publish, $percent, $keyword, $labelLimit, $since, $until, $dmin, $dmax, $now, $items);

        if ($labelArray === []) {
            // 0件（コントローラが空状態を描画する）
            return null;
        }

        $hasMore = count($labelArray) > self::MAX_PAGE_NUMBER * $limit;
        if ($hasMore) {
            // 判定用の +1 件目を捨て、境界配列の長さを「上限ページ×件数」ぴったりに揃える
            array_pop($labelArray);
        }

        // 上限超えなら総数は表示上限（「N件以上」表示）、上限以下なら取得件数が正確な総数
        $totalRecords = $hasMore ? self::MAX_PAGE_NUMBER * $limit : count($labelArray);
        $maxPageNumber = $hasMore ? self::MAX_PAGE_NUMBER : $this->calcMaxPages($totalRecords, $limit);
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
            hasMore: $hasMore,
        );
    }
}
