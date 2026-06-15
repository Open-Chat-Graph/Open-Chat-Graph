<?php

declare(strict_types=1);

namespace App\Services\Analysis;

use App\Models\Repositories\Analysis\AnalysisRoomRepositoryInterface;
use App\Models\Repositories\Analysis\AnalysisStatsRepositoryInterface;
use App\Services\Storage\FileStorageInterface;
use Shared\Exceptions\BadRequestException;

/**
 * 詳細成長分析（/analysis）の重いクエリを「ポーリング駆動のチャンク計算」で実行するサービス。
 *
 * 仕組み（cron・事前計算なし）:
 *  - 検索ごとに open_chat_id レンジを CHUNK_COUNT 分割。status を叩くたびに 1 チャンクだけ集計し、
 *    進捗(%)を返す。最終チャンクで全チャンクをマージ・ソートして結果ファイルに書き出す。
 *  - キャッシュキー = (locale, metric, period/from/to)、さらに毎時更新時刻(hour)でスコープ。
 *    1時間は同じデータなので、結果取得は同一 URL で CDN キャッシュされる（checkLastModified）。
 *  - 「セッションが切れても再計算しない」= 状態と結果はファイルが持つ。2 バッチ目以降や同一検索の
 *    再訪はファイル/CDN から返す（重い計算は (クエリ×時間帯) で 1 回だけ）。
 *  - キャンセル = クライアントがポーリングを止める＝計算が進まない。cancel で中間ファイルも掃除。
 *
 * 結果行は省メモリのため数値配列で保持する（列順は I_* 定数）。
 */
class AdvancedGrowthAnalysisService
{
    /** チャンク分割数（status のポーリング回数 ≒ これ） */
    private const CHUNK_COUNT = 30;

    /** 統計データの開始日（これ以前は from に指定できない） */
    private const DATA_START_DATE = '2023-10-16';

    /** 中間ファイルの寿命（秒）。これより古い job ファイルは掃除する */
    private const STALE_SECONDS = 7200;

    /** 増加率ソート時の最低 base メンバー数（極小規模からの暴騰%ノイズを除外） */
    private const RATE_MIN_BASE = 50;

    // 結果行の列インデックス（共通）
    private const I_ID = 0;
    private const I_CAT = 1;
    // increase 行: [id, cat, diff, pct, base]
    private const I_DIFF = 2;
    private const I_PCT = 3;
    private const I_BASE = 4;
    // steady 行: [id, cat, score, cagr, r2, slope, historyDays]
    private const I_SCORE = 2;
    private const I_CAGR = 3;
    private const I_R2 = 4;
    private const I_SLOPE = 5;
    private const I_HIST = 6;

    public function __construct(
        private AnalysisStatsRepositoryInterface $stats,
        private AnalysisRoomRepositoryInterface $rooms,
        private FileStorageInterface $fileStorage,
    ) {}

    /**
     * 毎時クロール更新時刻（CDN の Last-Modified 兼 時間帯スコープの基準）。
     */
    public function hourlyUpdatedAt(): string
    {
        return trim($this->fileStorage->getContents('@hourlyCronUpdatedAtDatetime'));
    }

