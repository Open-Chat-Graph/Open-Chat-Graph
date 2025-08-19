<?php

declare(strict_types=1);

namespace Shadow\File;

use App\Services\OpenChat\Updater\OpenChatUpdaterFromApi;
use PHPUnit\Framework\TestCase;

class OpenChatUpdaterFromApiTest extends TestCase
{
    public function test(): void
    {
        /**
         * @var OpenChatUpdaterFromApi $updater
         */
        $updater = app(OpenChatUpdaterFromApi::class);

        $result = $updater->fetchUpdateOpenChat(139335);
        $this->assertTrue($result);
    }
}
