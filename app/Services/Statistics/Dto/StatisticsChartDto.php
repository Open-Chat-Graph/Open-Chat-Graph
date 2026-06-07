<?php

declare(strict_types=1);

namespace App\Services\Statistics\Dto;

class StatisticsChartDto
{
    /** @var string[] Y-m-d */
    public array $date = [];

    /** @var (int|null)[] */
    public array $member = [];

    public string $startDate = '';

    public string $endDate = '';

    /**
     * 期間タブ毎にローソク足(OHLC)データが存在するか
     *
     * @var array{ week: bool, month: bool, all: bool }
     */
    public array $ohlcAvailability = ['week' => false, 'month' => false, 'all' => false];

    function __construct(string $startDate, string $endDate)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    function addValue(string $date, int|null $member)
    {
        $this->date[] = $date;
        $this->member[] = $member;
    }
}
