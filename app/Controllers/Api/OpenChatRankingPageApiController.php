<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Config\AppConfig;
use App\Models\ApiRepositories\OpenChatStatsRankingApiRepository;
use App\Models\ApiRepositories\OpenChatApiArgs;
use App\Models\ApiRepositories\OpenChatOfficialRankingApiRepository;
use App\Services\OpenChat\Enum\RankingType;
use Shared\Exceptions\BadRequestException as HTTP400;
use Shadow\Kernel\Reception as Recp;
use Shadow\Kernel\Validator as Valid;
use Shared\MimimalCmsConfig;

class OpenChatRankingPageApiController
{
    function __construct(
        private OpenChatApiArgs $args
    ) {
        $this->validateInputs();
    }

    private function validateInputs()
    {
        $error = HTTP400::class;
        Recp::$isJson = true;

        $this->args->page = Valid::num(Recp::input('page', 0), min: 0, e: $error);
        $this->args->limit = Valid::num(Recp::input('limit'), min: 1, max: 20, e: $error);
        $this->args->category = (int)Valid::str(Recp::input('category', '0'), regex: AppConfig::OPEN_CHAT_CATEGORY[MimimalCmsConfig::$urlRoot], e: $error);

        $this->args->list = Valid::str(Recp::input('list', 'daily'), regex: ['hourly', 'daily', 'weekly', 'all', 'ranking', 'rising'], e: $error);
        $this->args->order = Valid::str(Recp::input('order', 'asc'), regex: ['asc', 'desc'], e: $error);
        $this->args->sort = Valid::str(Recp::input('sort', 'rank'), regex: ['rank', 'increase', 'rate', 'member', 'created_at'], e: $error);

        $this->args->sub_category = Valid::str(Recp::input('sub_category', ''), emptyAble: true, maxLen: 40, e: $error);

        $keyword = Valid::str(Recp::input('keyword', ''), emptyAble: true, maxLen: 1000, e: $error);
        if ($keyword && str_starts_with($keyword, 'tag:')) {
            $this->args->tag = str_replace('tag:', '', $keyword);
        } elseif ($keyword && str_starts_with($keyword, 'badge:')) {
            $this->args->badge = $this->validateBadge(str_replace('badge:', '', $keyword));
            $this->args->keyword = $keyword;
        } elseif ($keyword) {
            $this->args->keyword = $keyword;
        }
    }

    private function validateBadge(string $word)
    {
        if ($word === AppConfig::OFFICIAL_EMBLEMS[MimimalCmsConfig::$urlRoot][1]) {
            return 1;
        } elseif ($word === AppConfig::OFFICIAL_EMBLEMS[MimimalCmsConfig::$urlRoot][2]) {
            return 2;
        } elseif ($word === AppConfig::OFFICIAL_EMBLEMS[MimimalCmsConfig::$urlRoot][3]) {
            return 3;
        } else {
            return 0;
        }
    }

    private function officialRanking(RankingType $type)
    {
        /** @var OpenChatOfficialRankingApiRepository $repo */
        $repo = app(OpenChatOfficialRankingApiRepository::class);

        $time = new \DateTime(getHouryUpdateTime());
        $time->modify('-1hour');
        $timeStr = $time->format('Y-m-d H:i:s');

        return response($repo->findOfficialRanking($this->args, $type, $timeStr));
    }

    function index(OpenChatStatsRankingApiRepository $repo)
    {
        switch ($this->args->list) {
            case 'hourly':
                return response($repo->findHourlyStatsRanking($this->args));
            case 'daily':
                return response($repo->findDailyStatsRanking($this->args));
            case 'weekly':
                return response($repo->findWeeklyStatsRanking($this->args));
            case 'all':
                return response($repo->findStatsAll($this->args));
            case 'ranking':
                return $this->officialRanking(RankingType::Ranking);
            case 'rising':
                return $this->officialRanking(RankingType::Rising);
        }
    }
}
