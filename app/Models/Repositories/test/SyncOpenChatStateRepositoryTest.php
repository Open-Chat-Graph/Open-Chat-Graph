<?php

/**
 * SyncOpenChatStateRepository のテスト
 *
 * 実行コマンド:
 * docker compose exec app ./vendor/bin/phpunit app/Models/Repositories/test/SyncOpenChatStateRepositoryTest.php
 *
 * テスト内容:
 * - INSERT ON DUPLICATE KEY UPDATE による自動レコード作成
 * - getBool/setTrue/setFalse の動作
 * - getString/setString の動作
 * - レコードが存在しない場合の挙動
 */

use PHPUnit\Framework\TestCase;
use App\Models\Repositories\SyncOpenChatStateRepository;
use App\Models\Repositories\SyncOpenChatStateRepositoryInterface;
use App\Services\Cron\Enum\SyncOpenChatStateType;
use App\Models\Repositories\DB;

class SyncOpenChatStateRepositoryTest extends TestCase
{
    private SyncOpenChatStateRepository $repository;
    private array $originalValues = [];

    protected function setUp(): void
    {
        DB::connect();
        $this->repository = app(SyncOpenChatStateRepositoryInterface::class);

        // 既存のEnum値の元の値をバックアップ
        $this->originalValues['bool'] = [
            'type' => SyncOpenChatStateType::persistMemberStatsLastDate,
            'bool' => $this->repository->getBool(SyncOpenChatStateType::persistMemberStatsLastDate),
            'string' => $this->repository->getString(SyncOpenChatStateType::persistMemberStatsLastDate),
        ];

        $this->originalValues['string'] = [
            'type' => SyncOpenChatStateType::filterCacheDate,
            'bool' => $this->repository->getBool(SyncOpenChatStateType::filterCacheDate),
            'string' => $this->repository->getString(SyncOpenChatStateType::filterCacheDate),
        ];
    }

    protected function tearDown(): void
    {
        // 元の値に戻す
        foreach ($this->originalValues as $data) {
            if ($data['bool']) {
                $this->repository->setTrue($data['type']);
            } else {
                $this->repository->setFalse($data['type']);
            }

            if ($data['string'] !== null) {
                $this->repository->setString($data['type'], $data['string']);
            }
        }
    }

    // ========================================
    // getBool / setTrue / setFalse のテスト
    // ========================================

    public function test_getBool_returns_false_when_record_does_not_exist(): void
    {
        // レコードが存在しない場合、falseを返す（既存レコードはクリア）
        $type = SyncOpenChatStateType::persistMemberStatsLastDate;

        // レコードを削除
        DB::execute("DELETE FROM sync_open_chat_state WHERE type = :type", ['type' => $type->value]);

        $result = $this->repository->getBool($type);
        $this->assertFalse($result);
    }

    public function test_setTrue_creates_record_automatically(): void
    {
        $type = SyncOpenChatStateType::persistMemberStatsLastDate;

        // レコードを削除
        DB::execute("DELETE FROM sync_open_chat_state WHERE type = :type", ['type' => $type->value]);

        // レコードが存在しなくても自動作成される
        $this->repository->setTrue($type);

        // DBに保存されていることを確認
        $result = DB::fetchColumn(
            "SELECT bool FROM sync_open_chat_state WHERE type = :type",
            ['type' => $type->value]
        );

        $this->assertSame(1, (int)$result);
    }

    public function test_setFalse_creates_record_automatically(): void
    {
        $type = SyncOpenChatStateType::persistMemberStatsLastDate;

        // レコードを削除
        DB::execute("DELETE FROM sync_open_chat_state WHERE type = :type", ['type' => $type->value]);

        // レコードが存在しなくても自動作成される
        $this->repository->setFalse($type);

        // DBに保存されていることを確認
        $result = DB::fetchColumn(
            "SELECT bool FROM sync_open_chat_state WHERE type = :type",
            ['type' => $type->value]
        );

        $this->assertSame(0, (int)$result);
    }

    public function test_setTrue_updates_existing_record(): void
    {
        $type = SyncOpenChatStateType::persistMemberStatsLastDate;

        // 先にfalseをセット
        $this->repository->setFalse($type);
        $this->assertFalse($this->repository->getBool($type));

        // trueに更新
        $this->repository->setTrue($type);
        $this->assertTrue($this->repository->getBool($type));
    }

    public function test_setFalse_updates_existing_record(): void
    {
        $type = SyncOpenChatStateType::persistMemberStatsLastDate;

        // 先にtrueをセット
        $this->repository->setTrue($type);
        $this->assertTrue($this->repository->getBool($type));

        // falseに更新
        $this->repository->setFalse($type);
        $this->assertFalse($this->repository->getBool($type));
    }

    public function test_getBool_returns_correct_value(): void
    {
        $type = SyncOpenChatStateType::persistMemberStatsLastDate;

        // true
        $this->repository->setTrue($type);
        $this->assertTrue($this->repository->getBool($type));

        // false
        $this->repository->setFalse($type);
        $this->assertFalse($this->repository->getBool($type));
    }

    // ========================================
    // getString / setString のテスト
    // ========================================

    public function test_getString_returns_null_when_record_does_not_exist(): void
    {
        $type = SyncOpenChatStateType::filterCacheDate;

        // レコードを削除
        DB::execute("DELETE FROM sync_open_chat_state WHERE type = :type", ['type' => $type->value]);

        $result = $this->repository->getString($type);
        $this->assertNull($result);
    }

