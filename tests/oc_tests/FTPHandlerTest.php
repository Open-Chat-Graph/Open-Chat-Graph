<?php

use App\Services\Admin\FTPHandler;
use PHPUnit\Framework\TestCase;

class FTPHandlerTest extends TestCase
{
    private FTPHandler $ftpHandler;

    protected function setUp(): void
    {
        $this->ftpHandler = new FTPHandler();
    }

    // テストメソッドを追加します...
    public function testCreateDirectoryIfNeeded()
    {
        $result = $this->ftpHandler->createDirectoryIfNeeded('test/test');

        $this->assertTrue($result);
    }

    // テストメソッドを追加します...
    public function testDeleteDirectoryRecursive()
    {
        $result = $this->ftpHandler->deleteDirectoryRecursive('test');

        $this->assertTrue($result);
    }
}