    /**
     * status: 1 チャンク分だけ計算を進め、進捗を返す。完了済みなら即 done。
     *
     * @return array{done:bool, percent:int, computed:int, total:?int}
     */
    public function advance(string $metric, string $period, ?string $from, ?string $to): array
    {
        $spec = $this->resolveSpec($metric, $period, $from, $to);
        $this->sweepStale();

        // 既に当該(クエリ×時間帯)の結果が出来ていれば即完了
        $result = $this->fileStorage->getSerializedFile($this->resultPath($spec));
        if (is_array($result)) {
            return ['done' => true, 'percent' => 100, 'computed' => (int)($result['total'] ?? 0), 'total' => (int)($result['total'] ?? 0)];
        }

        $dir = $this->jobDir();
        $lockFp = fopen($dir . '/' . $spec['key'] . '.lock', 'c');
        $gotLock = $lockFp !== false && flock($lockFp, LOCK_EX | LOCK_NB);
        if (!$gotLock) {
            // 別リクエストが計算中。現在の進捗だけ返す（二重計算を避ける）
            if ($lockFp !== false) {
                fclose($lockFp);
            }
            return $this->readProgress($spec);
        }

        try {
            $state = $this->fileStorage->getSerializedFile($this->statePath($spec));
            if (!is_array($state)) {
                $maxId = $this->rooms->getMaxOpenChatId();
                $step = intdiv(max($maxId, 1), self::CHUNK_COUNT) + 1;
                $state = ['maxId' => $maxId, 'step' => $step, 'done' => 0, 'computed' => 0];
            }

            $i = (int)$state['done'];
            $lo = $i * (int)$state['step'];
            $hi = $lo + (int)$state['step'];

            $rows = $this->computeChunk($spec, $lo, $hi);
            $this->fileStorage->saveSerializedFile($this->chunkPath($spec, $i), $rows);

            $state['done'] = $i + 1;
            $state['computed'] = (int)$state['computed'] + count($rows);

            if ($state['done'] >= self::CHUNK_COUNT) {
                $total = $this->finalize($spec);
                return ['done' => true, 'percent' => 100, 'computed' => $total, 'total' => $total];
            }

            $this->fileStorage->saveSerializedFile($this->statePath($spec), $state);
            $percent = (int)floor($state['done'] / self::CHUNK_COUNT * 100);
            return ['done' => false, 'percent' => min($percent, 99), 'computed' => (int)$state['computed'], 'total' => null];
        } finally {
            flock($lockFp, LOCK_UN);
            fclose($lockFp);
        }
    }

    /**
     * result: 完成済み結果をフィルタ・ソート・スライスして表示用に整形して返す。
     * 結果未完成（時間帯ロールオーバー等）なら空配列（クライアントは status を再実行）。
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
        $data = $this->fileStorage->getSerializedFile($this->resultPath($spec));
        if (!is_array($data) || !isset($data['rows'])) {
            return [];
        }

        /** @var array<int, array<int, int|float|null>> $rows */
        $rows = $data['rows'];

        if ($category > 0) {
            $rows = array_values(array_filter($rows, static fn($r) => $r[self::I_CAT] === $category));
        }

        if ($keyword !== '') {
            $ids = array_flip($this->rooms->findIdsByKeyword($keyword));
            $rows = array_values(array_filter($rows, static fn($r) => isset($ids[$r[self::I_ID]])));
        }

        // 増加率ソートは極小規模からの暴騰%（5人→500人=9000%等）に支配されるため base 下限で間引く
        if ($spec['metric'] === 'increase' && $sort === 'rate') {
            $rows = array_values(array_filter($rows, static fn($r) => (int)$r[self::I_BASE] >= self::RATE_MIN_BASE));
        }

        $rows = $this->sortRows($rows, $spec['metric'], $sort, $order);
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

            $item = [
                'id' => $id,
                'name' => $d['name'],
                'desc' => $d['desc'],
                'member' => $d['member'],
                'img' => $d['img'],
                'emblem' => $d['emblem'],
                'joinMethodType' => $d['joinMethodType'],
                'category' => $d['category'],
            ];

            if ($spec['metric'] === 'increase') {
                $item['diff'] = (int)$r[self::I_DIFF];
                $item['pct'] = $r[self::I_PCT] !== null ? (float)$r[self::I_PCT] : null;
                $item['base'] = (int)$r[self::I_BASE];
            } else {
                $item['score'] = (float)$r[self::I_SCORE];
                $item['cagr'] = $r[self::I_CAGR] !== null ? (float)$r[self::I_CAGR] : null;
                $item['r2'] = (float)$r[self::I_R2];
                $item['slope'] = (float)$r[self::I_SLOPE];
                $item['historyDays'] = (int)$r[self::I_HIST];
            }

            if ($page === 0 && $idx === 0) {
                $item['totalCount'] = $total;
            }

