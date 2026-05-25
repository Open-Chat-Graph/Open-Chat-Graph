<?php

/**
 * OcSitemapLastmodRepository / sitemap 読み取りクエリの結合テスト
 * (ローカル MariaDB に使い捨て DB を作り、実 SQL を検証する)
 *
 * 実行コマンド:
 * docker compose exec app vendor/bin/phpunit app/Services/Sitemap/test/OcSitemapLastmodRepositoryTest.php
 *
 * setUp で `ocgraph_lastmod_test_<random>` を CREATE DATABASE し、最小の open_chat と
 * oc_sitemap_lastmod を作る。tearDown で DROP。実データの DB には一切触れない。
 * DB::connect() は既存接続を再利用するため、使い捨て DB に接続した状態で
 * リポジトリの内部 DB::connect() も同じ接続を使う。
 *
 * 前提: 接続ユーザに CREATE/DROP DATABASE 権限があること (ローカルは root)。
 */

declare(strict_types=1);

use App\Models\Repositories\DB;
use App\Models\Repositories\OcSitemapLastmodRepository;
use App\Models\Repositories\OpenChatListRepository;
use App\Services\Sitemap\LastmodPolicy;
use PHPUnit\Framework\TestCase;

class OcSitemapLastmodRepositoryTest extends TestCase
{
    private string $testDb;
    private OcSitemapLastmodRepository $repo;

