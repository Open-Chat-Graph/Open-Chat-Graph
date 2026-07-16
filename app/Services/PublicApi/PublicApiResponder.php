<?php

declare(strict_types=1);

namespace App\Services\PublicApi;

final class PublicApiResponder
{
    public function respond(array $payload, string $lastModified, string $htmlCanonical, int $status = 200): \Shadow\Kernel\Response
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $etag = '"' . hash('sha256', $json) . '"';
        $modified = ctype_digit($lastModified)
            ? new \DateTimeImmutable('@' . $lastModified)
            : new \DateTimeImmutable($lastModified);
        $modified = $modified->setTimezone(new \DateTimeZone('UTC'));
        $lastModifiedHeader = $modified->format('D, d M Y H:i:s') . ' GMT';

        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, HEAD');
        header('Access-Control-Allow-Headers: Accept, If-None-Match, If-Modified-Since');
        header('X-Robots-Tag: noindex, follow');
        header('Cache-Control: public, max-age=0, must-revalidate');
        header('Cloudflare-CDN-Cache-Control: max-age=3600');
        header('ETag: ' . $etag);
        header('Last-Modified: ' . $lastModifiedHeader);
        header('Link: <' . $htmlCanonical . '>; rel="canonical"');
        header('Vary: Accept-Encoding');

        $ifNoneMatch = trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '');
        $ifModifiedSince = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? null;
        if ($status === 200 && ($ifNoneMatch === $etag || ($ifModifiedSince && strtotime($ifModifiedSince) >= $modified->getTimestamp()))) {
            http_response_code(304);
            exit;
        }

        return response($payload, $status);
    }
}
