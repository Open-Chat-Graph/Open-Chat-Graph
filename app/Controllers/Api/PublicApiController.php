<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\PublicApi\PublicResourceRepositoryInterface;
use App\Services\PublicApi\CursorCodec;
use App\Services\PublicApi\PublicApiRateLimiter;
use App\Services\PublicApi\PublicApiResponder;
use App\Services\PublicApi\PublicResourceFactory;
use App\Services\Storage\FileStorageInterface;
use Shadow\Kernel\Reception;
use Shared\MimimalCmsConfig;

final class PublicApiController
{
    private const PERIODS = ['hour', 'day', 'week', 'members'];

    public function __construct(
        private readonly PublicResourceRepositoryInterface $repository,
        private readonly PublicResourceFactory $factory,
        private readonly CursorCodec $cursorCodec,
        private readonly PublicApiRateLimiter $rateLimiter,
        private readonly PublicApiResponder $responder,
        private readonly FileStorageInterface $fileStorage,
    ) {}

    public function room(int $id): \Shadow\Kernel\Response
    {
        if ($limited = $this->limit(false)) {
            return $limited;
        }
        $row = $this->repository->findRoom($id);
        $canonical = url('oc', (string)$id);
        if (!$row) {
            $status = $this->repository->isDeletedRoom($id) ? 410 : 404;
            return $this->error($status, $status === 410 ? 'room_gone' : 'room_not_found', $canonical);
        }
        $updatedAt = (string)$row['data_updated_at'];
        $resource = $this->factory->room($row, $this->observedAt());
        return $this->responder->respond([
            'data' => $resource,
            'meta' => $this->meta(1, 1, $this->locale(), $this->snapshot()),
            'links' => [
                'self' => url('api/v1/rooms/' . $id),
                'canonical' => $canonical,
                'collection' => url('api/v1/rooms'),
            ],
        ], $updatedAt, $canonical);
    }

    public function rooms(): \Shadow\Kernel\Response
    {
        $search = trim((string)Reception::input('q', ''));
        if (mb_strlen($search) > 100) {
            return $this->badRequest('q must be 100 characters or fewer.');
        }
        if ($limited = $this->limit($search !== '')) {
            return $limited;
        }
        $limit = $this->pageLimit();
        if ($limit === null) {
            return $this->badRequest('limit must be an integer from 1 to 50.');
        }
        $filters = ['endpoint' => 'rooms', 'q' => $search];
        [$offset, $snapshot] = $this->pagination($filters);
        if ($offset === null) {
            return $this->badRequest('cursor is invalid, expired, or belongs to another request.');
        }
        $searchArg = $search === '' ? null : $search;
        $rows = $this->repository->listRooms($limit, $offset, $searchArg, $snapshot);
        $data = array_map(fn(array $row) => $this->factory->room($row, $this->observedAt()), $rows);
        $total = $this->repository->countRooms($searchArg, $snapshot);
        $next = count($rows) === $limit && $offset + $limit < $total
            ? $this->nextLink('api/v1/rooms', $offset + $limit, $snapshot, $filters, ['q' => $search, 'limit' => $limit])
            : null;
        $canonical = url('ranking');
        return $this->collectionResponse($data, $total, $offset, $limit, $snapshot, $canonical, url('api/v1/rooms'), $next);
    }

    public function rankings(): \Shadow\Kernel\Response
    {
        if ($limited = $this->limit(false)) {
            return $limited;
        }
        $period = (string)Reception::input('period', 'day');
        $category = filter_var(Reception::input('category', 0), FILTER_VALIDATE_INT);
        $limit = $this->pageLimit();
        if (!in_array($period, self::PERIODS, true) || $category === false || $category < 0 || $limit === null) {
            return $this->badRequest('period, category, or limit is invalid.');
        }
        if ($category > 0 && !getCategoryName((int)$category)) {
            return $this->badRequest('category is unknown for this locale.');
        }
        $filters = ['endpoint' => 'rankings', 'period' => $period, 'category' => (int)$category];
        [$offset, $snapshot] = $this->pagination($filters);
        if ($offset === null) {
            return $this->badRequest('cursor is invalid, expired, or belongs to another request.');
        }
        $rows = $this->repository->listRankings($period, (int)$category, $limit, $offset, $snapshot);
        $data = [];
        foreach ($rows as $index => $row) {
            $change = isset($row['ranking_change']) ? (int)$row['ranking_change'] : null;
            $row['change_1h'] = $period === 'hour' ? $change : null;
            $row['change_24h'] = $period === 'day' ? $change : null;
            $row['change_7d'] = $period === 'week' ? $change : null;
            $data[] = [
                'position' => $offset + $index + 1,
                'period' => $period,
                'change' => $change,
                'room' => $this->factory->room($row, $this->observedAt()),
            ];
        }
        $total = $this->repository->countRankings($period, (int)$category, $snapshot);
        $next = count($rows) === $limit && $offset + $limit < $total
            ? $this->nextLink('api/v1/rankings', $offset + $limit, $snapshot, $filters, ['period' => $period, 'category' => (int)$category, 'limit' => $limit])
            : null;
        $canonical = url('ranking') . ($category ? '/' . $category : '');
        return $this->collectionResponse($data, $total, $offset, $limit, $snapshot, $canonical, url('api/v1/rankings'), $next);
    }

