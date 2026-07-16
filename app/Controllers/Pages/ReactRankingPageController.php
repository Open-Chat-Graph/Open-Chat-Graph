<?php

declare(strict_types=1);

namespace App\Controllers\Pages;

use App\Config\AppConfig;
use App\Services\StaticData\StaticDataFile;
use App\Views\Schema\PageBreadcrumbsListSchema;
use Shadow\Kernel\Reception;
use Shared\MimimalCmsConfig;
use App\Services\Seo\SeoLinks;
use App\Services\Seo\RankingSchemaBuilder;
use App\Models\PublicApi\PublicResourceRepositoryInterface;
use App\Services\PublicApi\PublicResourceFactory;

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
        PublicResourceRepositoryInterface $publicRepository,
        PublicResourceFactory $resourceFactory,
        RankingSchemaBuilder $schemaBuilder,
        Reception $reception,
        int $category
    ) {
        $keyword = (string) $reception->input('keyword', '');
        if (str_starts_with($keyword, 'tag:') && strlen($keyword) > 4) {
            return redirect('recommend/' . urlencode(substr($keyword, 4)), 301);
        }

        $legacyReact = AppConfig::$legacyRankingReactEnabled;
        $_css = $legacyReact ? [
            'style/react/OpenChat.css',
            'style/react/OpenChatList.css',
            'style/react/SiteHeader.css',
            getFilePath('js/react', 'main-*.css'),
        ] : [
            'style/base/mvp.css',
            'style/base/unset.css',
            'style/components/site_header.css',
            'style/components/site_footer.css',
            'style/pages/ranking_ssr.css',
        ];

        $_js = $legacyReact ? getFilePath('js/react', 'main-*.js') : null;

        $canonical = url('ranking') . ($category ? '/' . $category : '');
        $heading = $this->buildTitle($reception);
        $description = t('LINEオープンチャットの人数と増加数を毎時集計し、同じ基準で比較した最新ランキングです。');
        $meta = meta()
            ->setTitle($heading)
            ->setDescription($description)
            ->setOgpDescription($description)
            ->setCanonicalUrl($canonical);
        $_meta = $meta->generateTags();
        $noindex = $keyword !== '' || (string)$reception->input('sub_category', '') !== '';
        $hreflang = $category === 0 && !$noindex ? SeoLinks::localeAlternates('ranking') : [];

        $_argDto = null;
        if ($legacyReact) {
            $_argDto = $staticDataFile->getRankingArgDto();
            $_argDto->baseUrl = url();
        }

        $page = max(0, (int)$reception->input('page', 0));
        $limit = 20;
        $offset = $page * $limit;
        $snapshot = $publicRepository->latestUpdatedAt();
        $period = match ((string)$reception->input('list', 'all')) {
            'hourly' => 'hour',
            'daily' => 'day',
            'weekly' => 'week',
            default => 'members',
        };
        $search = $keyword !== '' ? $keyword : null;
        $rows = $search !== null
            ? $publicRepository->listRooms($limit, $offset, $search, $snapshot)
            : $publicRepository->listRankings($period, $category, $limit, $offset, $snapshot);
        $total = $search !== null
            ? $publicRepository->countRooms($search, $snapshot)
            : $publicRepository->countRankings($period, $category, $snapshot);
        $_ssrRooms = [];
        foreach ($rows as $index => $row) {
            $change = isset($row['ranking_change']) ? (int)$row['ranking_change'] : null;
            $row['change_1h'] = $period === 'hour' ? $change : null;
            $row['change_24h'] = $period === 'day' ? $change : null;
            $row['change_7d'] = $period === 'week' ? $change : null;
            $_ssrRooms[] = [
                'position' => $offset + $index + 1,
                'room' => $resourceFactory->room($row, $snapshot),
                'change' => $change,
            ];
        }
        $_updatedAt = new \DateTimeImmutable($snapshot);
        $_total = $total;
        $_page = $page;
        $_period = $period;
        $_schema = $schemaBuilder->build($canonical, $heading, $description, $snapshot, $_ssrRooms);

        $nextQuery = array_filter([
            'list' => (string)$reception->input('list', 'all'),
            'keyword' => $keyword,
            'sub_category' => (string)$reception->input('sub_category', ''),
            'page' => $page + 1,
        ], static fn($value) => $value !== '' && $value !== null);
        $_nextUrl = $offset + count($rows) < $total
            ? $canonical . '?' . http_build_query($nextQuery, '', '&', PHP_QUERY_RFC3986)
            : null;

        $_breadcrumbsShema = $breadcrumbsShema->generateSchema(
            t('ランキング'),
            $category ? 'ranking' : '',
            $category ? getCategoryName($category) : '',
        );

        return view('ranking_react_content', compact(
            '_css', '_js', '_meta', '_argDto', '_breadcrumbsShema', '_schema', '_ssrRooms', '_updatedAt',
            '_total', '_page', '_period', '_nextUrl', 'heading', 'description', 'category', 'canonical',
            'noindex', 'hreflang', 'legacyReact'
        ));
    }
}
