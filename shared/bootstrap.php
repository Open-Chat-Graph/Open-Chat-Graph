<?php

use App\ServiceProvider\OpenChatCrawlerConfigServiceProvider;
use App\Services\Storage\FileStorageInterface;
use App\Services\Storage\FileStorageService;
use App\Services\Seo\OpenChatUrlNormalizer;

// Room graph controls used to live in the query string, creating many crawlable
// copies of the same room. Canonicalise before route matching/DB access.
$canonicalRoomTarget = OpenChatUrlNormalizer::normalizeRequestUri(
    $_SERVER['REQUEST_URI'] ?? '/',
    $_SERVER['REQUEST_METHOD'] ?? 'GET',
);
if ($canonicalRoomTarget !== null) {
    header('Location: ' . $canonicalRoomTarget, true, 301);
    exit;
}

// Register FileStorageService as singleton
app()->singleton(FileStorageInterface::class, FileStorageService::class);

app(OpenChatCrawlerConfigServiceProvider::class)->register();
