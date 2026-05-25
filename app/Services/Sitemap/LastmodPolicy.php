<?php

declare(strict_types=1);

namespace App\Services\Sitemap;

/**
 * sitemap の <lastmod> を「ページ内容が significant に変わった日」に保つためのポリシー。
 *
 * /oc/{id} ページの主役コンテンツは現在人数・推移・narrative であり、これらは人数で変わる。
 * しかし trivial な人数の揺れ (±数人) で lastmod を毎回 bump すると、Google は lastmod 全体を
 * 信用しなくなり無視する。そこで「前回 bump 時の人数からの累積変化」が一定の閾値を超えたときだけ
 * significant とみなす。
 *
 * 閾値 = max( 前回人数の 1%, 5 人 )
 *   - 相対 1%: 大規模ルームの軽微な揺れを無視 (例 9000 人 → 90 人未満は無視)
 *   - 絶対下限 5 人: 小規模ルームのノイズを無視 (例 50 人 → ±4 は無視, +5 で bump)
 *
 * NOTE: この閾値式は OcSitemapLastmodRepository の set-based SQL
 *       `GREATEST(CEILING(member_snapshot * 0.01), 5)` と必ず一致させること。
 *       本クラスが仕様の source of truth。
 */
final class LastmodPolicy
{
    /** 相対閾値 (前回人数に対する割合) */
    public const RELATIVE_RATIO = 0.01;

    /** 絶対下限 (人) */
    public const ABSOLUTE_FLOOR = 5;

    /**
     * significant とみなす最小変化量 (人)。
     */
    public static function significanceThreshold(int $snapshot): int
    {
        return max((int)ceil($snapshot * self::RELATIVE_RATIO), self::ABSOLUTE_FLOOR);
    }

    /**
     * 前回 bump 時の人数 $snapshot から現在 $current への変化が significant か。
     * 増加・減少どちらも内容変化として扱う (ABS)。
     */
    public static function isSignificantMemberChange(int $snapshot, int $current): bool
    {
        return abs($current - $snapshot) >= self::significanceThreshold($snapshot);
    }
}
