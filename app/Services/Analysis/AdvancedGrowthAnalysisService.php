<?php

declare(strict_types=1);

namespace App\Services\Analysis;

use App\Models\Repositories\Analysis\AnalysisRoomRepositoryInterface;
use App\Models\Repositories\Analysis\AnalysisStatsRepositoryInterface;
use App\Services\Storage\FileStorageInterface;
use Shared\Exceptions\BadRequestException;

/**
 * 詳細成長分析（/labs/growth）。重い集計を「シンプルなポーリング」で進め、進捗(%)を返す。
 *
 * - status: open_chat_id レンジを CHUNK_COUNT 分割。1 回叩くごとに 1 チャンク集計して 1 つの
 *   ジョブファイルに積み増し、進捗% を返す。最終チャンクでソートして finished に。
 *   （1 リクエストでは進捗を出せないので、待ち時間の目安を見せるために分割ポーリングする）
 * - result: その finished ジョブを読み、絞り込み・並び替え・ページ切り出し・整形して返す。
 *   毎時更新なので同一 URL は CDN がキャッシュする（コントローラの checkLastModified）。
 * - ジョブファイルは短命（JOB_TTL_SECONDS）。結果は CDN が持つのでサーバには残し続けない。
 *
 * 行は省メモリのため数値配列で持つ（列順は I_* 定数）。
 */
class AdvancedGrowthAnalysisService
{
    /** チャンク分割数（status のポーリング回数 ≒ これ） */
    private const CHUNK_COUNT = 30;

    /** 統計データの開始日（これ以前は from に指定できない。最初の数日はノイズが多いので 10-30 から） */
    private const DATA_START_DATE = '2023-10-30';

    /** ジョブファイルの寿命（秒）。CDN がレスポンスを持つので短命でよい */
    private const JOB_TTL_SECONDS = 600;

    /** 増加率ソート時の最低 base メンバー数（極小規模からの暴騰%ノイズを除外） */
    private const RATE_MIN_BASE = 50;

    // 行の列インデックス
    private const I_ID = 0;
    private const I_CAT = 1;
    // increase 行: [id, cat, diff, pct, base]
    private const I_DIFF = 2;
    private const I_PCT = 3;
    private const I_BASE = 4;
    // steady 行: [id, cat, score, base]（score は並び替え用・非表示）
    private const I_SCORE = 2;
    private const I_STEADY_BASE = 3;

    public function __construct(
        private AnalysisStatsRepositoryInterface $stats,
        private AnalysisRoomRepositoryInterface $rooms,
        private FileStorageInterface $fileStorage,
    ) {}

    /** 毎時クロール更新時刻（CDN の Last-Modified 兼 時間帯スコープの基準） */
    public function hourlyUpdatedAt(): string
    {
        return trim($this->fileStorage->getContents('@hourlyCronUpdatedAtDatetime'));
    }

    /**
     * status: 1 チャンク進めて進捗を返す。完了済みなら即 done。
     *
     * @return array{done:bool, percent:int, computed:int, total:?int}
     */
    public function advance(string $metric, string $period, ?string $from, ?string $to): array
    {
        $spec = $this->resolveSpec($metric, $period, $from, $to);
        $this->sweepStale();
        $jobPath = $this->jobPath($spec);

        $job = $this->fileStorage->getSerializedFile($jobPath);
        if (is_array($job) && !empty($job['finished'])) {
            return $this->doneResponse($job);
        }

        $lockFp = fopen($this->jobDir() . '/' . $spec['key'] . '.lock', 'c');
        $gotLock = $lockFp !== false && flock($lockFp, LOCK_EX | LOCK_NB);
        if (!$gotLock) {
            if ($lockFp !== false) {
                fclose($lockFp);
            }
            return $this->progressResponse(is_array($job) ? $job : null);
        }

        try {
            $job = $this->fileStorage->getSerializedFile($jobPath);
            if (is_array($job) && !empty($job['finished'])) {
                return $this->doneResponse($job);
            }
            if (!is_array($job)) {
                $maxId = $this->rooms->getMaxOpenChatId();
                $job = ['step' => intdiv(max($maxId, 1), self::CHUNK_COUNT) + 1, 'done' => 0, 'rows' => [], 'finished' => false];
            }

            $i = (int)$job['done'];
            $lo = $i * (int)$job['step'];
            $hi = $lo + (int)$job['step'];
            foreach ($this->computeChunk($spec, $lo, $hi) as $row) {
                $job['rows'][] = $row;
            }
            $job['done'] = $i + 1;

            if ($job['done'] >= self::CHUNK_COUNT) {
                $primary = $spec['metric'] === 'increase' ? self::I_DIFF : self::I_SCORE;
                usort($job['rows'], static fn($a, $b) => ($b[$primary] <=> $a[$primary]));
                $job['finished'] = true;
            }

            $this->fileStorage->saveSerializedFile($jobPath, $job);
            return $job['finished'] ? $this->doneResponse($job) : $this->progressResponse($job);
        } finally {
            flock($lockFp, LOCK_UN);
            fclose($lockFp);
        }
    }

