<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Review;

use PHPUnit\Framework\TestCase;
use App\Models\Repositories\UpdateOpenChatRepositoryInterface;

class GetOpenChatIdByEmidTest extends TestCase
{
    public function testgetOpenChatIdByEmid(): void
    {
        $emid = 'yeO-hKw0jPcecDPorz6PLeU8fdPDqIYl17W93bVh_W4J01SX6lPq0J3TXAI';

        /**
         * @var UpdateOpenChatRepositoryInterface $Service
         */
        $Service = app()->make(UpdateOpenChatRepositoryInterface::class);
        $result = $Service->getOpenChatIdByEmid($emid);
        var_dump($result);
        $this->assertIsBool($result && !$result['next_update']);
    }
}
