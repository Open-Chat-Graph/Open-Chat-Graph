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
 * - status: open_chat_id レンジを CHUNK_COUNT 分割。1 回叩くごとに 1 チャンク集計して、そのチャンクだけを
 *   小さなファイルに書き（既存分は読まない）、進捗% を返す。最終チャンクで全チャンクを結合・ソートして
 *   1 つの完成ファイルにする。（1 リクエストでは進捗を出せないので、待ち時間の目安を見せるために分割する）
 * - result: その完成ファイルを読み、絞り込み・並び替え・ページ切り出し・整形して返す。
 *   毎時更新なので同一 URL は CDN がキャッシュする（コントローラの checkLastModified）。
 * - ジョブファイルは短命（JOB_TTL_SECONDS）。結果は CDN が持つのでサーバには残し続けない。
 *
 * 行は列ごとに pack したバイナリ（id / cat / val / base）で持つ。行ごとの PHP 配列＋serialize だと
 * 1 行 ~350B で、全 27 万行を毎ポーリングで読み書きすると web の memory_limit(128MB) を超え、
 * status が途中のチャンクで OOM のまま進まなくなった（2026-10-29）。いまは
 *  - ポーリング 1 回の読み書きは「そのチャンク分」だけ（全体量に比例しない）
 *  - 結合・ソートは最後に 1 回だけ
 *  - result は既定の並び（完成ファイルの並びそのまま）なら必要な行だけを切り出して読む
 * ので、メモリも I/O も小さい。
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

    /** pack に渡す引数の分割サイズ（巨大な展開引数を避ける） */
    private const PACK_BATCH = 10000;

    // computeChunk の行 [id, cat, val, base] の列インデックス
    private const I_ID = 0;
    private const I_CAT = 1;
    /** increase: 増加数 / steady: スコア（並び替え用・非表示） */
    private const I_VAL = 2;
    private const I_BASE = 3;

    /** 先頭の行数ヘッダ（pack 'V'） */
    private const HEADER_BYTES = 4;

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

        $finished = $this->finishedRowCount($spec);
        if ($finished !== null) {
            return $this->doneResponse($finished);
        }

        $lockFp = fopen($this->jobDir() . '/' . $spec['key'] . '.lock', 'c');
        $gotLock = $lockFp !== false && flock($lockFp, LOCK_EX | LOCK_NB);
        if (!$gotLock) {
            if ($lockFp !== false) {
                fclose($lockFp);
            }
            return $this->progressResponse($this->readMeta($spec));
        }

        try {
            $finished = $this->finishedRowCount($spec);
            if ($finished !== null) {
                return $this->doneResponse($finished);
            }

            $meta = $this->readMeta($spec);
            if ($meta === null) {
                $maxId = $this->rooms->getMaxOpenChatId();
                $meta = ['step' => intdiv(max($maxId, 1), self::CHUNK_COUNT) + 1, 'done' => 0, 'computed' => 0];
            }

            if ($meta['done'] < self::CHUNK_COUNT) {
                $i = $meta['done'];
                $lo = $i * $meta['step'];
                $rows = $this->computeChunk($spec, $lo, $lo + $meta['step']);
                $this->fileStorage->safeFileRewrite($this->chunkPath($spec, $i), $this->packRows($spec['metric'], $rows));
                $meta['done'] = $i + 1;
                $meta['computed'] += count($rows);
                unset($rows);
                $this->writeMeta($spec, $meta);
            }

            if ($meta['done'] >= self::CHUNK_COUNT) {
                $n = $this->finalize($spec);
                return $n === null ? $this->progressResponse(null) : $this->doneResponse($n);
            }

            return $this->progressResponse($meta);
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
        $bin = $this->readFinished($spec);
        if ($bin === null) {
            return $page === 0 ? [['totalCount' => 0]] : [];
        }

        $n = $this->rowCount($bin);
        $isIncrease = $spec['metric'] === 'increase';
        $offset = $page * $limit;

        // 既定の並び（完成ファイルは val 降順）で絞り込みも無いなら、必要な行だけを切り出す
        $keepsOrder = $category <= 0 && $keyword === '' && $order === 'desc'
            && !($isIncrease && $sort === 'rate');
        if ($keepsOrder) {
            $total = $n;
            $count = max(0, min($limit, $n - $offset));
            $ids = $this->column($bin, $n, 'id', $spec['metric'], $offset, $count);
            $vals = $this->column($bin, $n, 'val', $spec['metric'], $offset, $count);
            $bases = $this->column($bin, $n, 'base', $spec['metric'], $offset, $count);
            $slice = array_keys($ids);
        } else {
            $ids = $this->column($bin, $n, 'id', $spec['metric']);
            $vals = $this->column($bin, $n, 'val', $spec['metric']);
            $bases = $this->column($bin, $n, 'base', $spec['metric']);

            $pos = array_keys($ids);
            if ($category > 0) {
                $cats = $this->column($bin, $n, 'cat', $spec['metric']);
                $pos = array_values(array_filter($pos, static fn($i) => $cats[$i] === $category));
                unset($cats);
            }
            if ($keyword !== '') {
                $hit = array_flip($this->rooms->findIdsByKeyword($keyword));
                $pos = array_values(array_filter($pos, static fn($i) => isset($hit[$ids[$i]])));
            }
            if ($isIncrease && $sort === 'rate') {
                $pos = array_values(array_filter($pos, static fn($i) => $bases[$i] >= self::RATE_MIN_BASE));
            }

            $pos = $this->sortPositions($pos, $vals, $bases, $isIncrease, $sort, $order);
            $total = count($pos);
            $slice = array_slice($pos, $offset, $limit);
        }
        unset($bin);

        $hydrated = $this->rooms->hydrate(array_map(static fn($i) => $ids[$i], $slice));

        $items = [];
        foreach ($slice as $idx => $i) {
            $id = $ids[$i];
            $d = $hydrated[$id] ?? null;
            if ($d === null) {
                continue;
            }

            $base = $bases[$i];
            if ($isIncrease) {
                $diff = (int)$vals[$i];
                $pct = GrowthMath::periodIncrease($base, $base + $diff)['pct'];
            } else {
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
    private function doneResponse(int $rowCount): array
    {
        return ['done' => true, 'percent' => 100, 'computed' => $rowCount, 'total' => $rowCount];
    }

    /**
     * @param array{step:int, done:int, computed:int}|null $meta
     * @return array{done:bool, percent:int, computed:int, total:null}
     */
    private function progressResponse(?array $meta): array
    {
        $done = $meta['done'] ?? 0;
        $computed = $meta['computed'] ?? 0;
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
     * 1 レンジ分（open_chat_id ∈ [lo,hi)）の指標を計算して数値配列の行群 [id, cat, val, base] で返す。
     *
     * @return array<int, array<int, int|float>>
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
                $out[] = [$id, $rooms[$id]['category'], $cur - $bm, $bm];
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

    // ─────────────── 列バイナリ（行数ヘッダ + id / cat / val / base の順に n 個ずつ） ───────────────

    /**
     * 列名 => [pack フォーマット, 1 要素のバイト数]。val だけ metric で型が違う
     * （increase は増加数＝整数、steady はスコア＝倍精度）。
     *
     * @return array<string, array{0:string, 1:int}>
     */
    private function layout(string $metric): array
    {
        return [
            'id' => ['l', 4],
            'cat' => ['l', 4],
            'val' => $metric === 'increase' ? ['l', 4] : ['e', 8],
            'base' => ['l', 4],
        ];
    }

    /** @param array<int, array<int, int|float>> $rows */
    private function packRows(string $metric, array $rows): string
    {
        $bin = pack('V', count($rows));
        foreach ($this->layout($metric) as $col => [$fmt]) {
            $bin .= $this->packList($fmt, array_column($rows, $this->columnIndex($col)));
        }
        return $bin;
    }

    private function columnIndex(string $col): int
    {
        return match ($col) {
            'id' => self::I_ID,
            'cat' => self::I_CAT,
            'val' => self::I_VAL,
            'base' => self::I_BASE,
        };
    }

    /** @param array<int, int|float> $values */
    private function packList(string $fmt, array $values): string
    {
        $out = '';
        foreach (array_chunk($values, self::PACK_BATCH) as $part) {
            $out .= pack($fmt . '*', ...$part);
        }
        return $out;
    }

    private function rowCount(string $bin): int
    {
        return $bin === '' ? 0 : (int)unpack('V', $bin)[1];
    }

    /**
     * 列を 0 始まりの配列で取り出す。$from / $count を渡すとその範囲だけ読む。
     *
     * @return array<int, int|float>
     */
    private function column(string $bin, int $n, string $col, string $metric, int $from = 0, ?int $count = null): array
    {
        $offset = self::HEADER_BYTES;
        foreach ($this->layout($metric) as $name => [$fmt, $size]) {
            if ($name === $col) {
                $count ??= $n - $from;
                if ($count <= 0) {
                    return [];
                }
                $raw = substr($bin, $offset + $from * $size, $count * $size);
                return array_values(unpack($fmt . '*', $raw));
            }
            $offset += $n * $size;
        }
        throw new \LogicException("unknown column: {$col}");
    }

    /**
     * 行位置の配列を並び替える。val 降順が既定（完成ファイルの並び）。同値は元の順を保つ（安定ソート）。
     *
     * @param array<int, int> $pos
     * @param array<int, int|float> $vals
     * @param array<int, int> $bases
     * @return array<int, int>
     */
    private function sortPositions(array $pos, array $vals, array $bases, bool $isIncrease, string $sort, string $order): array
    {
        $byRate = $isIncrease && $sort === 'rate';
        if (!$byRate && $order === 'desc') {
            return $pos;
        }

        $keys = [];
        foreach ($pos as $i) {
            $keys[$i] = $byRate
                ? GrowthMath::safePct($bases[$i], $bases[$i] + (int)$vals[$i])
                : $vals[$i];
        }
        $order === 'asc' ? asort($keys) : arsort($keys);

        return array_keys($keys);
    }

    // ─────────────── ジョブファイル ───────────────

    /**
     * 完成していれば行数、未完成なら null。
     */
    private function finishedRowCount(array $spec): ?int
    {
        $path = $this->finishedPath($spec);
        if (!is_file($path)) {
            return null;
        }
        $fp = fopen($path, 'rb');
        if ($fp === false) {
            return null;
        }
        $head = fread($fp, self::HEADER_BYTES);
        fclose($fp);

        return strlen((string)$head) === self::HEADER_BYTES ? (int)unpack('V', $head)[1] : null;
    }

    private function readFinished(array $spec): ?string
    {
        $path = $this->finishedPath($spec);
        if (!is_file($path)) {
            return null;
        }
        $bin = $this->fileStorage->getContents($path);

        return strlen($bin) >= self::HEADER_BYTES ? $bin : null;
    }

    /**
     * 全チャンクを結合して val 降順に並べ、完成ファイルを書く。チャンクと進捗ファイルは消す。
     * チャンクが欠けていたら（掃除された等）進捗を捨てて null を返す＝次のポーリングで最初からやり直す。
     *
     * @return int|null 行数
     */
    private function finalize(array $spec): ?int
    {
        $metric = $spec['metric'];
        $layout = $this->layout($metric);

        for ($i = 0; $i < self::CHUNK_COUNT; $i++) {
            if (!is_file($this->chunkPath($spec, $i))) {
                $this->safeUnlink($this->metaPath($spec));
                return null;
            }
        }

        $parts = [];
        $n = 0;
        for ($i = 0; $i < self::CHUNK_COUNT; $i++) {
            $bin = $this->fileStorage->getContents($this->chunkPath($spec, $i));
            $rows = $this->rowCount($bin);
            $parts[] = [$bin, $rows];
            $n += $rows;
        }

        // 列ごとに全チャンクを連結（同時に持つのは 1 列ぶんだけ）
        $columns = [];
        foreach ($layout as $col => [$fmt]) {
            $values = [];
            foreach ($parts as [$bin, $rows]) {
                foreach ($this->column($bin, $rows, $col, $metric) as $v) {
                    $values[] = $v;
                }
            }
            $columns[$col] = $values;
        }
        unset($parts);

        // 同値は連結順（チャンク順）を保つ安定ソート
        $order = $columns['val'];
        arsort($order);
        $order = array_keys($order);

        $out = pack('V', $n);
        foreach ($layout as $col => [$fmt]) {
            $sorted = [];
            foreach ($order as $i) {
                $sorted[] = $columns[$col][$i];
            }
            $out .= $this->packList($fmt, $sorted);
            unset($columns[$col], $sorted);
        }

        $this->fileStorage->safeFileRewrite($this->finishedPath($spec), $out);

        for ($i = 0; $i < self::CHUNK_COUNT; $i++) {
            $this->safeUnlink($this->chunkPath($spec, $i));
        }
        $this->safeUnlink($this->metaPath($spec));

        return $n;
    }

    /** @return array{step:int, done:int, computed:int}|null */
    private function readMeta(array $spec): ?array
    {
        $path = $this->metaPath($spec);
        if (!is_file($path)) {
            return null;
        }
        $m = json_decode($this->fileStorage->getContents($path), true);

        return is_array($m) && isset($m['step'], $m['done'], $m['computed'])
            ? ['step' => (int)$m['step'], 'done' => (int)$m['done'], 'computed' => (int)$m['computed']]
            : null;
    }

    /** @param array{step:int, done:int, computed:int} $meta */
    private function writeMeta(array $spec, array $meta): void
    {
        $this->fileStorage->safeFileRewrite($this->metaPath($spec), json_encode($meta));
    }

    private function jobDir(): string
    {
        $dir = $this->fileStorage->getStorageFilePath('analysisJobsDir');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }

    /** ジョブ 1 件ぶんのファイル名の共通部分（クエリ × 時間帯） */
    private function stem(array $spec): string
    {
        return $this->jobDir() . '/' . $spec['key'] . '.' . $spec['hour'];
    }

    private function finishedPath(array $spec): string
    {
        return $this->stem($spec) . '.bin';
    }

    private function metaPath(array $spec): string
    {
        return $this->stem($spec) . '.meta';
    }

    private function chunkPath(array $spec, int $i): string
    {
        return $this->stem($spec) . '.c' . sprintf('%02d', $i);
    }

    /**
     * TTL を過ぎた job/lock を掃除（@抑制警告も例外化される環境なので is_file 確認してから）。
     * 計算中のチャンクファイルは、同じジョブの進捗ファイル(.meta)が毎ポーリングで更新されている間は残す
     * （全体で TTL を超える長いジョブでも途中のチャンクを消さない）。
     */
    private function sweepStale(): void
    {
        $now = time();
        $dir = $this->jobDir();
        foreach (glob($dir . '/*') ?: [] as $file) {
            if (!is_file($file)) {
                continue;
            }
            $mtime = filemtime($file);
            if (preg_match('/\.c\d{2}$/', $file)) {
                $meta = preg_replace('/\.c\d{2}$/', '.meta', $file);
                if (is_file($meta)) {
                    $mtime = max($mtime, filemtime($meta));
                }
            }
            if (($now - $mtime) > self::JOB_TTL_SECONDS) {
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
