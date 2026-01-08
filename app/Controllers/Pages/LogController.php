<?php

declare(strict_types=1);

namespace App\Controllers\Pages;

use App\Services\Admin\AdminAuthService;
use Shared\Exceptions\NotFoundException;

/**
 * 管理者用ログ閲覧コントローラー
 *
 * exception.log と各言語のcron.logを閲覧するための管理画面を提供する。
 * 大容量ファイルに対応するため、ファイルシークとキャッシュを使用して効率的に処理する。
 */
class LogController
{
    /** cronログの1ページあたりの表示件数 */
    private const CRON_ITEMS_PER_PAGE = 1000;

    /** 例外ログの1ページあたりの表示件数 */
    private const EXCEPTION_ITEMS_PER_PAGE = 300;

    /** ログファイルのパス定義 */
    private const LOG_FILES = [
        'exception' => '/storage/exception.log',
        'ja-cron' => '/storage/ja/logs/cron.log',
        'th-cron' => '/storage/th/logs/cron.log',
        'tw-cron' => '/storage/tw/logs/cron.log',
    ];

    /**
     * 管理者認証を行う
     * 認証失敗時は404を返す（管理画面の存在を隠すため）
     */
    public function __construct(AdminAuthService $adminAuthService)
    {
        if (!$adminAuthService->auth()) {
            throw new NotFoundException;
        }
    }

    /**
     * ログ選択画面を表示
     * 4種類のログファイルの一覧と状態を表示する
     */
    public function index()
    {
        $logFiles = [];
        foreach (self::LOG_FILES as $key => $path) {
            $fullPath = __DIR__ . '/../../..' . $path;
            $logFiles[$key] = [
                'name' => $key,
                'path' => $path,
                'exists' => file_exists($fullPath),
                'size' => file_exists($fullPath) ? $this->formatBytes(filesize($fullPath)) : 'N/A',
            ];
        }

        return view('admin/log_index', [
            'logFiles' => $logFiles,
        ]);
    }

    /**
     * cronログを表示
     * 日付降順で1000件ずつページング表示する
     *
     * @param string $type ログの種類（ja-cron, th-cron, tw-cron）
     * @param int $page ページ番号
     */
    public function cronLog(string $type, int $page = 1)
    {
        $validTypes = ['ja-cron', 'th-cron', 'tw-cron'];
        if (!in_array($type, $validTypes)) {
            return response('Invalid log type', 400);
        }

        $filePath = __DIR__ . '/../../..' . self::LOG_FILES[$type];
        if (!file_exists($filePath)) {
            return response('Log file not found', 404);
        }

        $result = $this->readCronLogReverse($filePath, $page, self::CRON_ITEMS_PER_PAGE);

        return view('admin/log_cron', [
            'type' => $type,
            'logs' => $result['logs'],
            'currentPage' => $page,
            'totalPages' => $result['totalPages'],
        ]);
    }

    /**
     * 例外ログを表示
     * 日付降順で300件ずつページング表示する
     *
     * @param int $page ページ番号
     */
    public function exceptionLog(int $page = 1)
    {
        $filePath = __DIR__ . '/../../..' . self::LOG_FILES['exception'];
        if (!file_exists($filePath)) {
            return response('Log file not found', 404);
        }

        $result = $this->readExceptionLogReverse($filePath, $page, self::EXCEPTION_ITEMS_PER_PAGE);

        return view('admin/log_exception', [
            'logs' => $result['logs'],
            'currentPage' => $page,
            'totalPages' => $result['totalPages'],
        ]);
    }

    /**
     * 例外ログの詳細を表示
     * 指定されたインデックスの例外エントリの生データを表示する
     *
     * @param int $index エントリのインデックス（0から始まる）
     */
    public function exceptionDetail(int $index)
    {
        $filePath = __DIR__ . '/../../..' . self::LOG_FILES['exception'];
        if (!file_exists($filePath)) {
            return response('Log file not found', 404);
        }

        $entry = $this->getExceptionEntryByIndex($filePath, $index);
        if ($entry === null) {
            return response('Entry not found', 404);
        }

        return view('admin/log_exception_detail', [
            'entry' => $entry,
            'index' => $index,
        ]);
    }

