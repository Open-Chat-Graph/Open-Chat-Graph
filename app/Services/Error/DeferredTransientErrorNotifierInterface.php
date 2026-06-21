<?php

declare(strict_types=1);

namespace App\Services\Error;

/**
 * Web リクエストで起きた一過性DBエラー(TransientDatabaseException)を、リアルタイムではなく
 * 「一定件数たまったらまとめて1通」Discord 通知するためのバッファ。
 *
 * 実装の差し替え・テスト用モック化のため Interface を必須とし、/shared/MimimalCmsConfig.php で
 * DI バインドする。
 */
interface DeferredTransientErrorNotifierInterface
{
    /**
     * 一過性DBエラーを1件キューに記録する。閾値(既定10件)に達したらまとめて Discord に
     * 送信し、キューを空にする。
     */
    public function record(\Throwable $e): void;

    /**
     * キューに溜まっている端数(閾値未満)を即送信して空にする。
     * 毎時 cron などから呼び、10件たまらないまま長時間放置されないようにする。
     */
    public function flush(): void;
}
