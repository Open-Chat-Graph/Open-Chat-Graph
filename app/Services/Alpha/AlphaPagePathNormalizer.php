<?php

declare(strict_types=1);

namespace App\Services\Alpha;

use App\Config\AppConfig;

/**
 * referrer URL / pagePath を内部の非部屋ページ（トップ '/' / おすすめ '/recommend/{tag}'）に正規化する。
 *
 * AlphaGaClient（GA4 pagePath / GSC page の集計）と
 * AlphaAccessRankingRepository（alpha_page_jump_daily 再計算の referrer 正規化）の
 * 2箇所に重複定義されていた同一ロジックの統合先。
 *
 * 処理: 完全URLなら自サイトホストのみ path 部を抽出（外部ホストは null）→
 * クエリ/フラグメント除去 → ''/'(direct)' は null →
 * /tw・/th ロケール配下は除外 → /oc/{id}・/openchat/{id} の部屋ページは除外 →
 * トップ → /recommend/{tag}。対象外はすべて null（集計から脱落）。
 *
 * ※ 旧2実装からの意図的な挙動変更（いずれも「誤った混入の除去」）:
 * - 入力 ''（GA行の dimensionValues 欠損）: 旧 AlphaGaClient 版はトップ '/' 扱い → null に統一。
 * - 外部サイトのルートURL（https://www.google.com/ 等。referrer として頻出）: 旧2実装とも
 *   ホストを見ずに path '/' を抽出しトップ扱い → 自サイト（AppConfig::$siteDomain）以外の
 *   完全URLは null。外部検索エンジン流入が「トップページ経由の入室」に化けるのを防ぐ。
 */
final class AlphaPagePathNormalizer
{
    /**
     * @return array{path: string, label: string}|null 対象外（部屋ページ・外部・直接など）は null
     */
    public static function normalize(string $raw): ?array
    {
        if ($raw === '' || $raw === '(direct)') {
            return null;
        }

        // 完全URLなら自サイトホストに限り path 部だけ取り出す（外部サイトは対象外）
        $path = $raw;
        if (preg_match('#^https?://([^/]+)(/.*)?$#i', $raw, $m)) {
            $host = strtolower($m[1]);
            $own = strtolower((string)parse_url(AppConfig::$siteDomain, PHP_URL_HOST));
            if ($host !== $own && $host !== 'www.' . $own) {
                return null;
            }
            $path = $m[2] ?? '/';
        }

        // クエリ/フラグメント除去
        $path = preg_replace('/[?#].*$/', '', $path) ?? $path;

        // tw/th ロケール配下は ja 専用αの対象外
        if (preg_match('#^/(?:tw|th)(?:/|$)#', $path)) {
            return null;
        }

        // /oc/{id} や /openchat/{id} の部屋ページは対象外
        if (preg_match('#/(?:oc|openchat)/\d+#', $path)) {
            return null;
        }

        // トップ
        if ($path === '' || $path === '/' || $path === '/index.html') {
            return ['path' => '/', 'label' => 'トップ'];
        }

        // おすすめ /recommend/{tag}（末尾スラッシュ許容）
        if (preg_match('#^/recommend/([^/]+)/?$#', $path, $mm)) {
            $tag = rawurldecode($mm[1]);
            $tag = mb_strlen($tag) > 150 ? mb_substr($tag, 0, 150) : $tag;
            return ['path' => '/recommend/' . $tag, 'label' => $tag];
        }

        return null;
    }
}