    /**
     * cronログを逆順（新しい順）で読み込む
     * SplFileObjectを使用してファイルを効率的にシークする
     *
     * @param string $filePath ファイルパス
     * @param int $page ページ番号
     * @param int $perPage 1ページあたりの件数
     * @return array ['logs' => ログ配列, 'totalPages' => 総ページ数]
     */
    private function readCronLogReverse(string $filePath, int $page, int $perPage): array
    {
        $file = new \SplFileObject($filePath, 'r');

        // ファイル末尾にシークして総行数を取得
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key();

        if ($totalLines === 0) {
            return ['logs' => [], 'totalPages' => 1];
        }

        $totalPages = (int)ceil($totalLines / $perPage);
        $page = max(1, min($page, $totalPages));

        // 読み込み範囲を計算（末尾からの逆順）
        $startLine = $totalLines - ($page * $perPage);
        $endLine = $totalLines - (($page - 1) * $perPage) - 1;
        $startLine = max(0, $startLine);

        $logs = [];

        // 指定範囲の行を読み込む
        $file->seek($startLine);
        for ($i = $startLine; $i <= $endLine && !$file->eof(); $i++) {
            $line = trim($file->current());
            if ($line !== '') {
                $parsed = $this->parseCronLine($line);
                if ($parsed) {
                    $logs[] = $parsed;
                }
            }
            $file->next();
        }

        // 新しい順に並べ替え
        $logs = array_reverse($logs);

        return [
            'logs' => $logs,
            'totalPages' => $totalPages,
        ];
    }