            $items[] = $item;
        }

        // page 0 で結果が空でも件数(0)を伝える
        if ($page === 0 && $items === []) {
            return [['totalCount' => 0]];
        }

        return $items;
    }

    /**
     * cancel: 当該(クエリ×時間帯)の中間ファイル・ロックを掃除する。
     * 計算はポーリング停止で自然に止まる（OS プロセスは起動していない）。
     */
    public function cancel(string $metric, string $period, ?string $from, ?string $to): void
    {
        $spec = $this->resolveSpec($metric, $period, $from, $to);
        $this->deleteJobFiles($spec);
    }

    // ───────────────────────── 内部 ─────────────────────────

    /**
     * @return array{metric:string, from:string, to:string, key:string, hour:string}
     */
    private function resolveSpec(string $metric, string $period, ?string $from, ?string $to): array
    {
        $hourly = $this->hourlyUpdatedAt();
        $baseDate = substr($hourly, 0, 10);
        $hour = preg_replace('/[^0-9]/', '', $hourly); // 例: 20260611223000

        if ($metric === 'steady') {
            // period は無関係（全履歴対象）
            $fromDate = '';
            $toDate = '';
        } elseif ($period === 'custom') {
            $fromDate = $this->validDate($from);
            $toDate = ($to !== null && $to !== '') ? $this->validDate($to) : $baseDate;
            if ($fromDate === null) {
                throw new BadRequestException('from は YYYY-MM-DD で指定してください');
            }
            if ($fromDate < self::DATA_START_DATE) {
                $fromDate = self::DATA_START_DATE;
            }
            if ($toDate > $baseDate) {
                $toDate = $baseDate;
            }
            if ($fromDate >= $toDate) {
                throw new BadRequestException('from は to より前の日付にしてください');
            }
        } else { // month / year
            $modifier = $period === 'year' ? '-1 year' : '-1 month';
            $fromDate = date('Y-m-d', strtotime($baseDate . ' ' . $modifier));
            if ($fromDate < self::DATA_START_DATE) {
                $fromDate = self::DATA_START_DATE;
            }
            $toDate = $baseDate;
        }

        $key = substr(hash('sha256', \Shared\MimimalCmsConfig::$urlRoot . '|' . $metric . '|' . $fromDate . '|' . $toDate), 0, 24);

        return [
            'metric' => $metric,
            'from' => $fromDate,
            'to' => $toDate,
            'curIsLatest' => ($toDate === '' || $toDate === $baseDate),
            'key' => $key,
            'hour' => $hour,
        ];
    }

    private function validDate(?string $d): ?string
    {
        if ($d === null || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
            return null;
        }
        // 実在日付かを軽くチェック
        [$y, $m, $day] = array_map('intval', explode('-', $d));
        return checkdate($m, $day, $y) ? $d : null;
    }

    /**
     * 1 チャンク分（open_chat_id ∈ [lo,hi)）の指標を計算し、数値配列の行群で返す。
     *
     * @param array{metric:string, from:string, to:string} $spec
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
            // 終点が最新日(月/年プリセット、または custom で to=最新)なら現在値=open_chat.member を使い、
            // 全履歴を走査する as-of クエリをもう1本省く（性能）。custom で過去 to のときだけ実取得。
            $curMap = $spec['curIsLatest'] ? null : $this->stats->getMemberAsOf($lo, $hi, $spec['to']);
            foreach ($base as $id => $bm) {
                if (!isset($rooms[$id])) {
                    continue; // 現在は存在しない部屋を除外
                }
                $cur = $curMap === null ? $rooms[$id]['member'] : ($curMap[$id] ?? null);
                if ($cur === null) {
                    continue; // custom で終点にデータが無い部屋は除外
                }
                $inc = GrowthMath::periodIncrease($bm, $cur);
                $out[] = [$id, $rooms[$id]['category'], $inc['diff'], $inc['pct'], $bm];
            }
        } else { // steady
            $aggs = $this->stats->getSteadyAggregates($lo, $hi);
            foreach ($aggs as $id => $agg) {
                if (!isset($rooms[$id])) {
                    continue;
                }
                $s = GrowthMath::steady($agg, $rooms[$id]['member']);
                if ($s === null) {
                    continue;
                }
                $out[] = [$id, $rooms[$id]['category'], $s['score'], $s['cagr'], $s['r2'], $s['slope'], $s['historyDays']];
            }
        }

        return $out;
    }

    /**
     * 全チャンクをマージ・主指標で降順ソートして結果ファイルに書き出す。中間ファイルは削除。
     *
     * @param array{metric:string, key:string, hour:string} $spec
     * @return int 件数
     */
    private function finalize(array $spec): int
    {
        $all = [];
        for ($i = 0; $i < self::CHUNK_COUNT; $i++) {
            $chunk = $this->fileStorage->getSerializedFile($this->chunkPath($spec, $i));
            if (is_array($chunk)) {
                foreach ($chunk as $row) {
                    $all[] = $row;
                }
            }
        }

        $primary = $spec['metric'] === 'increase' ? self::I_DIFF : self::I_SCORE;
        usort($all, static fn($a, $b) => ($b[$primary] <=> $a[$primary]));

        $this->fileStorage->saveSerializedFile($this->resultPath($spec), ['rows' => $all, 'total' => count($all)]);
        $this->deleteJobFiles($spec, keepResult: true);

        return count($all);
    }

    /**
     * @param array<int, array<int, int|float|null>> $rows
     * @return array<int, array<int, int|float|null>>
     */
    private function sortRows(array $rows, string $metric, string $sort, string $order): array
    {
        if ($metric === 'increase') {
            $col = $sort === 'rate' ? self::I_PCT : self::I_DIFF; // 既定 count
        } else {
            $col = match ($sort) {
                'cagr' => self::I_CAGR,
                'slope' => self::I_SLOPE,
                default => self::I_SCORE,
            };
        }

        $primary = $metric === 'increase' ? self::I_DIFF : self::I_SCORE;
        // 結果ファイルは主指標 desc で保存済み。同条件なら再ソート不要
        if ($col === $primary && $order === 'desc') {
            return $rows;
        }

        if ($order === 'asc') {
            usort($rows, static fn($a, $b) => ($a[$col] <=> $b[$col]));
        } else {
            usort($rows, static fn($a, $b) => ($b[$col] <=> $a[$col]));
        }

        return $rows;
    }

    private function readProgress(array $spec): array
    {
        $state = $this->fileStorage->getSerializedFile($this->statePath($spec));
        if (!is_array($state)) {
            return ['done' => false, 'percent' => 0, 'computed' => 0, 'total' => null];
        }
        $percent = (int)floor((int)$state['done'] / self::CHUNK_COUNT * 100);
        return ['done' => false, 'percent' => min($percent, 99), 'computed' => (int)($state['computed'] ?? 0), 'total' => null];
    }

    private function jobDir(): string
    {
        $dir = $this->fileStorage->getStorageFilePath('analysisJobsDir');
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        return $dir;
    }

    private function statePath(array $spec): string
    {
        return $this->jobDir() . '/' . $spec['key'] . '.' . $spec['hour'] . '.state';
    }

    private function chunkPath(array $spec, int $i): string
    {
        return $this->jobDir() . '/' . $spec['key'] . '.' . $spec['hour'] . '.chunk' . $i;
    }

    private function resultPath(array $spec): string
    {
        return $this->jobDir() . '/' . $spec['key'] . '.' . $spec['hour'] . '.result';
    }

    private function deleteJobFiles(array $spec, bool $keepResult = false): void
    {
        $base = $this->jobDir() . '/' . $spec['key'] . '.' . $spec['hour'];
        // .lock は意図的に消さない（保持中ハンドルとの競合回避。sweepStale が後で掃除する）
        $this->safeUnlink($base . '.state');
        for ($i = 0; $i < self::CHUNK_COUNT; $i++) {
            $this->safeUnlink($base . '.chunk' . $i);
        }
        if (!$keepResult) {
            $this->safeUnlink($base . '.result');
        }
    }

    private function sweepStale(): void
    {
        $dir = $this->jobDir();
        $now = time();
        foreach (glob($dir . '/*') ?: [] as $file) {
            // この環境は @ 抑制警告も例外化されるため is_file で存在確認してから操作する
            if (is_file($file) && ($now - filemtime($file)) > self::STALE_SECONDS) {
                $this->safeUnlink($file);
            }
        }
    }

    /** この環境は @ 抑制警告も例外化されるため、存在確認してから unlink する */
    private function safeUnlink(string $path): void
    {
        if (is_file($path)) {
            unlink($path);
        }
    }
}
