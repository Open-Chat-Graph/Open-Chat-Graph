<?php

declare(strict_types=1);

namespace App\Services\OgImage;

use App\Config\AppConfig;
use Shared\MimimalCmsConfig;

/**
 * 動的OGPカードの HTTP 送出（生成PNG／デフォルト画像フォールバック）。
 * /oc/{id}/card と /recommend/{tag}/card のコントローラで共通のため分離した純粋な出力層。
 * いずれもヘッダー送出→本文→exit で完結する（以降の処理は走らない）。
 */
class OgCardHttpResponder
{
    /** エッジ／ブラウザのキャッシュ秒数。og:image は日付クエリ(?d=Ymd)で日次ローテするので長めでよい */
    public const CACHE_MAX_AGE = 43200; // 12h

    /** 生成したPNGバイト列を、エッジがキャッシュできるヘッダー付きで送出して終了する */
    public function sendPng(string $bytes): never
    {
        header('Content-Type: image/png');
        header('Cache-Control: public, max-age=' . self::CACHE_MAX_AGE);
        header('X-Robots-Tag: noindex');
        echo $bytes;
        exit;
    }

    /** デフォルトOGP画像（言語別）を送って終了する（生成不可・混雑時のフォールバック） */
    public function sendDefault(): never
    {
        $path = AppConfig::DEFAULT_OGP_IMAGE_FILE_PATHS[MimimalCmsConfig::$urlRoot]
            ?? AppConfig::DEFAULT_OGP_IMAGE_FILE_PATHS[''];
        $fallback = AppConfig::ROOT_PATH . 'public/' . $path;
        if (is_file($fallback)) {
            header('Content-Type: image/png');
            // フォールバックもエッジ(CF)にはキャッシュさせる。ただし混雑・一時失敗で出たものが
            // 長時間ピンされないよう、本来のカード(12h)より短いTTLにして早めに再生成へ戻す。
            header('Cache-Control: public, max-age=600');
            header('X-Robots-Tag: noindex');
            readfile($fallback);
        }
        exit;
    }
}
