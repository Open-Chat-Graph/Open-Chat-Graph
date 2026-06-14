<?php

declare(strict_types=1);

namespace App\Models\Repositories;

/**
 * ルーム個別ページの分析文(narrative)事前計算データの書き込み。
 *
 * 読み取りは getOpenChatByIdWithTag() の oc_page_cache JOIN に一本化したため、
 * このリポジトリは背景バッチからの一括 upsert（書き込み）専用。
 */
interface OcPageCacheRepositoryInterface
{
    /**
     * 部屋単位の分析データを一括 upsert する（背景バッチ専用・単一プロセス直列書き込み）。
     *
     * chart_meta はグラフ初回ロードの可用性メタ JSON（生成不可なら null = 列も NULL）。
     *
     * @param array<array{open_chat_id: int, narrative_data: string, chart_meta?: string|null}> $rows
     */
    public function upsertMany(array $rows): void;
}
