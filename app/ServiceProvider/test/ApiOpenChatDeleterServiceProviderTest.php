<?php

declare(strict_types=1);

namespace App\ServiceProvider\test;

use App\ServiceProvider\ApiOpenChatDeleterServiceProvider;
use App\Services\OpenChat\Api\ApiOpenChatDeleter;
use App\Services\OpenChat\Updater\OpenChatDeleterInterface;

class ApiOpenChatDeleterServiceProviderTest extends AbstractServiceProviderTestCase
{
    public function testRegisterBindsCorrectly(): void
    {
        $this->assertServiceProviderBindings(
            ApiOpenChatDeleterServiceProvider::class,
            [
                OpenChatDeleterInterface::class => ApiOpenChatDeleter::class
            ]
        );
    }
    
    public function testBindingsAreNotSingletons(): void
    {
        $this->assertServiceProviderNonSingletons(
            ApiOpenChatDeleterServiceProvider::class,
            [
                OpenChatDeleterInterface::class
            ]
        );
    }
}