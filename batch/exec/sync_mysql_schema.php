<?php

/**
 * スキーマファイル駆動の冪等・加算 MySQL スキーマ同期 CLI。
 *
 * setup/schema/mysql/*.sql を source of truth とし、全 DB グループ (ocreview / ranking /
 * comment / userlog × locale) に対して、不足テーブル・不足カラム・不足索引を「加算のみ」で
 * 反映する。DROP / MODIFY は一切行わない。デプロイとローカル開発の双方で同じ方法で使う。
 *
 * 使い方:
 *   php batch/exec/sync_mysql_schema.php            # 実反映
 *   php batch/exec/sync_mysql_schema.php --dry-run  # 実行せず DDL を出力するだけ
 *
 * DB 名は AppConfig の static 配列 (本番/stg では local-secrets.php が環境ごとの名前に上書き済。
 * サフィックス命名に限らず言語ごとに任意の名前でよい) を読むだけで解決するため、環境差は自動対応。
 * スキーマファイル名は DB 名とは独立に下の $groups で明示する (Parser が CREATE DATABASE / USE を無視)。
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Config\AppConfig;
use App\Models\Repositories\DB;
use App\Services\Schema\SchemaSyncRunner;

$dryRun = in_array('--dry-run', $argv, true);
$schemaDir = __DIR__ . '/../../setup/schema/mysql';

// 各DBグループの「実DB名」と「スキーマファイル」を定義する。
// - dbNames: AppConfig の配列をそのまま使う。local-secrets が言語ごとに *任意の名前*
//   (サフィックスに限らない) へ上書きしてよい。接続先はこの値をそのまま使う。
// - schema:  各ロケールが使うスキーマファイル名 (リポジトリ内の実ファイル)。DB名とは独立。
//            ファイル名から組み立てず明示することで、DB名の付け方に依存しない。
$groups = [
    'ocreview' => [
        'dbNames' => AppConfig::$dbName,
        'schema'  => ['' => 'ocgraph_ocreview_schema.sql', '/tw' => 'ocgraph_ocreviewtw_schema.sql', '/th' => 'ocgraph_ocreviewth_schema.sql'],
    ],
    'ranking' => [
        'dbNames' => AppConfig::$rankingPositionDbName,
        'schema'  => ['' => 'ocgraph_ranking_schema.sql', '/tw' => 'ocgraph_rankingtw_schema.sql', '/th' => 'ocgraph_rankingth_schema.sql'],
    ],
    'comment' => [
        'dbNames' => AppConfig::$commentDbName,
        'schema'  => ['' => 'ocgraph_comment_schema.sql', '/tw' => 'ocgraph_commenttw_schema.sql', '/th' => 'ocgraph_commentth_schema.sql'],
    ],
    'userlog' => [
        'dbNames' => AppConfig::$userLogDbName,
        'schema'  => ['' => 'ocgraph_userlog_schema.sql', '/tw' => 'ocgraph_userlog_schema.sql', '/th' => 'ocgraph_userlog_schema.sql'],
    ],
];

// runtime DB 名 => スキーマファイル名。AppConfig が定義する実ロケールだけを回すため、
// 将来ロケールが増えても自動で対象になる。キーが DB 名なので同一DB (userlog の locale 共有等) は
// 自然に1件化される。スキーマ対応が無いロケールは黙って飛ばさず警告する。
$targets = [];
foreach ($groups as $group => $cfg) {
    foreach ($cfg['dbNames'] as $loc => $dbName) {
        $file = $cfg['schema'][$loc] ?? null;
        if ($file === null) {
            fwrite(STDERR, "[WARN] {$group}: locale '{$loc}' (db={$dbName}) にスキーマファイルの対応が無いためスキップ\n");
            continue;
        }
        $targets[$dbName] = $file;
    }
}

$runner = new SchemaSyncRunner();
$allWarnings = [];
$failures = [];

if ($dryRun) {
    fwrite(STDOUT, "=== DRY-RUN (no DDL executed) ===\n");
}

foreach ($targets as $dbName => $file) {
    $path = $schemaDir . '/' . $file;
    if (!is_file($path)) {
        fwrite(STDOUT, "Skipped: {$dbName} (schema file {$file} missing)\n");
        continue;
    }
    try {
        $result = $runner->sync($dbName, $path, $dryRun);
        $allWarnings = array_merge($allWarnings, $result->warnings);
    } catch (\Throwable $e) {
        // 1 つの DB の失敗 (ロック待ちタイムアウト等) で他 DB の同期を止めない。
        // 加算・冪等なので、原因解消後に再実行すれば復旧できる。
        $failures[$dbName] = $e->getMessage();
        fwrite(STDERR, "[ERROR] {$dbName}: {$e->getMessage()}\n");
        DB::$pdo = null; // 壊れた接続を次 DB に持ち越さない
    }
}

// ドリフト警告はまとめて最後に表示 (デプロイログで目立たせる)。
// 警告では exit 非ゼロにしない = データ保全・運用継続を優先。
if ($allWarnings !== []) {
    fwrite(STDOUT, "\n=== SCHEMA DRIFT WARNINGS (" . count($allWarnings) . ") ===\n");
    foreach ($allWarnings as $w) {
        fwrite(STDOUT, " - {$w}\n");
    }
}

// 1 つでも失敗があれば非ゼロ終了し、デプロイで気付けるようにする (他 DB は反映済み)。
if ($failures !== []) {
    fwrite(STDERR, "\n=== SCHEMA SYNC FAILURES (" . count($failures) . ") ===\n");
    foreach ($failures as $db => $msg) {
        fwrite(STDERR, " - {$db}: {$msg}\n");
    }
    exit(1);
}
