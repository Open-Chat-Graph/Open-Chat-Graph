<?php

declare(strict_types=1);

namespace App\Models\Repositories;

/**
 * メンバー数変動フィルターキャッシュのリポジトリインターフェース
 *
 * 統計データから「メンバー数が変動している部屋」を抽出した結果をキャッシュし、
 * 日次処理の中断・再開時に重複クエリを防止する
 *
 * ## 3つのデータ
 * 1. 変動がある部屋（過去8日間でメンバー数変動）
 * 2. レコード数が8以下の部屋（新規部屋）
 * 3. 最後のレコードが1週間以上前の部屋（週次更新用）
 *
 * ## 使い分け
 * - hourly: 1（キャッシュ） + 2（リアルタイム） → getForHourly()
 * - daily:  1 + 2 + 3（全部キャッシュ）       → getForDaily()
 */
interface MemberChangeFilterCacheRepositoryInterface
{
    /**
     * 毎時処理用: 変動がある部屋（キャッシュ） + 新規部屋（リアルタイム）
     *
     * @param string $date 日付（Y-m-d形式）
     * @return int[] オープンチャットIDの配列
     */
    function getForHourly(string $date): array;

    /**
     * 日次処理用: 変動がある部屋 + 新規部屋 + 週次更新部屋（全部キャッシュ）
     *
     * 2回目以降は全てキャッシュから取得
     *
     * @param string $date 日付（Y-m-d形式）
     * @return int[] オープンチャットIDの配列
     */
    function getForDaily(string $date): array;
}
