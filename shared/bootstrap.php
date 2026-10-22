<?php

use App\ServiceProvider\OpenChatCrawlerConfigServiceProvider;
use App\Services\Storage\FileStorageInterface;
use App\Services\Storage\FileStorageService;

// Register FileStorageService as singleton
app()->singleton(FileStorageInterface::class, FileStorageService::class);

app(OpenChatCrawlerConfigServiceProvider::class)->register();

// 利用者が書いた SQL の実行中に致命的エラーで止まり、メモリの上限を戻せないまま終わったプロセスの後始末
// （Web の PHP はプロセスを次のリクエストにも使い回す。App\Services\Api\UserSqlMemoryLimit）
if (PHP_SAPI !== 'cli') {
    \App\Services\Api\UserSqlMemoryLimit::restoreLeftover();
}
