<?php

declare(strict_types=1);

namespace App\Controllers\Pages;

use App\Services\StaticData\StaticDataFile;

/**
 * 詳細成長分析ページ（/analysis）。ランキングと同じ React バンドルを配信し、
 * React Router が URL を見て AnalysisPage を描画する（2 つ目のビルドは不要）。
 *
 * 専門ユーザー向け・重いクエリのため noindex（X-Robots-Tag）。arg-dto は
 * ランキングと同一形（カテゴリ等）を渡す（同一 config.ts が読むため）。
 */
class ReactAnalysisPageController
{
    public function index(StaticDataFile $staticDataFile)
    {
        // 専門機能・重いクエリ・検索結果ページのため検索避け
        header('X-Robots-Tag: noindex, nofollow');

        $_css = [
            'style/react/OpenChat.css',
            'style/react/OpenChatList.css',
            'style/react/SiteHeader.css',
            getFilePath('js/react', 'main-*.css'),
        ];

        $_js = getFilePath('js/react', 'main-*.js');

        $_meta = meta()
            ->setTitle(t('詳細成長分析'))
            ->generateTags();

        $_argDto = $staticDataFile->getRankingArgDto();
        $_argDto->baseUrl = url();

        return view('analysis_react_content', compact('_css', '_js', '_meta', '_argDto'));
    }
}
