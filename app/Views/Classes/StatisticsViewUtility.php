<?php

declare(strict_types=1);

namespace App\Views;

use App\Services\Statistics\Dto\StatisticsChartDto;

class StatisticsViewUtility
{
    /**
     * @return array{ diff_member: int, percent_increase: float, diff_member2: int, percent_increase2: float }
     */
    function getOcPageArrayElementMemberDiff(StatisticsChartDto $dto, int $memberCount): array
    {
        $result = [
            'diff_member' => null,
            'percent_increase' => null,
            'diff_member2' => null,
            'percent_increase2' => null,
        ];

        $maxIndex = count($dto->member) - 1;
        if ($maxIndex < 1 || !$dto->member[$maxIndex - 1]) {
            return $result;
        }

        [
            'diffNum' => $result['diff_member'],
            'percentIncrease' => $result['percent_increase']
        ] = $this->calculateDiff($memberCount, $dto->member[$maxIndex - 1]);

        if (!isset($dto->member[$maxIndex - 7])) {
            return $result;
        }

        [
            'diffNum' => $result['diff_member2'],
            'percentIncrease' => $result['percent_increase2']
        ] = $this->calculateDiff($memberCount, $dto->member[$maxIndex - 7]);

        return $result;
    }

    /**
     * 最新の数値と前の数値から差分と増加率を計算して返す関数。
     *
     * @param int $latestNum 最新の数値
     * @param int $previousNum 前の数値
     *
     * @return array 連想配列で差分（'diffNum'）と増加率（'percentIncrease'）を含む配列。
     */
    private function calculateDiff(int $latestNum, int $previousNum): array
    {
        // 差分を計算
        $diffNum = $latestNum - $previousNum;

        // 増加率を計算
        $percentIncrease = ($diffNum / $previousNum * 100);

        // 小数点以下6桁までの精度で増加率を丸める
        $percentIncrease = floor($percentIncrease * 1000000) / 1000000;

        // 結果を連想配列で返す
        return compact('diffNum', 'percentIncrease');
    }
}
