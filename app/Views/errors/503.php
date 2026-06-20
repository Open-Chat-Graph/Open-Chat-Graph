<?php

/**
 * HTTP 503 (Service Unavailable) 用のエラービュー。
 *
 * 画面は 5xx 共通の「再読み込み」画面（error.php）をそのまま流用する。
 * 503 固有の差分は Retry-After ヘッダーのみ:
 *   - クローラ（Googlebot 等）に「一時的な混雑。少し後で再クロールして」と伝える。
 *     503 + Retry-After は 500 と違い検索順位を落とさない正規の「混雑」シグナル。
 *   - 軽いジッタ(30〜90秒)で再試行時刻を分散し、復帰直後の再集中(サンダリングハード)を避ける。
 *
 * error.php 自体が no-store を設定するため、503 が CDN/ブラウザにキャッシュされることはない。
 */

if (!headers_sent()) {
    header('Retry-After: ' . random_int(30, 90));
}

require __DIR__ . '/error.php';
