<?php

declare(strict_types=1);

namespace App\Services\Analysis;

use App\Models\Repositories\Analysis\AnalysisRoomRepositoryInterface;
use App\Models\Repositories\Analysis\AnalysisStatsRepositoryInterface;
use App\Services\Storage\FileStorageInterface;
use Shared\Exceptions\BadRequestException;

/**
 * 詳細成長分析（/analysis）。重いが、その場で計算して返すだけ（サーバに一切保存しない）。
 *
 * - 1 リクエストで全部屋を集計→絞り込み→並び替え→ページ切り出し→表示用に整形して返す。
 * - 毎時更新なので同一 URL クエリは CDN がキャッシュする（コントローラの checkLastModified）。
 *   つまり「計算するのは (条件×時間帯) ごとに 1 回」で、以降は CDN が返す。中間ファイル・ジョブ状態は持たない。
 *
 * 行は省メモリのため数値配列で持つ（列順は I_* 定数）。
 */
class AdvancedGrowthAnalysisService
{
    /** 集計を分割実行する単位（1 クエリの IN レンジを小さく保つ。進捗用ではない） */
    private const CHUNK_COUNT = 30;

    /** 統計データの開始日（これ以前は from に指定できない） */
    private const DATA_START_DATE = '2023-10-16';

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
     * その場で全件計算し、絞り込み・並び替え・ページ切り出し・整形して返す。
     *
     * @return array<int, array<string, mixed>> 先頭要素に totalCount を載せる（page 0 のとき）
     */
    public function search(
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
        $isIncrease = $spec['metric'] === 'increase';

        $rows = $this->computeAll($spec);

        if ($category > 0) {
            $rows = array_values(array_filter($rows, static fn($r) => $r[self::I_CAT] === $category));
        }
        if ($keyword !== '') {
            $ids = array_flip($this->rooms->findIdsByKeyword($keyword));
            $rows = array_values(array_filter($rows, static fn($r) => isset($ids[$r[self::I_ID]])));
        }
        // 増加率ソートは極小規模からの暴騰%に支配されるため base 下限で間引く
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

    /**
     * 全部屋を期間窓で集計し、主指標の降順でソートした行配列を返す（その場限り・非保存）。
     *
     * @return array<int, array<int, int|float|null>>
     */
    private function computeAll(array $spec): array
    {
        $maxId = $this->rooms->getMaxOpenChatId();
        $step = intdiv(max($maxId, 1), self::CHUNK_COUNT) + 1;

        $rows = [];
        for ($i = 0; $i < self::CHUNK_COUNT; $i++) {
            $lo = $i * $step;
            $hi = $lo + $step;
            foreach ($this->computeChunk($spec, $lo, $hi) as $row) {
                $rows[] = $row;
            }
        }

        $primary = $spec['metric'] === 'increase' ? self::I_DIFF : self::I_SCORE;
        usort($rows, static fn($a, $b) => ($b[$primary] <=> $a[$primary]));

        return $rows;
    }

    /**
     * @return array{metric:string, from:string, to:string, windowDays:int, curIsLatest:bool}
     */
    private function resolveSpec(string $metric, string $period, ?string $from, ?string $to): array
    {
        $baseDate = substr($this->hourlyUpdatedAt(), 0, 10);

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
            // 終点が最新日なら現在値=open_chat.member を使い as-of クエリを 1 本省く（性能）
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

        // computeAll が主指標 desc で返すので、既定（主指標・降順）ならそのまま
        if ($col === $primary && $order === 'desc') {
            return $rows;
        }

        usort($rows, $order === 'asc'
            ? static fn($a, $b) => ($a[$col] <=> $b[$col])
            : static fn($a, $b) => ($b[$col] <=> $a[$col]));

        return $rows;
    }
}
