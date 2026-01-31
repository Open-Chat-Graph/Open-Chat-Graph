<?php

declare(strict_types=1);

namespace App\Controllers\Pages;

use App\Config\AppConfig;
use App\Services\StaticData\StaticDataFile;
use App\Views\Schema\PageBreadcrumbsListSchema;
use Shadow\Kernel\Reception;
use Shared\MimimalCmsConfig;

class ReactRankingPageController
{
    private function buildTitle(Reception $reception): string
    {
        $category = $reception->input('category');
        $keyword = $reception->input('keyword');
        $subCategory = $reception->input('sub_category');

        $title0 = '';
        switch (!!$keyword) {
            case true:
                $title0 = sprintfT('「%s」の検索結果', $keyword) . "｜";
                break;
            default:
                $title0 = '';
        }

        $title1 = '';
        switch (!!$category) {
            case true:
                $title1 = getCategoryName($category) . '｜';
                break;
            default:
                $title1 = $title0 ? '' : t('【最新】');
        }

        $title3 = '';
        switch (!!$subCategory) {
            case true:
                $title3 = $subCategory . '｜';
                break;
            default:
                $title3 = '';
        }

        $title2 = '';
        switch ($reception->input('list')) {
            case 'weekly':
                $title2 = t('人数増加・1週間');
                break;
            case 'daily':
                $title2 = t('人数増加・24時間');
                break;
            case 'hourly':
                $title2 = t('人数増加・1時間');
                break;
            case 'all':
                $title2 = t('参加人数のランキング');
                break;
            case 'ranking':
                $title2 = '公式ランキング(1時間前)';
                break;
            case 'rising':
                $title2 = '公式急上昇(1時間前)';
        }

        return $title0 . $title1 . $title3 . $title2;
    }

    function ranking(
        StaticDataFile $staticDataFile,
        PageBreadcrumbsListSchema $breadcrumbsShema,
        Reception $reception,
        int $category
    ) {
        $_css = [
            'style/react/OpenChat.css',
            'style/react/OpenChatList.css',
            'style/react/SiteHeader.css',
            getFilePath('style/react', 'main.*.css')
        ];

        $_js = getFilePath('js/react', 'main.*.js');

        $_meta = meta()
            ->setTitle($this->buildTitle($reception))
            ->generateTags();

        $_argDto = $staticDataFile->getRankingArgDto();
        $_argDto->baseUrl = url();

        $_breadcrumbsShema = $breadcrumbsShema->generateSchema(
            t('ランキング'),
            $category ? 'ranking' : '',
            $category ? getCategoryName($category) : '',
        );

        return etag(view('ranking_react_content', compact('_css', '_js', '_meta', '_argDto', '_breadcrumbsShema', 'category')));
    }
}
