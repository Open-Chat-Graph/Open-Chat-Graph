<?php

declare(strict_types=1);

namespace App\Controllers\Pages;

use App\Models\PublicApi\PublicResourceRepositoryInterface;
use App\Services\PublicApi\PublicResourceFactory;

final class MonthlyReportPageController
{
    public function index(
        PublicResourceRepositoryInterface $repository,
        PublicResourceFactory $factory,
        string $month,
    ) {
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) return false;

        $snapshot = $repository->latestUpdatedAt();
        if ((new \DateTimeImmutable($snapshot))->format('Y-m') !== $month) return false;

        $stats = $repository->getSiteStats();
        $themes = array_map(fn(array $row) => $factory->theme($row)->jsonSerialize(), $repository->listThemes(10, 0, $snapshot));
        $rankingRows = $repository->listRankings('day', 0, 10, 0, $snapshot);
        $rankings = array_map(fn(array $row) => $factory->room($row, $snapshot)->jsonSerialize(), $rankingRows);

        $canonical = url('reports/' . $month);
        $_meta = meta()
            ->setTitle($month . ' オープンチャット月次データレポート')
            ->setDescription($month . 'のLINEオープンチャット掲載数、合計メンバー数、成長テーマと急成長ルームを独自観測データから集計した月次レポートです。')
            ->setOgpDescription($month . ' オープンチャット月次データレポート')
            ->setCanonicalUrl($canonical);
        $_css = ['components/site_header', 'components/site_footer', 'pages/terms'];

        $schema = [
            '@context' => 'https://schema.org', '@type' => 'Dataset',
            'name' => $month . ' オープンチャット月次データレポート',
            'url' => $canonical, 'dateModified' => PublicResourceFactory::dateToRfc3339($snapshot),
            'temporalCoverage' => $month, 'creator' => ['@type' => 'Organization', 'name' => 'オプチャグラフ', 'url' => rtrim(url(), '/')],
            'variableMeasured' => ['掲載ルーム数', '合計メンバー数', '新規ルーム数', 'テーマ別人数', '24時間メンバー増減'],
        ];
        $_schema = '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . '</script>';

        return view('monthly_report_content', compact('_meta', '_css', '_schema', 'canonical', 'month', 'snapshot', 'stats', 'themes', 'rankings'));
    }
}
