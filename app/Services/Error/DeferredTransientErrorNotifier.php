<?php

declare(strict_types=1);

namespace App\Services\Error;

use App\Services\Admin\AdminTool;

/**
 * Web リクエストで発生した一過性DBエラー(TransientDatabaseException)を、リアルタイムではなく
 * 「10件たまったらまとめて1通」Discord 通知するためのバッファ。
 *
 * 過負荷スパイク時は同じエラー(接続枯渇・SQLiteロック)が大量に出るため、1件ずつ通知すると
 * スマホが鳴り止まない。かといって握り潰すと気づけない。そこで storage/transient_errors.queue に
 * 1行ずつ追記し、10件に達した時点で MD リストとして送る(送信後はクリア)。複数の php-fpm
 * ワーカーが同時に書くため flock で直列化する。
 *
 * 例外ログ(exception.log)への記録はハンドラ側(ApplicationExceptionHandler)が別途必ず行う。
 * 本クラスは「通知の間引き」専用で、ログの代替ではない。
 */
class DeferredTransientErrorNotifier implements DeferredTransientErrorNotifierInterface
{
    /** この件数に達したらまとめて送信する */
    private const THRESHOLD = 10;

    /** 1メッセージが Discord 上限(2000字)を超えないよう、1行の最大長を抑える */
    private const MAX_LINE_WIDTH = 200;

    private string $queueFile;
    private string $lockFile;

    public function __construct(?string $queueFile = null)
    {
        // locale 非依存の固定パス(exception.log と同じルート)。php-fpm/cron 双方が書ける。
        $this->queueFile = $queueFile ?? __DIR__ . '/../../../storage/transient_errors.queue';
        $this->lockFile = $this->queueFile . '.lock';
    }

    public function record(\Throwable $e): void
    {
        $line = $this->formatLine($e);

        $toSend = [];
        $this->withLock(function () use ($line, &$toSend) {
            $lines = $this->readLines();
            $lines[] = $line;

            if (count($lines) >= self::THRESHOLD) {
                // ロック下では「抜き取り＋クリア」までで止め、ファイルI/Oだけに占有時間を限定する
                $toSend = $lines;
                $this->writeLines([]);
                return;
            }

            $this->writeLines($lines);
        });

        $this->sendOutsideLock($toSend);
    }

    public function flush(): void
    {
        $toSend = [];
        $this->withLock(function () use (&$toSend) {
            $lines = $this->readLines();
            if ($lines === []) {
                return;
            }
            $toSend = $lines;
            $this->writeLines([]);
        });

        $this->sendOutsideLock($toSend);
    }

    /**
     * Discord 送信(ネットワークI/O)はロックの外で行う。
     *
     * ロックを握ったまま Discord への curl を待つと、過負荷スパイク時に後続ワーカーが
     * flock(LOCK_EX) で芋づる式に直列ブロックし、503 を素早く返すどころか二次的な
     * ワーカー枯渇を招く。送信は best-effort（失敗しても正本は exception.log に残る）。
     *
     * @param string[] $lines
     */
    private function sendOutsideLock(array $lines): void
    {
        if ($lines === []) {
            return;
        }
        $this->send($lines);
    }

    /**
     * 1行 = "- {日時} | {元例外クラス}: {メッセージ1行} | {UA}"
     *
     * 元の \PDOException を $previous に連結しているので、可能なら元例外のクラス/メッセージを使う
     * (TransientDatabaseException ではなく PDOException として記録され、原因コードが読み取れる)。
     */
    private function formatLine(\Throwable $e): string
    {
        $origin = $e->getPrevious() ?? $e;
        $class = (new \ReflectionClass($origin))->getShortName();
        $message = (string) preg_replace('/\s+/', ' ', trim($origin->getMessage()));
        $message = mb_strimwidth($message, 0, self::MAX_LINE_WIDTH, '…');
        $ua = (string) preg_replace('/\s+/', ' ', trim(getUA()));
        $time = date('Y-m-d H:i:s');

        return "- {$time} | {$class}: {$message} | {$ua}";
    }

    /**
     * @param string[] $lines
     */
    protected function send(array $lines): void
    {
        $header = count($lines) . '件の一過性DBエラー(Webアクセス):' . "\n";
        AdminTool::sendDiscordNotify($header . implode("\n", $lines));
    }

    /**
     * @return string[]
     */
    private function readLines(): array
    {
        if (!is_file($this->queueFile)) {
            return [];
        }
        $content = file_get_contents($this->queueFile);
        if ($content === false || $content === '') {
            return [];
        }
        return array_values(array_filter(explode("\n", $content), static fn ($l) => $l !== ''));
    }

    /**
     * @param string[] $lines
     */
    private function writeLines(array $lines): void
    {
        file_put_contents($this->queueFile, $lines === [] ? '' : implode("\n", $lines) . "\n");
    }

    private function withLock(callable $fn): void
    {
        $fp = fopen($this->lockFile, 'c');
        if ($fp === false) {
            // ロックファイルすら開けない環境では通知を諦める(本処理=リクエストは止めない)
            return;
        }
        try {
            if (!flock($fp, LOCK_EX)) {
                return;
            }
            try {
                $fn();
            } finally {
                flock($fp, LOCK_UN);
            }
        } finally {
            fclose($fp);
        }
    }
}
