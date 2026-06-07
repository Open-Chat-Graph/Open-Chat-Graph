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

    /** 最新24時間タブに表示できる毎時メンバー数データが存在するか */
    public bool $hourAvailability = false;

    /**
     * 期間タブ×ランキング種別(ranking/rising)×カテゴリ(in/all)毎に順位データが存在するか
     *
     * @var array<'hour'|'week'|'month'|'all', array{ ranking_in: bool, ranking_all: bool, rising_in: bool, rising_all: bool }>
     */
    public array $positionAvailability = [
        'hour' => self::POSITION_NONE,
        'week' => self::POSITION_NONE,
        'month' => self::POSITION_NONE,
        'all' => self::POSITION_NONE,
    ];

    private const POSITION_NONE = [
        'ranking_in' => false,
        'ranking_all' => false,
        'rising_in' => false,
        'rising_all' => false,
    ];

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