    /**
     * cronログの1行をパースする
     * 形式: "2025-01-07 05:33:01 メッセージ"
     *
     * @param string $line ログ行
     * @return array|null パース結果、失敗時はnull
     */
    private function parseCronLine(string $line): ?array
    {
        if (preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}) (.+)$/', $line, $matches)) {
            return [
                'date' => $matches[1],
                'message' => $matches[2],
            ];
        }
        return null;
    }

    /**
     * 例外ログを逆順（新しい順）で読み込む
     *
     * @param string $filePath ファイルパス
     * @param int $page ページ番号
     * @param int $perPage 1ページあたりの件数
     * @return array ['logs' => ログ配列, 'totalPages' => 総ページ数]
     */
    private function readExceptionLogReverse(string $filePath, int $page, int $perPage): array
    {
        // エントリ総数を取得（キャッシュ使用）
        $totalEntries = $this->countExceptionEntries($filePath);

        if ($totalEntries === 0) {
            return ['logs' => [], 'totalPages' => 1];
        }

        $totalPages = (int)ceil($totalEntries / $perPage);
        $page = max(1, min($page, $totalPages));

        // 取得するエントリの範囲を計算（末尾からの逆順）
        $skipFromEnd = ($page - 1) * $perPage;
        $startEntry = $totalEntries - $skipFromEnd - $perPage;
        $startEntry = max(0, $startEntry);
        $count = min($perPage, $totalEntries - $skipFromEnd);

        $logs = $this->getExceptionEntries($filePath, $startEntry, $count);

        // 新しい順に並べ替え
        $logs = array_reverse($logs);

        return [
            'logs' => $logs,
            'totalPages' => $totalPages,
        ];
    }

    /**
     * 例外ログのエントリ総数をカウントする
     * 大容量ファイル対応のため、結果をキャッシュする
     * キャッシュはファイルサイズと更新日時で無効化される
     *
     * @param string $filePath ファイルパス
     * @return int エントリ総数
     */
    private function countExceptionEntries(string $filePath): int
    {
        $cacheFile = sys_get_temp_dir() . '/exception_log_count_cache.json';
        $fileSize = filesize($filePath);
        $fileMtime = filemtime($filePath);

        // キャッシュが有効ならそれを使用
        if (file_exists($cacheFile)) {
            $cache = json_decode(file_get_contents($cacheFile), true);
            if ($cache && $cache['size'] === $fileSize && $cache['mtime'] === $fileMtime) {
                return $cache['count'];
            }
        }

        // エントリ数をカウント（日付パターンでエントリ開始を検出）
        $handle = fopen($filePath, 'r');
        $count = 0;

        while (($line = fgets($handle)) !== false) {
            // 形式: "2025-01-30 00:47:29 Asia/Tokyo:"
            if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2} \w+\/\w+:/', $line)) {
                $count++;
            }
        }

        fclose($handle);

        // キャッシュを保存
        file_put_contents($cacheFile, json_encode([
            'size' => $fileSize,
            'mtime' => $fileMtime,
            'count' => $count,
        ]));

        return $count;
    }

    /**
     * 指定範囲の例外エントリを取得する
     *
     * @param string $filePath ファイルパス
     * @param int $startEntry 開始エントリ番号
     * @param int $count 取得件数
     * @return array パース済みエントリの配列
     */
    private function getExceptionEntries(string $filePath, int $startEntry, int $count): array
    {
        $handle = fopen($filePath, 'r');
        $entries = [];
        $currentEntry = -1;
        $currentContent = '';
        $inEntry = false;

        while (($line = fgets($handle)) !== false) {
            // エントリ開始行を検出
            if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2} \w+\/\w+:/', $line)) {
                // 前のエントリが対象範囲内なら保存
                if ($inEntry && $currentEntry >= $startEntry && $currentEntry < $startEntry + $count) {
                    $parsed = $this->parseExceptionEntry($currentContent);
                    $parsed['entryIndex'] = $currentEntry;
                    $entries[] = $parsed;
                }

                $currentEntry++;
                $currentContent = $line;
                $inEntry = true;

                // 必要数を取得したら終了
                if (count($entries) >= $count) {
                    break;
                }
            } elseif ($inEntry) {
                // エントリの続き
                $currentContent .= $line;
            }
        }

        // 最後のエントリを処理
        if ($inEntry && $currentEntry >= $startEntry && $currentEntry < $startEntry + $count && count($entries) < $count) {
            $parsed = $this->parseExceptionEntry($currentContent);
            $parsed['entryIndex'] = $currentEntry;
            $entries[] = $parsed;
        }

        fclose($handle);
        return $entries;
    }

    /**
     * インデックスを指定して例外エントリの生データを取得する
     *
     * @param string $filePath ファイルパス
     * @param int $targetIndex 取得するエントリのインデックス
     * @return string|null エントリの生データ、見つからない場合はnull
     */
    private function getExceptionEntryByIndex(string $filePath, int $targetIndex): ?string
    {
        $handle = fopen($filePath, 'r');
        $currentEntry = -1;
        $currentContent = '';
        $inEntry = false;

        while (($line = fgets($handle)) !== false) {
            if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2} \w+\/\w+:/', $line)) {
                // 目的のエントリを見つけたら返す
                if ($inEntry && $currentEntry === $targetIndex) {
                    fclose($handle);
                    return $currentContent;
                }

                $currentEntry++;
                $currentContent = $line;
                $inEntry = true;
            } elseif ($inEntry) {
                $currentContent .= $line;
            }
        }

        // 最後のエントリをチェック
        if ($inEntry && $currentEntry === $targetIndex) {
            fclose($handle);
            return $currentContent;
        }

        fclose($handle);
        return null;
    }

    /**
     * 例外エントリをパースして構造化データに変換する
     *
     * @param string $content エントリの生データ
     * @return array パース結果（date, url, userAgent, ip, message, raw）
     */
    private function parseExceptionEntry(string $content): array
    {
        $lines = explode("\n", $content);
        $firstLine = $lines[0] ?? '';

        // 日付を抽出
        preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $firstLine, $dateMatch);
        $date = $dateMatch[1] ?? '';

        // 例外メッセージを抽出（通常2行目）
        $exceptionMessage = '';
        if (isset($lines[1])) {
            $exceptionMessage = trim($lines[1]);
        }

        // URL、ユーザーエージェント、IPを抽出
        $url = '';
        $userAgent = '';
        $ip = '';

        if (preg_match('/REQUEST_URI: (.+)/', $content, $match)) {
            $url = trim($match[1]);
        }
        if (preg_match('/HTTP_USER_AGENT: (.+)/', $content, $match)) {
            $userAgent = trim($match[1]);
        }
        if (preg_match('/REMOTE_ADDR: (.+)/', $content, $match)) {
            $ip = trim($match[1]);
        }

        return [
            'date' => $date,
            'url' => $url,
            'userAgent' => $userAgent,
            'ip' => $ip,
            'message' => $exceptionMessage,
            'raw' => $content,
        ];
    }

    /**
     * バイト数を人間が読みやすい形式に変換する
     *
     * @param int $bytes バイト数
     * @return string フォーマット済み文字列（例: "1.5 MB"）
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