    public function themes(): \Shadow\Kernel\Response
    {
        if ($limited = $this->limit(false)) {
            return $limited;
        }
        $limit = $this->pageLimit();
        if ($limit === null) {
            return $this->badRequest('limit must be an integer from 1 to 50.');
        }
        $filters = ['endpoint' => 'themes'];
        [$offset, $snapshot] = $this->pagination($filters);
        if ($offset === null) {
            return $this->badRequest('cursor is invalid, expired, or belongs to another request.');
        }
        $rows = $this->repository->listThemes($limit, $offset, $snapshot);
        $data = array_map(fn(array $row) => $this->factory->theme($row), $rows);
        $total = $this->repository->countThemes($snapshot);
        $next = count($rows) === $limit && $offset + $limit < $total
            ? $this->nextLink('api/v1/themes', $offset + $limit, $snapshot, $filters, ['limit' => $limit])
            : null;
        return $this->collectionResponse($data, $total, $offset, $limit, $snapshot, rtrim(url(), '/'), url('api/v1/themes'), $next);
    }

    public function theme(string $tag): \Shadow\Kernel\Response
    {
        if ($limited = $this->limit(false)) {
            return $limited;
        }
        $tag = urldecode($tag);
        $limit = $this->pageLimit();
        if ($limit === null || mb_strlen($tag) > 1000) {
            return $this->badRequest('tag or limit is invalid.');
        }
        $theme = $this->repository->findTheme($tag);
        if (!$theme) {
            return $this->error(404, 'theme_not_found', rtrim(url(), '/'));
        }
        $canonicalTag = (string)$theme['tag'];
        $filters = ['endpoint' => 'theme', 'tag' => $canonicalTag];
        [$offset, $snapshot] = $this->pagination($filters);
        if ($offset === null) {
            return $this->badRequest('cursor is invalid, expired, or belongs to another request.');
        }
        $rows = $this->repository->listThemeRooms($canonicalTag, $limit, $offset, $snapshot);
        $rooms = [];
        foreach ($rows as $row) {
            $row['change_24h'] = $row['ranking_change'] ?? null;
            $rooms[] = $this->factory->room($row, $this->observedAt());
        }
        $total = (int)$theme['room_count'];
        $next = count($rows) === $limit && $offset + $limit < $total
            ? $this->nextLink('api/v1/themes/' . urlencode($canonicalTag), $offset + $limit, $snapshot, $filters, ['limit' => $limit])
            : null;
        $canonical = url('recommend/' . urlencode($canonicalTag));
        return $this->responder->respond([
            'data' => ['theme' => $this->factory->theme($theme), 'rooms' => $rooms],
            'meta' => $this->meta(count($rooms), $total, $this->locale(), $snapshot) + ['offset' => $offset, 'limit' => $limit],
            'links' => ['self' => url('api/v1/themes/' . urlencode($canonicalTag)), 'canonical' => $canonical, 'next' => $next],
        ], (string)$theme['data_updated_at'], $canonical);
    }

