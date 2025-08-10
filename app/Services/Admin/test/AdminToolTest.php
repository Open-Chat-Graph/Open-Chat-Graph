<?php

declare(strict_types=1);

use App\Services\Admin\AdminTool;
use PHPUnit\Framework\TestCase;

class AdminToolTest extends TestCase
{
    // discordテスト
    public function testDiscordWebhook()
    {
        $result = AdminTool::sendDiscordNotify('テストメッセージ');
        debug($result);
        $this->assertIsString($result);
    }
}
