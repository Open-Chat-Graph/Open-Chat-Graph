<?php

declare(strict_types=1);

namespace App\Services\Alpha;

/**
 * 詳細画面のリファラ（参照元URL）解析・表示用整形（純粋 static）。
 *
 * AlphaApiController::roomMetrics に直書きされていた
 * リファラURL解析・検索エンジン判定・本家内部ページ判定の統合先。
 */
final class AlphaReferrerFormatter
{
    /**
     * 「他の部屋（◯◯）」用に、参照元URL群から他部屋IDを拾い出す（名前解決はリポジトリで行う）。
     *
     * @param array<int, array{referrer:string, pageviews:int}> $referrerRows
     * @param int $currentRoomId この詳細ページの部屋ID（自分自身は除外）
     * @return array<int, int> 他部屋IDの配列
     */
    public static function extractOtherRoomIds(array $referrerRows, int $currentRoomId): array
    {
        $otherRoomIds = [];
        foreach ($referrerRows as $rr) {
            if (preg_match('#/(?:oc|openchat)/(\d+)#', (string)$rr['referrer'], $mm)) {
                $rid = (int)$mm[1];
                if ($rid !== $currentRoomId) {
                    $otherRoomIds[] = $rid;
                }
            }
        }
        return $otherRoomIds;
    }

    /**
     * リファラ行を表示用に整形する（label / isInternal を付ける）。
     *
     * isInternal は host が本家ドメイン（SecretsConfig::$gscSiteUrl 由来）かどうか。
     * label は (direct)→「直接・不明」/ 検索エンジン→「検索」/ 本家内部→パスからページ種別 /
     * 外部→ホスト名。
     *
     * @param array{referrer:string, pageviews:int} $r
     * @param int $currentRoomId この詳細ページの部屋ID（自分自身からの参照を判別する）
     * @param array<int, string> $roomNames 他部屋 id => name（「他の部屋（◯◯）」用）
     * @return array{referrer:string, label:string, detail:string, pageviews:int, isInternal:bool}
     *   label  … 一覧の1行に出す短い文言（はみ出す分は省略表示）
     *   detail … タップ/ホバーのチップに出す全文（どこから来たかを明示）
     */
    public static function format(array $r, int $currentRoomId = 0, array $roomNames = []): array
    {
        $referrer = (string)$r['referrer'];
        $pageviews = (int)$r['pageviews'];

        if ($referrer === '(direct)') {
            return [
                'referrer' => $referrer,
                'label' => '直接・不明',
                'detail' => '直接アクセス（ブックマーク／アプリ内／URL直打ち など、参照元なし）',
                'pageviews' => $pageviews,
                'isInternal' => false,
            ];
        }

        $host = (string)(parse_url($referrer, PHP_URL_HOST) ?? '');
        $host = strtolower($host);
        // 先頭の www. は無視して比較する
        $bareHost = preg_replace('/^www\./', '', $host) ?? $host;

        $ownDomain = self::ownDomainHost();
        $isInternal = $ownDomain !== '' && ($bareHost === $ownDomain || str_ends_with($bareHost, '.' . $ownDomain));

        if ($isInternal) {
            $path = (string)(parse_url($referrer, PHP_URL_PATH) ?? '');
            $query = (string)(parse_url($referrer, PHP_URL_QUERY) ?? '');
            [$label, $detail, $isSeoOrigin] = self::internalReferrerLabel($path, $query, $currentRoomId, $roomNames);
            return [
                'referrer' => $referrer,
                'label' => $label,
                'detail' => $detail,
                'pageviews' => $pageviews,
                // SEO経由＝本家内SEOページ経由の間接流入。自己参照(このページ内)は除く。
                'isInternal' => $isSeoOrigin,
            ];
        }

        // 検索エンジン判定（host ベース）
        $engine = self::searchEngineName($bareHost);
        if ($engine !== '') {
            return [
                'referrer' => $referrer,
                'label' => $engine . '検索',
                'detail' => $engine . '検索からの流入（外部）',
                'pageviews' => $pageviews,
                'isInternal' => false,
            ];
        }

        // それ以外の外部はホスト名（取れなければ生 referrer）。チップには元URLを出す。
        return [
            'referrer' => $referrer,
            'label' => $bareHost !== '' ? $bareHost : $referrer,
            'detail' => '外部サイトからの流入: ' . $referrer,
            'pageviews' => $pageviews,
            'isInternal' => false,
        ];
    }

    /**
     * 本家ドメインのホスト名を SecretsConfig::$gscSiteUrl から取り出す（ハードコードしない）。
     * 例 'sc-domain:openchat-review.me' / 'https://openchat-review.me/' → 'openchat-review.me'。
     * 設定が空なら ''（その場合 isInternal は常に false）。
     */
    private static function ownDomainHost(): string
    {
        $site = trim(\App\Config\SecretsConfig::$gscSiteUrl);
        if ($site === '') {
            return '';
        }
        // sc-domain:example.com 形式
        if (str_starts_with($site, 'sc-domain:')) {
            $host = substr($site, strlen('sc-domain:'));
        } else {
            // URLプレフィックス形式 https://example.com/
            $parsed = parse_url($site, PHP_URL_HOST);
            $host = $parsed !== null && $parsed !== false ? $parsed : $site;
        }
        $host = strtolower(trim($host));
        return preg_replace('/^www\./', '', $host) ?? $host;
    }

