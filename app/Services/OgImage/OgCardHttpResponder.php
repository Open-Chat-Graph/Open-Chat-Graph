<?php

declare(strict_types=1);

namespace App\Services\OgImage;

use App\Config\AppConfig;

/**
 * 動的OGPカード・1:1サムネイルの HTTP 送出（生成PNG／デフォルト画像フォールバック）。
 * /oc/{id}/card・/oc/{id}/thumb・/recommend/{tag}/card・/recommend/{tag}/thumb の
 * コントローラで共通のため分離した純粋な出力層。
 * いずれもヘッダー送出→本文→exit で完結する（以降の処理は走らない）。
 */
class OgCardHttpResponder
{
    /** エッジ／ブラウザのキャッシュ秒数。og:image は日付クエリ(?d=Ymd)で日次ローテするので長めでよい */
    public const CACHE_MAX_AGE = 43200; // 12h

    /**
     * 生成したPNGバイト列を、エッジがキャッシュできるヘッダー付きで送出して終了する。
     *
     * @param bool $noindex SNSカード(og:image)は true（検索に画像単体を出さない）。
     *                      検索サムネイル(meta name="thumbnail")用は false＝X-Robots-Tag を
     *                      出さない（noindex を付けると検索側がサムネとして採用しない恐れがある）
     */
    public function sendPng(string $bytes, bool $noindex = true): never
    {
        header('Content-Type: image/png');
        header('Cache-Control: public, max-age=' . self::CACHE_MAX_AGE);
        if ($noindex) {
            header('X-Robots-Tag: noindex');
        }
        echo $bytes;
        exit;
    }

    /**
     * デフォルト画像を送って終了する（生成不可・混雑時のフォールバック）。
     *
     * @param bool $square 1:1サムネイル用エンドポイントは true＝正方形のサイトアイコンで代替する
     *                     （16:9 のデフォルトOGPを返すと検索側が期待する縦横比と食い違うため）。
     *                     このときサムネ本流に合わせて X-Robots-Tag も出さない
     */
    public function sendDefault(bool $square = false): never
    {
        $path = $square ? AppConfig::SITE_ICON_FILE_PATH : AppConfig::defaultOgpImagePath();
        $fallback = AppConfig::ROOT_PATH . 'public/' . $path;
        if (is_file($fallback)) {
            header('Content-Type: image/png');
            // フォールバックもエッジ(CF)にはキャッシュさせる。ただし混雑・一時失敗で出たものが
            // 長時間ピンされないよう、本来のカード(12h)より短いTTLにして早めに再生成へ戻す。
            header('Cache-Control: public, max-age=600');
            if (!$square) {
                header('X-Robots-Tag: noindex');
            }
            readfile($fallback);
        }
        exit;
    }
}
