<?php

use App\ServiceProvider\OpenChatCrawlerConfigServiceProvider;
use App\Services\Storage\FileStorageInterface;
use App\Services\Storage\FileStorageService;

// Register FileStorageService as singleton
app()->singleton(FileStorageInterface::class, FileStorageService::class);

app(OpenChatCrawlerConfigServiceProvider::class)->register();

// AIエージェント向けMarkdownネゴシエーション:
// Accept: text/markdown を含むGETリクエストのHTMLレスポンスをMarkdownへ変換して返す
// （対象リクエスト以外は何もしない。CLI実行時も無効）
\App\Services\Agent\AgentMarkdownResponder::register();