    /**
     * result: 完成済みジョブをフィルタ・ソート・スライスして整形して返す。
     * 未完成（TTL 経過・時間帯ロールオーバー等）なら空（クライアントは status を再実行）。
     *
     * @return array<int, array<string, mixed>> 先頭要素に totalCount を載せる（page 0 のとき）
     */
    public function result(
        string $metric,
        string $period,
        ?string $from,
        ?string $to,
        string $sort,
        string $order,
        int $category,
        string $keyword,
        int $page,
        int $limit,
    ): array {
        $spec = $this->resolveSpec($metric, $period, $from, $to);
        $job = $this->fileStorage->getSerializedFile($this->jobPath($spec));
        if (!is_array($job) || empty($job['finished'])) {
            return $page === 0 ? [['totalCount' => 0]] : [];
        }

        /** @var array<int, array<int, int|float|null>> $rows */
        $rows = $job['rows'];
        $isIncrease = $spec['metric'] === 'increase';

        if ($category > 0) {
            $rows = array_values(array_filter($rows, static fn($r) => $r[self::I_CAT] === $category));
        }
        if ($keyword !== '') {
            $ids = array_flip($this->rooms->findIdsByKeyword($keyword));
            $rows = array_values(array_filter($rows, static fn($r) => isset($ids[$r[self::I_ID]])));
        }
        if ($isIncrease && $sort === 'rate') {
            $rows = array_values(array_filter($rows, static fn($r) => (int)$r[self::I_BASE] >= self::RATE_MIN_BASE));
        }

        $rows = $this->sortRows($rows, $isIncrease, $sort, $order);
        $total = count($rows);

        $slice = array_slice($rows, $page * $limit, $limit);
        $hydrated = $this->rooms->hydrate(array_map(static fn($r) => (int)$r[self::I_ID], $slice));

        $items = [];
        foreach ($slice as $idx => $r) {
            $id = (int)$r[self::I_ID];
            $d = $hydrated[$id] ?? null;
            if ($d === null) {
                continue;
            }

            if ($isIncrease) {
                $base = (int)$r[self::I_BASE];
                $diff = (int)$r[self::I_DIFF];
                $pct = $r[self::I_PCT] !== null ? (float)$r[self::I_PCT] : null;
            } else {
                $base = (int)$r[self::I_STEADY_BASE];
                $diff = $d['member'] - $base;
                $pct = GrowthMath::safePct($base, $d['member']);
            }

            $item = [
                'id' => $id,
                'name' => $d['name'],
                'desc' => $d['desc'],
                'member' => $d['member'],
                'img' => $d['img'],
                'emblem' => $d['emblem'],
                'joinMethodType' => $d['joinMethodType'],
                'category' => $d['category'],
                'diff' => $diff,
                'pct' => $pct,
                'base' => $base,
            ];
            if ($page === 0 && $idx === 0) {
                $item['totalCount'] = $total;
            }
            $items[] = $item;
        }

        if ($page === 0 && $items === []) {
            return [['totalCount' => 0]];
        }

        return $items;
    }

    // ───────────────────────── 内部 ─────────────────────────

    /** @return array{done:bool, percent:int, computed:int, total:int} */
    private function doneResponse(array $job): array
    {
        $n = count($job['rows'] ?? []);
        return ['done' => true, 'percent' => 100, 'computed' => $n, 'total' => $n];
    }

    /** @return array{done:bool, percent:int, computed:int, total:null} */
    private function progressResponse(?array $job): array
    {
        $done = is_array($job) ? (int)($job['done'] ?? 0) : 0;
        $computed = is_array($job) ? count($job['rows'] ?? []) : 0;
        return ['done' => false, 'percent' => min((int)floor($done / self::CHUNK_COUNT * 100), 99), 'computed' => $computed, 'total' => null];
    }

