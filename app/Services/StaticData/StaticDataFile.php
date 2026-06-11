<?php

declare(strict_types=1);

namespace App\Services\StaticData;

use App\Config\AppConfig;
use App\Services\StaticData\Dto\StaticRecommendPageDto;
use App\Services\StaticData\Dto\StaticTopPageDto;
use App\Services\Storage\FileStorageInterface;
use App\Views\Dto\RankingArgDto;

class StaticDataFile
{
    private ?StaticDataGenerator $staticDataGenerator = null;

    public function __construct(
        private FileStorageInterface $fileStorage
    ) {}

    /**
     * 生成器はキャッシュファイル欠損時等のフォールバックでしか使わないため、
     * Webホットパス（ルームページ等で毎リクエスト生成される本クラス）の構築コストを避けて初回利用時にのみ解決する
     */
    private function staticDataGenerator(): StaticDataGenerator
    {
        if ($this->staticDataGenerator === null) {
            /** @var StaticDataGenerator $staticDataGenerator */
            $staticDataGenerator = app(StaticDataGenerator::class);
            $this->staticDataGenerator = $staticDataGenerator;
        }

        return $this->staticDataGenerator;
    }

    private function checkUpdatedAt(string $hourlyUpdatedAt)
    {
        if (!$hourlyUpdatedAt === $this->fileStorage->getContents('@hourlyCronUpdatedAtDatetime'))
            noStore();
    }

    function getTopPageData(): StaticTopPageDto
    {
        $data = $this->fileStorage->getSerializedFile('@topPageRankingData');

        /** @var StaticTopPageDto $data */
        if (!$data || AppConfig::$disableStaticDataFile) {
            return $this->staticDataGenerator()->getTopPageDataFromDB();
        }

        $this->checkUpdatedAt($data->hourlyUpdatedAt->format('Y-m-d H:i:s'));
        return $data;
    }

    function getRankingArgDto(): RankingArgDto
    {
        /** @var RankingArgDto $data */
        $data = $this->fileStorage->getSerializedFile('@rankingArgDto');
        //$data = null;
        if (!$data || AppConfig::$disableStaticDataFile) {
            $data = $this->staticDataGenerator()->getRankingArgDto();
        }

        $this->checkUpdatedAt($data->hourlyUpdatedAt);
        return $data;
    }

    function getRecommendPageDto(): StaticRecommendPageDto
    {
        /** @var StaticRecommendPageDto $data */
        $data = $this->fileStorage->getSerializedFile('@recommendPageDto');
        //$data = null;
        if (!$data || AppConfig::$disableStaticDataFile) {
            $data = $this->staticDataGenerator()->getRecommendPageDto();
        }

        $this->checkUpdatedAt($data->hourlyUpdatedAt);
        return $data;
    }

    /** @return array<int, array<array{tag:string, record_count:int}>> */
    function getTagList(): array
    {
        /** @var array $data */
        $data = $this->fileStorage->getSerializedFile('@tagList');
        if (!$data || AppConfig::$disableStaticDataFile) {
            $data = $this->staticDataGenerator()->getTagList();
        }

        $time = getStorageFileTime($this->fileStorage->getStorageFilePath('tagList'));
        if (!$time || new \DateTime('@' . $time) < new \DateTime($this->fileStorage->getContents('@hourlyCronUpdatedAtDatetime')))
            noStore();

        return $data;
    }

    /**
     * 関連タグマップ（タグ => [関連タグ => 共起スコア]）。RelatedTagsService 参照。
     *
     * @return array<string, array<string, int>>
     */
    function getRelatedTags(): array
    {
        /** @var array|false $data */
        $data = $this->fileStorage->getSerializedFile('@relatedTags');
        if (!$data || AppConfig::$disableStaticDataFile) {
            $data = $this->staticDataGenerator()->getRelatedTags();
        }

        return is_array($data) ? $data : [];
    }
}
