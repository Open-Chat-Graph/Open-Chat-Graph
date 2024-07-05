<?php

declare(strict_types=1);

use App\Services\Bot\BotFightUserAgents;
use PHPUnit\Framework\TestCase;

class BotFightUserAgentsTest extends TestCase
{
    private BotFightUserAgents $instance;

    public function test()
    {
        //BotFightUserAgents::fetchCrawlerUserAgentsJson();
        
        $this->instance = app(BotFightUserAgents::class);
        debug($result = $this->instance->isCrawler("facebookexternalhit/1.1;line-poker/1.0"));

        $this->assertTrue($result);
    }
}