    public function test_getString_returns_null_when_extra_is_empty(): void
    {
        $type = SyncOpenChatStateType::filterCacheDate;

        // 空文字でレコード作成
        DB::execute("DELETE FROM sync_open_chat_state WHERE type = :type", ['type' => $type->value]);
        DB::execute(
            "INSERT INTO sync_open_chat_state (type, bool, extra) VALUES (:type, 0, '')",
            ['type' => $type->value]
        );

        $result = $this->repository->getString($type);
        $this->assertNull($result);
    }

    public function test_setString_creates_record_automatically(): void
    {
        $type = SyncOpenChatStateType::filterCacheDate;
        $testValue = '2026-01-09';

        // レコードを削除
        DB::execute("DELETE FROM sync_open_chat_state WHERE type = :type", ['type' => $type->value]);

        // レコードが存在しなくても自動作成される
        $this->repository->setString($type, $testValue);

        // DBに保存されていることを確認
        $result = DB::fetchColumn(
            "SELECT extra FROM sync_open_chat_state WHERE type = :type",
            ['type' => $type->value]
        );

        $this->assertSame($testValue, $result);
    }

    public function test_setString_updates_existing_record(): void
    {
        $type = SyncOpenChatStateType::filterCacheDate;

        // 先に値をセット
        $this->repository->setString($type, 'initial_value');
        $this->assertSame('initial_value', $this->repository->getString($type));

        // 値を更新
        $this->repository->setString($type, 'updated_value');
        $this->assertSame('updated_value', $this->repository->getString($type));
    }

    public function test_getString_returns_correct_value(): void
    {
        $type = SyncOpenChatStateType::filterCacheDate;
        $testValue = 'test_string_123';

        $this->repository->setString($type, $testValue);
        $result = $this->repository->getString($type);

        $this->assertSame($testValue, $result);
    }

    public function test_setString_with_date_format(): void
    {
        $type = SyncOpenChatStateType::filterCacheDate;
        $date = '2026-01-09';

        $this->repository->setString($type, $date);
        $result = $this->repository->getString($type);

        $this->assertSame($date, $result);
    }

    // ========================================
    // INSERT ON DUPLICATE KEY UPDATE の動作確認
    // ========================================

    public function test_setTrue_does_not_overwrite_extra_column(): void
    {
        $type = SyncOpenChatStateType::persistMemberStatsLastDate;
        $extraValue = 'important_data';

        // 先にextraに値をセット
        $this->repository->setString($type, $extraValue);

        // setTrueを実行してもextraは上書きされない
        $this->repository->setTrue($type);

        // extraの値が保持されていることを確認
        $result = DB::fetchColumn(
            "SELECT extra FROM sync_open_chat_state WHERE type = :type",
            ['type' => $type->value]
        );

        $this->assertSame($extraValue, $result);
    }

    public function test_setString_does_not_overwrite_bool_column(): void
    {
        $type = SyncOpenChatStateType::filterCacheDate;

        // 先にboolにtrueをセット
        $this->repository->setTrue($type);

        // setStringを実行してもboolは上書きされない（初回作成時は0だが既存レコードは保持）
        $this->repository->setString($type, 'test_value');

        // boolの値が保持されていることを確認
        $result = DB::fetchColumn(
            "SELECT bool FROM sync_open_chat_state WHERE type = :type",
            ['type' => $type->value]
        );

        $this->assertSame(1, (int)$result);
    }

    public function test_multiple_operations_on_same_type(): void
    {
        $type = SyncOpenChatStateType::persistMemberStatsLastDate;

        // 複数回の操作を実行
        $this->repository->setTrue($type);
        $this->assertTrue($this->repository->getBool($type));

        $this->repository->setString($type, 'value1');
        $this->assertSame('value1', $this->repository->getString($type));
        $this->assertTrue($this->repository->getBool($type)); // boolは保持

        $this->repository->setFalse($type);
        $this->assertFalse($this->repository->getBool($type));
        $this->assertSame('value1', $this->repository->getString($type)); // stringは保持

        $this->repository->setString($type, 'value2');
        $this->assertSame('value2', $this->repository->getString($type));
        $this->assertFalse($this->repository->getBool($type)); // boolは保持
    }

    // ========================================
    // 既存のSyncOpenChatStateType を使ったテスト
    // ========================================

    public function test_works_with_real_enum_types(): void
    {
        // 実際のEnum型で動作確認
        $type = SyncOpenChatStateType::persistMemberStatsLastDate;

        // 元の値をバックアップ
        $originalValue = $this->repository->getString($type);

        try {
            // テスト値をセット
            $testDate = '2026-01-09';
            $this->repository->setString($type, $testDate);
            $this->assertSame($testDate, $this->repository->getString($type));

            // 更新
            $newDate = '2026-01-10';
            $this->repository->setString($type, $newDate);
            $this->assertSame($newDate, $this->repository->getString($type));
        } finally {
            // 元の値に戻す
            if ($originalValue !== null) {
                $this->repository->setString($type, $originalValue);
            }
        }
    }

    // ========================================
    // インターフェース・DI のテスト
    // ========================================

    public function test_implements_interface(): void
    {
        $this->assertInstanceOf(
            SyncOpenChatStateRepositoryInterface::class,
            $this->repository
        );
    }

    public function test_di_creates_instance(): void
    {
        /** @var SyncOpenChatStateRepositoryInterface $repository */
        $repository = app(SyncOpenChatStateRepositoryInterface::class);

        $this->assertInstanceOf(SyncOpenChatStateRepository::class, $repository);
    }

}