    public function stats(): \Shadow\Kernel\Response
    {
        if ($limited = $this->limit(false)) {
            return $limited;
        }
        $data = $this->repository->getSiteStats();
        $updatedAt = (string)($data['data_updated_at'] ?? $this->snapshot());
        $resource = [
            'room_count' => (int)($data['room_count'] ?? 0),
            'total_members' => (int)($data['total_members'] ?? 0),
            'new_rooms_7d' => (int)($data['new_rooms_7d'] ?? 0),
            'tracking_started_at' => isset($data['tracking_started_at']) ? PublicResourceFactory::dateToRfc3339((string)$data['tracking_started_at']) : null,
            'data_updated_at' => PublicResourceFactory::dateToRfc3339($updatedAt),
        ];
        return $this->responder->respond([
            'data' => $resource,
            'meta' => $this->meta(1, 1, $this->locale(), $updatedAt),
            'links' => ['self' => url('api/v1/stats'), 'canonical' => url('labs/all-room-stats')],
        ], $updatedAt, url('labs/all-room-stats'));
    }

    private function collectionResponse(array $data, int $total, int $offset, int $limit, string $snapshot, string $canonical, string $self, ?string $next): \Shadow\Kernel\Response
    {
        return $this->responder->respond([
            'data' => $data,
            'meta' => $this->meta(count($data), $total, $this->locale(), $snapshot) + ['offset' => $offset, 'limit' => $limit],
            'links' => ['self' => $self, 'canonical' => $canonical, 'next' => $next],
        ], $snapshot, $canonical);
    }

    /** @param array<string,scalar|null> $filters @return array{?int,string} */
    private function pagination(array $filters): array
    {
        $cursor = trim((string)Reception::input('cursor', ''));
        if ($cursor === '') {
            return [0, $this->snapshot()];
        }
        if (strlen($cursor) > 4096) {
            return [null, $this->snapshot()];
        }
        try {
            $decoded = $this->cursorCodec->decode($cursor, $this->locale(), $filters);
            return [(int)$decoded['offset'], (string)$decoded['snapshot']];
        } catch (\Throwable) {
            return [null, $this->snapshot()];
        }
    }

    /** @param array<string,scalar|null> $filters @param array<string,scalar|null> $query */
    private function nextLink(string $path, int $offset, string $snapshot, array $filters, array $query): string
    {
        $query['cursor'] = $this->cursorCodec->encode($offset, $this->locale(), $snapshot, $filters);
        $query = array_filter($query, static fn($value) => $value !== '' && $value !== null && $value !== 0);
        return url($path) . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    private function pageLimit(): ?int
    {
        $raw = Reception::input('limit', 20);
        if (filter_var($raw, FILTER_VALIDATE_INT) === false) {
            return null;
        }
        $limit = (int)$raw;
        return $limit >= 1 && $limit <= 50 ? $limit : null;
    }

    private function limit(bool $search): ?\Shadow\Kernel\Response
    {
        $client = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $retry = $this->rateLimiter->hit($this->locale() . ':' . $client . ':' . ($search ? 'search' : 'read'), $search ? 20 : 60);
        if ($retry === null) {
            return null;
        }
        header('Retry-After: ' . $retry);
        return $this->error(429, 'rate_limit_exceeded', rtrim(url(), '/'), ['retry_after' => $retry]);
    }

    private function badRequest(string $detail): \Shadow\Kernel\Response
    {
        return $this->error(400, 'invalid_request', rtrim(url(), '/'), ['detail' => $detail]);
    }

    private function error(int $status, string $code, string $canonical, array $extra = []): \Shadow\Kernel\Response
    {
        return $this->responder->respond([
            'data' => null,
            'meta' => ['status' => $status, 'error' => $code] + $extra,
            'links' => ['self' => rtrim(url(path()), '/'), 'canonical' => $canonical],
        ], $this->snapshot(), $canonical, $status);
    }

    private function meta(int $count, int $total, string $locale, string $snapshot): array
    {
        return [
            'locale' => $locale,
            'count' => $count,
            'total' => $total,
            'snapshot_at' => PublicResourceFactory::dateToRfc3339($snapshot),
            'methodology_url' => url('policy') . '#methodology',
        ];
    }

    private function locale(): string
    {
        return match (MimimalCmsConfig::$urlRoot) {
            '/tw' => 'tw',
            '/th' => 'th',
            default => 'ja',
        };
    }

    private function snapshot(): string
    {
        return $this->repository->latestUpdatedAt();
    }

    private function observedAt(): string
    {
        return $this->fileStorage->getContents('@hourlyCronUpdatedAtDatetime');
    }
}
