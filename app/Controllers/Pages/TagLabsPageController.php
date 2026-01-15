<?php

declare(strict_types=1);

namespace App\Controllers\Pages;

use App\Config\AppConfig;
use App\Models\AdsRepositories\AdsRepository;
use App\Services\StaticData\StaticDataFile;
use App\Services\Storage\FileStorageInterface;
use App\Views\Schema\PageBreadcrumbsListSchema;
use Shared\MimimalCmsConfig;

class TagLabsPageController
{
    function index(
        StaticDataFile $staticDataGeneration,
        PageBreadcrumbsListSchema $pageBreadcrumbsListSchema,
        FileStorageInterface $fileStorage,
        ?string $isAdminPage,
    ) {
        $_css = ['room_list', 'site_header', 'site_footer'];
        $_meta = meta()->setTitle('タグで見るトレンド動向')->setDescription('タグによる分類を用いたトレンド分析では、単純ながらも重要な増減数や合計人数の集計を行います。これにより、各トピックの人気度やその変動を捉えることができます。');
        $_meta->image_url = '';
        $_schema = $pageBreadcrumbsListSchema->generateSchema('タグ');

        $tagsGroup = (function ($tagsGroup) {
            $exists = [];
            return array_map(function ($tags) use (&$exists) {
                $result = [];
                foreach ($tags as $tag) {
                    if (!$tag['tag'] || in_array($tag['tag'], $exists)) continue;
                    $exists[] = $tag['tag'];
                    $result[] = $tag;
                }

                return $result;
            }, $tagsGroup);
        })($staticDataGeneration->getTagList());

        $categories = array_flip(AppConfig::OPEN_CHAT_CATEGORY[MimimalCmsConfig::$urlRoot]);
        $_updatedAt = new \DateTime($fileStorage->getContents('@hourlyRealUpdatedAtDatetime'));

        if (isset($isAdminPage) && adminMode()) {
            /** @var AdsRepository $adsRepo */
            $adsRepo = app(AdsRepository::class);
            $adsList = $adsRepo->getAdsListAll();
            $adsTagMap = $adsRepo->getTagMapAll();
        } else {
            $adsList = null;
            $adsTagMap = null;
        }

        return view('tags_content', compact(
            '_meta',
            '_css',
            '_schema',
            'tagsGroup',
            'categories',
            '_updatedAt',
            'isAdminPage',
            'adsList',
            'adsTagMap',
        ));
    }
}
