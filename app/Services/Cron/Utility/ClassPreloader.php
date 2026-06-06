<?php

declare(strict_types=1);

namespace App\Services\Cron\Utility;

use App\Config\AppConfig;

/**
 * 長時間バッチ用のクラス先読み。
 *
 * cron 実行中にデプロイが走ると、開始時に読み込み済みの旧クラスと実行途中で
 * 遅延オートロードされる新クラスが同一プロセス内に混在し、シグネチャ変更を
 * 跨いだ呼び出しが ArgumentCountError 等で落ちる（実例: 2026-06-06 17:50、
 * 17:30 開始の毎時処理に 17:33 のデプロイが重なり、旧 BlogService が
 * 新 BlogSummaryDto を引数不足で生成してサイトマップ生成が中断）。
 *
 * 開始時点で自前コード（app/shared/shadow）の全クラスを読み込み、プロセス内の
 * コードを開始時点のスナップショットに固定して混在を防ぐ。
 *
 * リンク不能なクラス（インターフェース実装漏れ等）の読み込みは try/catch で
 * 捕捉できない compile-time fatal になるため、いきなり本プロセスへは読み込まず、
 * サブプロセスで全クラスの読み込みを検証し、通ったものだけを本プロセスへ読み込む。
 * 検証に落ちたクラスは従来どおり遅延ロードに任せる（使われた時点で同じ場所で落ちる
 * だけで、バッチ全体は巻き込まない）。
 *
 * composer install --optimize-autoloader（本番デプロイが実行）で生成される
 * classmap を前提とし、無ければ何もしない（開発環境では従来どおり遅延ロード）。
 */
class ClassPreloader
{
    /** リンク不能クラスの除外リトライ上限（通常は壊れたクラス数 + 1 回で収束する） */
    private const MAX_PROBE_ATTEMPTS = 10;

    /**
     * @return int 読み込んだクラス数（classmap が無効・検証が収束しない場合は 0 で、従来の遅延ロードに任せる）
     */
    public static function preload(): int
    {
        try {
            $classes = self::ownClasses();
            if (!$classes) {
                return 0;
            }

            $verified = self::probeInSubprocess($classes);

            $count = 0;
            foreach ($verified as $class) {
                try {
                    // interface / trait / enum でもオートロードは発火する
                    class_exists($class);
                    $count++;
                } catch (\Throwable) {
                    // サブプロセス検証後にデプロイでファイルが入れ替わった等の稀なケース。
                    // 先読みは best-effort とし、バッチ本体は止めない
                }
            }

            return $count;
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * サブプロセスで classmap の自前クラスを順に読み込み、fatal にならず読めたものだけを返す。
     * fatal で落ちた場合は、その直前まで出力されたクラス名から壊れたクラスを特定して除外し再試行する。
     *
     * @param string[] $classes
     * @return string[] 本プロセスへ安全に読み込めるクラス名
     */
    private static function probeInSubprocess(array $classes): array
    {
        $autoload = AppConfig::ROOT_PATH . 'vendor/autoload.php';
        $code = sprintf("require %s; %s::probe(\$argv[1] ?? '');", var_export($autoload, true), self::class);

        $skip = [];
        for ($attempt = 0; $attempt < self::MAX_PROBE_ATTEMPTS; $attempt++) {
            $candidates = array_values(array_diff($classes, $skip));
            if (!$candidates) {
                return [];
            }

            $command = escapeshellarg(PHP_BINARY)
                . ' -d display_errors=0 -r ' . escapeshellarg($code)
                . ' -- ' . escapeshellarg(implode(',', $skip)) . ' 2>/dev/null';

            $output = [];
            $exitCode = 1;
            exec($command, $output, $exitCode);

            // 出力はクラス名のみのはずだが、fatal 時のハンドラ出力等の混入に備え
            // 「期待順と一致する先頭部分」だけを成功とみなす
            $verified = [];
            foreach ($output as $i => $line) {
                if (($candidates[$i] ?? null) !== trim($line)) {
                    break;
                }
                $verified[] = $candidates[$i];
            }

            if ($exitCode === 0 && count($verified) === count($candidates)) {
                return $verified;
            }

            // 最後に成功したクラスの次が fatal の原因。進捗が無い異常時は打ち切り
            $broken = $candidates[count($verified)] ?? null;
            if ($broken === null) {
                return [];
            }
            $skip[] = $broken;
        }

        return [];
    }

    /**
     * サブプロセス側のエントリ。読み込みに成功したクラス名を順に出力する（preload() からは呼ばない）。
     *
     * @param string $skipCsv 除外クラス名のカンマ区切り
     */
    public static function probe(string $skipCsv): void
    {
        $skip = $skipCsv === '' ? [] : explode(',', $skipCsv);

        foreach (array_diff(self::ownClasses(), $skip) as $class) {
            try {
                class_exists($class);
                echo $class, "\n";
            } catch (\Throwable) {
                // 捕捉可能な失敗は出力しない → 親プロセス側で除外される
            }
        }
    }

    /**
     * classmap から自前コード（app/shared/shadow）のクラスのみ抽出する。
     * vendor は対象外: デプロイで変わる頻度が低く、未使用の拡張依存クラス等を
     * 巻き込むリスクの方が大きい。
     *
     * @return string[]
     */
    private static function ownClasses(): array
    {
        $classmapPath = AppConfig::ROOT_PATH . 'vendor/composer/autoload_classmap.php';
        if (!is_file($classmapPath)) {
            return [];
        }

        $classmap = include $classmapPath;
        if (!is_array($classmap)) {
            return [];
        }

        $targets = array_filter([
            realpath(AppConfig::ROOT_PATH . 'app'),
            realpath(AppConfig::ROOT_PATH . 'shared'),
            realpath(AppConfig::ROOT_PATH . 'shadow'),
        ]);

        $classes = [];
        foreach ($classmap as $class => $file) {
            $path = realpath($file);
            if ($path === false) {
                continue;
            }

            foreach ($targets as $dir) {
                if (str_starts_with($path, $dir . DIRECTORY_SEPARATOR)) {
                    $classes[] = $class;
                    break;
                }
            }
        }

        return $classes;
    }
}
