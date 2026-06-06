<?php

declare(strict_types=1);

namespace App\Services\Alpha;

/**
 * referrer URL / pagePath を内部の非部屋ページ（トップ '/' / おすすめ '/recommend/{tag}'）に正規化する。
 *
 * AlphaGaClient（GA4 pagePath / GSC page の集計）と
 * AlphaAccessRankingRepository（alpha_page_jump_daily 再計算の referrer 正規化）の
 * 2箇所に重複定義されていた同一ロジックの統合先。
 *
 * 処理: 完全URLなら path 部を抽出 → クエリ/フラグメント除去 → ''/'(direct)' は null →
 * /tw・/th ロケール配下は除外 → /oc/{id}・/openchat/{id} の部屋ページは除外 →
 * トップ → /recommend/{tag}。対象外はすべて null（集計から脱落）。
 *
 * ※ 意図的な挙動変更: 旧 AlphaGaClient::normalizePageScopePath は入力 ''（空文字。
 * GA行の dimensionValues 欠損が '' になるケース）をトップ '/' として返していたが、
 * 本クラスでは null（対象外として脱落）に統一した。欠損行がトップに混入するより
 * 脱落させる方が正しいため。旧 Repository 版（null）と同じ挙動。
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

        // 完全URLなら path 部だけ取り出す
        $path = $raw;
        if (preg_match('#^https?://[^/]+(/.*)$#', $raw, $m)) {
            $path = $m[1];
        } elseif (preg_match('#^https?://[^/]+$#', $raw)) {
            $path = '/';
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
