<?php

declare(strict_types=1);

use App\Models\UserLogRepositories\UserLogDB;
use App\Models\UserLogRepositories\UserLogRepository;
use PHPUnit\Framework\TestCase;

class UserLogRepositoryTest extends TestCase
{
    private UserLogRepository $inst;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inst = app(UserLogRepository::class);
    }

    protected function tearDown(): void
    {
        UserLogDB::execute('TRUNCATE TABLE oc_list_user');
        UserLogDB::execute('TRUNCATE TABLE oc_list_user_list_show_log');

        parent::tearDown();
    }

    public function test_insertUserListLog()
    {
        $list = [1, 2, 3, 4];
        $id = 'test_user_id';
        $expires = time();
        $ip = 'ip';
        $ua = 'ua';

        $this->inst->insertUserListLog(
            $list,
            $id,
            $expires,
            $ip,
            $ua
        );

        $this->assertTrue($this->inst->checkExistsUserListLog($id, $expires));
        $this->assertFalse($this->inst->checkExistsUserListLog($id, 2345));
    }

    public function test_insertUserListShowLog()
    {
        $id = 'test_user_id';
        $this->inst->insertUserListShowLog($id);
        $this->assertTrue(true);
    }
    public function test_getUserListLogAll()
    {
        $this->test_insertUserListLog();
        $this->test_insertUserListShowLog();
        $this->test_insertUserListShowLog();
        $this->test_insertUserListShowLog();
        $this->test_insertUserListShowLog();
        $this->test_insertUserListShowLog();
        $this->test_insertUserListShowLog();
        $this->test_insertUserListShowLog();
        $this->test_insertUserListShowLog();

        $result = $this->inst->getUserListLogAll(100, 0);
        debug($result);
        $this->assertTrue(isset($result[0]['oc'][0]['id']));

        $this->test_insertUserListLog();
    }
}
