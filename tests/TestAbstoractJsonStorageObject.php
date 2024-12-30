<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Config\ConfigJson;

class TestAbstoractJsonStorageObject extends TestCase
{
    private ConfigJson $json;

    public function test()
    {
        $this->json = app(ConfigJson::class);
        var_dump($this->json);
        
        $this->json->SyncOpenChat = true;
        $this->json->update();
        var_dump($this->json);
        
        $this->assertTrue(true);
    }
}
