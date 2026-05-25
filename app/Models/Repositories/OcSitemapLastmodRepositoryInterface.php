<?php

declare(strict_types=1);

namespace App\Models\Repositories;

/**
 * /oc/{id} の sitemap <lastmod> 専用テーブル oc_sitemap_lastmod を管理する Interface。
 *
 * open_chat.updated_at はメタ情報 (タイトル/説明/ステータス) 変更時しか動かず、
 * 人数変化を反映しない。本テーブルは「ページ内容が実際に変わった日」を独立に保持し、
 * Google に正確な再クロールヒントを与える。
 */
interface OcSitemapLastmodRepositoryInterface
{
    /**
     * lastmod を最新化する (日次バッチ想定)。
     *
     * 対象 (= ページ内容が significant に変わった room):
     *  - oc_sitemap_lastmod に未登録
     *  - open_chat.updated_at がレコードの lastmod より新しい (メタ情報変更)
     *  - 人数が前回 snapshot から significant に変化 (LastmodPolicy と一致する閾値)
     *
     * @return int upsert された行数
     */
    public function refreshLastmod(): int;
}