    /**
     * @return array{metric:string, from:string, to:string, windowDays:int, curIsLatest:bool, key:string, hour:string}
     */
    private function resolveSpec(string $metric, string $period, ?string $from, ?string $to): array
    {
        $hourly = $this->hourlyUpdatedAt();
        $baseDate = substr($hourly, 0, 10);
        $hour = preg_replace('/[^0-9]/', '', $hourly);

        if ($period === 'custom') {
            $fromDate = $this->validDate($from);
            $toDate = ($to !== null && $to !== '') ? $this->validDate($to) : $baseDate;
            if ($fromDate === null) {
                throw new BadRequestException('from は YYYY-MM-DD で指定してください');
            }
            if ($toDate === null) {
                throw new BadRequestException('to は YYYY-MM-DD で指定してください');
            }
            $fromDate = max($fromDate, self::DATA_START_DATE);
            $toDate = min($toDate, $baseDate);
            if ($fromDate >= $toDate) {
                throw new BadRequestException('from は to より前の日付にしてください');
            }
        } else {
            $modifier = match ($period) {
                'month' => '-1 month',
                '3month' => '-3 months',
                '6month' => '-6 months',
                'year' => '-1 year',
                'all' => null,
                default => '-3 months',
            };
            $fromDate = $modifier === null
                ? self::DATA_START_DATE
                : max(date('Y-m-d', strtotime($baseDate . ' ' . $modifier)), self::DATA_START_DATE);
            $toDate = $baseDate;
        }

        return [
            'metric' => $metric,
            'from' => $fromDate,
            'to' => $toDate,
            'windowDays' => (int) round((strtotime($toDate) - strtotime($fromDate)) / 86400),
            'curIsLatest' => ($toDate === $baseDate),
            'key' => substr(hash('sha256', \Shared\MimimalCmsConfig::$urlRoot . '|' . $metric . '|' . $fromDate . '|' . $toDate), 0, 24),
            'hour' => $hour,
        ];
    }

    private function validDate(?string $d): ?string
    {
        if ($d === null || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
            return null;
        }
        [$y, $m, $day] = array_map('intval', explode('-', $d));
        return checkdate($m, $day, $y) ? $d : null;
    }

    /**
     * 1 レンジ分（open_chat_id ∈ [lo,hi)）の指標を計算して数値配列の行群で返す。
     *
     * @return array<int, array<int, int|float|null>>
     */
    private function computeChunk(array $spec, int $lo, int $hi): array
    {
        $rooms = $this->rooms->getRoomsInRange($lo, $hi);
        if (!$rooms) {
            return [];
        }

        $out = [];

        if ($spec['metric'] === 'increase') {
            $base = $this->stats->getMemberAsOf($lo, $hi, $spec['from']);
            $curMap = $spec['curIsLatest'] ? null : $this->stats->getMemberAsOf($lo, $hi, $spec['to']);
            foreach ($base as $id => $bm) {
                if (!isset($rooms[$id])) {
                    continue;
                }
                $cur = $curMap === null ? $rooms[$id]['member'] : ($curMap[$id] ?? null);
                if ($cur === null) {
                    continue;
                }
                $inc = GrowthMath::periodIncrease($bm, $cur);
                $out[] = [$id, $rooms[$id]['category'], $inc['diff'], $inc['pct'], $bm];
            }
        } else { // steady
            $aggs = $this->stats->getSteadyAggregates($lo, $hi, $spec['from'], $spec['to']);
            foreach ($aggs as $id => $agg) {
                if (!isset($rooms[$id])) {
                    continue;
                }
                $s = GrowthMath::steady($agg, $rooms[$id]['member'], $spec['windowDays']);
                if ($s === null) {
                    continue;
                }
                $out[] = [$id, $rooms[$id]['category'], $s['score'], $s['base']];
            }
        }

        return $out;
    }

    /**
     * @param array<int, array<int, int|float|null>> $rows
     * @return array<int, array<int, int|float|null>>
     */
    private function sortRows(array $rows, bool $isIncrease, string $sort, string $order): array
    {
        $col = $isIncrease ? ($sort === 'rate' ? self::I_PCT : self::I_DIFF) : self::I_SCORE;
        $primary = $isIncrease ? self::I_DIFF : self::I_SCORE;
        if ($col === $primary && $order === 'desc') {
            return $rows;
        }
        usort($rows, $order === 'asc'
            ? static fn($a, $b) => ($a[$col] <=> $b[$col])
            : static fn($a, $b) => ($b[$col] <=> $a[$col]));
        return $rows;
    }

    private function jobDir(): string
    {
        $dir = $this->fileStorage->getStorageFilePath('analysisJobsDir');
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        return $dir;
    }

    private function jobPath(array $spec): string
    {
        return $this->jobDir() . '/' . $spec['key'] . '.' . $spec['hour'] . '.job';
    }

    /** TTL を過ぎた job/lock を掃除（@抑制警告も例外化される環境なので is_file 確認してから） */
    private function sweepStale(): void
    {
        $now = time();
        foreach (glob($this->jobDir() . '/*') ?: [] as $file) {
            if (is_file($file) && ($now - filemtime($file)) > self::JOB_TTL_SECONDS) {
                $this->safeUnlink($file);
            }
        }
    }

    private function safeUnlink(string $path): void
    {
        if (is_file($path)) {
            unlink($path);
        }
    }
}
