<?php

namespace App\Models\ApiRepositories;

class OpenChatListDto
{
    const DESC_LEN = 170;

    function __construct(array $oc)
    {
        $this->id = $oc['id'];
        $this->name = $oc['name'];
        $this->desc = $oc['description'];
        $this->member = $oc['member'];
        $this->img = $oc['img_url'];
        $this->emblem = $oc['emblem'] ?? 0;
        $this->joinMethodType = $oc['join_method_type'] ?? 0;
        $this->category = $oc['emblem'] === null ? -1 : ($oc['category'] ?? 0);

        if (isset($oc['api_created_at'])) {
            //$this->createdAt = convertDatetime($oc['api_created_at']);
        }

        if (isset($oc['diff_member'])) {
            if ($oc['diff_member'] === 0) {
                $this->increasedMember = '±0';
            } else {
                $this->increasedMember = signedNumF($oc['diff_member']);
                $this->symbolIncrease = ($oc['diff_member'] > 0) ? 'positive' : 'negative';
                $this->percentageIncrease = signedNum(signedCeil($oc['percent_increase'] * 10) / 10) . '%';
            }
        }

        if (isset($oc['totalCount'])) {
            $this->totalCount = $oc['totalCount'];
        }
    }

    public int $id;
    public string $name;
    public string $desc;
    public int $member;
    public string $img;
    public int $emblem; // 0 なし, 1 スペシャル, 2 公認
    public int $joinMethodType;
    public int $category;
    public ?int $totalCount;
    public ?string $increasedMember;
    public ?string $symbolIncrease;
    public ?string $percentageIncrease;
    public ?string $createdAt;

    public ?string $dailyIncreasedMember;
    public ?string $dailySymbolIncrease;
    public ?string $dailyPercentageIncrease;

    public ?string $weeklyIncreasedMember;
    public ?string $weeklySymbolIncrease;
    public ?string $weeklyPercentageIncrease;
}