    protected function setUp(): void
    {
        $this->testDb = 'ocgraph_lastmod_test_' . bin2hex(random_bytes(6));
        $this->repo = new OcSitemapLastmodRepository();

        DB::$pdo = null;
        DB::connect(['dbName' => 'information_schema']);
        DB::execute("CREATE DATABASE `{$this->testDb}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        // 以降のクエリは全てこの使い捨て DB に対して実行する
        DB::$pdo = null;
        DB::connect(['dbName' => $this->testDb]);

        // refreshLastmod / sitemap クエリが参照する列だけを持つ最小の open_chat
        DB::execute(
            "CREATE TABLE `open_chat` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `member` int(11) NOT NULL,
                `updated_at` datetime NOT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        DB::execute(
            "CREATE TABLE `oc_sitemap_lastmod` (
                `open_chat_id` int(11) NOT NULL,
                `lastmod` datetime NOT NULL,
                `member_snapshot` int(11) NOT NULL,
                PRIMARY KEY (`open_chat_id`),
                KEY `lastmod` (`lastmod`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    protected function tearDown(): void
    {
        DB::$pdo = null;
        DB::connect(['dbName' => 'information_schema']);
        DB::execute("DROP DATABASE IF EXISTS `{$this->testDb}`");
        DB::$pdo = null;
    }

    private function lastmodOf(int $id): string
    {
        return (string) DB::execute(
            "SELECT lastmod FROM oc_sitemap_lastmod WHERE open_chat_id = ?",
            [$id],
        )->fetchColumn();
    }

    /**
     * 部屋を1件だけ隔離して用意し「現在人数 $current・前回 snapshot $snapshot・
     * lastmod は過去の固定値」を作って refresh を1回流し、lastmod が動いた(=bumpされた)か返す。
     * updated_at は lastmod より古くして「メタ変更なし＝人数差分だけで判定」させる。
     */
    private function memberChangeBumps(int $snapshot, int $current): bool
    {
        $sentinel = '2000-01-01 00:00:00';
        DB::execute("DELETE FROM open_chat");
        DB::execute("DELETE FROM oc_sitemap_lastmod");
        DB::execute("INSERT INTO open_chat (id, member, updated_at) VALUES (1, ?, '1999-01-01 00:00:00')", [$current]);
        DB::execute("INSERT INTO oc_sitemap_lastmod (open_chat_id, lastmod, member_snapshot) VALUES (1, ?, ?)", [$sentinel, $snapshot]);

        $this->repo->refreshLastmod();

        return $this->lastmodOf(1) !== $sentinel;
    }

    public function test_first_run_populates_all_rooms_with_member_snapshot(): void
    {
        DB::execute("INSERT INTO open_chat (id, member, updated_at) VALUES (1, 100, '2026-01-01 00:00:00'), (2, 200, '2026-02-02 00:00:00')");

        $this->repo->refreshLastmod();

        $this->assertSame(2, (int) DB::execute("SELECT COUNT(*) FROM oc_sitemap_lastmod")->fetchColumn());
        $this->assertSame(100, (int) DB::execute("SELECT member_snapshot FROM oc_sitemap_lastmod WHERE open_chat_id = 1")->fetchColumn());

        // 直後の再実行は何も変わらない (冪等)
        $this->assertSame(0, $this->repo->refreshLastmod());
    }

    /**
     * 人数差分の significant 判定が LastmodPolicy と完全に一致すること。
     * 閾値式が SQL と PHP の2箇所に存在するため、両者の境界一致をこのテストで担保する。
     */
    public function test_member_change_threshold_matches_LastmodPolicy(): void
    {
        $cases = [
            [50, 54],    // +4  小部屋: 下限5未満 → bumpしない
            [50, 55],    // +5  ちょうど下限 → bump
            [50, 45],    // -5  減少も ABS で bump
            [50, 46],    // -4  → bumpしない
            [9000, 9089], // +89 大部屋: 1%(90)未満 → bumpしない
            [9000, 9090], // +90 ちょうど1% → bump
            [0, 4],      // snapshot0: 下限5 → bumpしない
            [0, 5],      // → bump
            [1234, 1234], // 無変化 → bumpしない
        ];

        foreach ($cases as [$snapshot, $current]) {
            $bumped = $this->memberChangeBumps($snapshot, $current);
            $expected = LastmodPolicy::isSignificantMemberChange($snapshot, $current);
            $this->assertSame(
                $expected,
                $bumped,
                "SQL の判定が LastmodPolicy と不一致: snapshot={$snapshot} current={$current}",
            );
        }
    }

    public function test_significant_change_updates_snapshot_to_current(): void
    {
        DB::execute("INSERT INTO open_chat (id, member, updated_at) VALUES (1, 5000, '1999-01-01 00:00:00')");
        DB::execute("INSERT INTO oc_sitemap_lastmod (open_chat_id, lastmod, member_snapshot) VALUES (1, '2000-01-01 00:00:00', 100)");

        // 100 → 5000 は significant。bump 後は snapshot が現在人数に是正される
        $this->repo->refreshLastmod();

        $this->assertNotSame('2000-01-01 00:00:00', $this->lastmodOf(1));
        $this->assertSame(5000, (int) DB::execute("SELECT member_snapshot FROM oc_sitemap_lastmod WHERE open_chat_id = 1")->fetchColumn());
    }

    /**
     * 人数が1人も動かなくても、updated_at(メタ情報)が lastmod より新しければ bump される。
     * = COALESCE が「古い人数変動の時刻」に固定されることはない。
     */
    public function test_metadata_change_bumps_lastmod_even_with_no_member_change(): void
    {
        // 人数は 100 で固定 (snapshot も 100)。updated_at だけ lastmod より新しい。
        DB::execute("INSERT INTO open_chat (id, member, updated_at) VALUES (1, 100, '2026-05-10 00:00:00')");
        DB::execute("INSERT INTO oc_sitemap_lastmod (open_chat_id, lastmod, member_snapshot) VALUES (1, '2026-05-01 00:00:00', 100)");

        $this->repo->refreshLastmod();

        $after = $this->lastmodOf(1);
        $this->assertNotSame('2026-05-01 00:00:00', $after, '人数無変化でもメタ変更で lastmod が動くべき');
        // CURRENT_TIMESTAMP (今日) まで進む = 古い時刻に張り付かない
        $this->assertGreaterThan('2026-05-10 00:00:00', $after);
    }

    /**
     * sitemap 読み取り (getOpenChatSiteMapData) は専用テーブルの lastmod を優先し、
     * 未登録の部屋は従来の open_chat.updated_at にフォールバックする。
     */
    public function test_sitemap_query_prefers_tracked_lastmod_and_falls_back(): void
    {
        // id=1: 専用テーブルに登録あり → その lastmod を返す
        DB::execute("INSERT INTO open_chat (id, member, updated_at) VALUES (1, 100, '2020-01-01 00:00:00')");
        DB::execute("INSERT INTO oc_sitemap_lastmod (open_chat_id, lastmod, member_snapshot) VALUES (1, '2026-05-24 19:00:00', 100)");
        // id=2: 未登録 → updated_at にフォールバック
        DB::execute("INSERT INTO open_chat (id, member, updated_at) VALUES (2, 50, '2026-03-03 12:00:00')");

        $rows = (new OpenChatListRepository())->getOpenChatSiteMapData();
        $byId = array_column($rows, 'updated_at', 'id');

        $this->assertSame('2026-05-24 19:00:00', $byId[1]);
        $this->assertSame('2026-03-03 12:00:00', $byId[2]);
    }
}
