<?php

declare(strict_types=1);

namespace App\Services\Alpha;

use App\Services\Crawler\Config\OpenChatCrawlerConfigInterface;
use App\Services\Crawler\FileDownloader;

/**
 * LINE公式のオープンチャット検索APIを叩くクライアント（Alpha通知用）。
 *
 * エンドポイント:
 *   https://openchat.line.me/api/square/search?query=<kw>&limit=20
 *
 * レスポンス形（実測）:
 *   { "squares": [ { "square": {
 *       "emid": "...", "name": "...", "desc": "...",
 *       "profileImageObsHash": "...", "emblems": [], "joinMethodType": 1, "badges": []
 *   } }, ... ], "continuationToken": "...", "showNewForOneMember": bool }
 *
 * 注意: square には memberCount が含まれない（実測）。人数は別途 open_chat 等から補完する。
 * 既存クローラと同じ UA（OpenChatStatsbot 入り）を流用する。
 */
class AlphaKeywordSearchClient
{
    private const SEARCH_URL = 'https://openchat.line.me/api/square/search';

    public function __construct(
        private FileDownloader $downloader,
        private OpenChatCrawlerConfigInterface $config,
    ) {
    }

    /**
     * キーワードで検索し、ヒットした square 配列を返す。
     * 失敗時（404・JSON不正・例外）は空配列を返す（毎時処理を止めない）。
     *
     * @return array<int, array{emid:string, name:string, desc:string, profileImageObsHash:string, joinMethodType:int}>
     */
    public function search(string $keyword, int $limit = 20): array
    {
        $url = self::SEARCH_URL . '?query=' . rawurlencode($keyword) . '&limit=' . $limit;

        try {
            $body = $this->downloader->downloadFile($url, $this->config->getUserAgent());
        } catch (\Throwable $e) {
            return [];
        }

        if ($body === false) {
            return [];
        }

        $json = json_decode($body, true);
        if (!is_array($json) || !isset($json['squares']) || !is_array($json['squares'])) {
            return [];
        }

        $result = [];
        foreach ($json['squares'] as $entry) {
            $sq = $entry['square'] ?? null;
            if (!is_array($sq) || empty($sq['emid'])) {
                continue;
            }

            $result[] = [
                'emid' => (string)$sq['emid'],
                'name' => (string)($sq['name'] ?? ''),
                'desc' => (string)($sq['desc'] ?? ''),
                'profileImageObsHash' => (string)($sq['profileImageObsHash'] ?? ''),
                'joinMethodType' => (int)($sq['joinMethodType'] ?? 0),
            ];
        }

        return $result;
    }
}
