<?php

use App\ServiceProvider\OpenChatCrawlerConfigServiceProvider;
use App\Services\Storage\FileStorageInterface;
use App\Services\Storage\FileStorageService;

// Register FileStorageService as singleton
app()->singleton(FileStorageInterface::class, FileStorageService::class);

app(OpenChatCrawlerConfigServiceProvider::class)->register();
