<?php

declare(strict_types=1);

namespace App\Services\Recommend;

use App\Views\Classes\CollapseKeywordEnumerations;

/**
 * おすすめリスト行の共通フォーマット。
 *
 * .dat 一括生成もページ表示時の即時生成も同じ RecommendRankingBuilder を通り、
 * テンプレート(open_chat_list_recommend)が描画に使う最小フィールドだけの同一形式の行を
 * 出力する。行の形を変えるときはここだけを変える。
 */
final class RecommendRowFormat
{
    /**
     * @param array $row open_chat 行（name / description / img_url 等を含む生データ）
     * @param string $tableName ランキング由来テーブル名（伸び部屋バッジ・リンクの ?limit=hour 判定用）
     * @param ?int $diff24h 伸び部屋のみ24h増を持たせる（裾は null＝バッジ非表示）
     */
    public static function slim(array $row, string $tableName, ?int $diff24h): array
    {
        return [
            'id' => $row['id'],
            'name' => $row['name'],
            'img_url' => $row['img_url'],
            'member' => $row['member'],
            'emblem' => $row['emblem'],
            'api_created_at' => $row['api_created_at'],
            'join_method_type' => $row['join_method_type'],
            'table_name' => $tableName,
            'diff_member_24h' => $diff24h,
            'desc40' => self::buildDisplayDescription((string)($row['description'] ?? ''), (string)$row['name']),
        ];
    }

    /**
     * リスト表示用の説明文。テンプレートが描画ごとに行っていた
     * 「キーワード列挙の collapse + 40字 truncate」を生成時の1回に移したもの。
     * description 原文(最大1000字)は行に保存しない。
     */
    public static function buildDisplayDescription(string $description, string $name): string
    {
        $collapsedDesc = CollapseKeywordEnumerations::collapse(
            htmlspecialchars_decode($description),
            extraText: htmlspecialchars_decode($name)
        );
        return mb_strlen($collapsedDesc) > 40 ? mb_substr($collapsedDesc, 0, 40) . '…' : $collapsedDesc;
    }
}