    /**
     * 検索エンジン名を返す（該当しなければ ''）。Google / Yahoo / Bing 等。
     */
    private static function searchEngineName(string $host): string
    {
        if ($host === '') {
            return '';
        }
        $map = [
            'google.' => 'Google',
            'bing.' => 'Bing',
            'yahoo.' => 'Yahoo!',
            'duckduckgo.' => 'DuckDuckGo',
            'baidu.' => 'Baidu',
            'yandex.' => 'Yandex',
            'ecosia.' => 'Ecosia',
            'naver.' => 'Naver',
        ];
        foreach ($map as $needle => $name) {
            if (str_contains($host, $needle)) {
                return $name;
            }
        }
        return '';
    }

    /**
     * 本家(openchat-review.me)内リファラの path/query から「どのページから来たか」を
     * 人間可読に整形する。本家のページ種別は限られるので各パターンを文言化する。
     *
     * @param array<int, string> $roomNames 他部屋 id => name
     * @return array{0:string, 1:string, 2:bool} [一覧用の短ラベル, チップ用の全文, SEO経由(間接流入)とみなすか]
     *   第3要素 false ＝ 自己参照「このページ内」（再読込/グラフ操作。SEO経由バッジを出さない）。
     */
    private static function internalReferrerLabel(string $path, string $query, int $currentRoomId = 0, array $roomNames = []): array
    {
        // 末尾スラッシュを正規化（'/ranking/' と '/ranking' を同一視）
        $p = $path === '' ? '/' : rtrim($path, '/');
        if ($p === '') {
            $p = '/';
        }
        $params = [];
        if ($query !== '') {
            parse_str($query, $params);
        }
        $keyword = isset($params['keyword']) ? trim((string)$params['keyword']) : '';

        // トップ
        if ($p === '/') {
            return ['トップページ', 'オプチャグラフのトップページ', true];
        }
        // ランキング（検索結果＝キーワード付き、カテゴリ別、急上昇）
        if ($p === '/ranking' || str_starts_with($p, '/ranking/')) {
            // keyword=tag:◯◯ はおすすめタグからの遷移（検索ではなくタグ）。1行にタグ名も出す。
            if (str_starts_with($keyword, 'tag:')) {
                $tag = trim(substr($keyword, 4));
                if ($tag !== '') {
                    return ['おすすめ（' . $tag . '）', 'おすすめタグ「' . $tag . '」', true];
                }
            }
            if ($keyword !== '') {
                return ['検索結果「' . $keyword . '」', '検索結果「' . $keyword . '」', true];
            }
            if (preg_match('#^/ranking/(.+)$#u', $p, $m)) {
                $cat = urldecode($m[1]);
                return ['ランキング（' . $cat . '）', 'ランキング（カテゴリ: ' . $cat . '）', true];
            }
            return ['ランキング', '急上昇ランキング', true];
        }
        if ($p === '/official-ranking' || str_starts_with($p, '/official-ranking/')) {
            return ['公式ランキング', '公式ランキング', true];
        }
        // おすすめ（タグ別＝1行にタグ名も／一覧）
        if (preg_match('#^/recommend/(.+)$#u', $p, $m)) {
            $tag = urldecode($m[1]);
            return ['おすすめ（' . $tag . '）', 'おすすめタグ「' . $tag . '」', true];
        }
        if ($p === '/recommend') {
            return ['おすすめ', 'おすすめタグ一覧', true];
        }
        // 部屋詳細（自分自身＝再訪/グラフ操作 と 他の部屋 を区別する。自己参照は SEO経由ではない）
        if (preg_match('#^/(?:oc|openchat)/(\d+)#', $p, $m)) {
            $rid = (int)$m[1];
            if ($currentRoomId > 0 && $rid === $currentRoomId) {
                return ['このページ内', 'この部屋のページ内（再読み込み・グラフ操作など）', false];
            }
            $name = $roomNames[$rid] ?? '';
            if ($name !== '') {
                return ['他の部屋（' . $name . '）', '他の部屋「' . $name . '」（ID: ' . $rid . '）から', true];
            }
            return ['他の部屋', '他の部屋（ID: ' . $rid . '）から', true];
        }
        if ($p === '/oclist') {
            return ['部屋一覧', '部屋一覧ページ', true];
        }
        if (str_starts_with($p, '/recently-registered')) {
            return ['新着の部屋', '新着登録の部屋一覧', true];
        }
        if (str_starts_with($p, '/comments-timeline')) {
            return ['コメント新着', '新着コメント一覧', true];
        }
        if (str_starts_with($p, '/comment/')) {
            return ['コメント欄', 'コメント欄', true];
        }
        if ($p === '/labs' || str_starts_with($p, '/labs/')) {
            return ['ラボ', 'オプチャグラフ Labs', true];
        }
        // 既知パターン外の本家内ページ
        return ['サイト内ページ', 'オプチャグラフ内のページ: ' . $path, true];
    }
}
