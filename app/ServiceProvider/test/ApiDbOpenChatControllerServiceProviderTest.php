<?php

declare(strict_types=1);

namespace App\ServiceProvider\test;

use App\ServiceProvider\ApiDbOpenChatControllerServiceProvider;
use App\Models\Repositories\OpenChatPageRepositoryInterface;
use App\Models\Repositories\Api\ApiOpenChatPageRepository;
use App\Models\Repositories\Statistics\StatisticsPageRepositoryInterface;
use App\Models\Repositories\Api\ApiStatisticsPageRepository;

class ApiDbOpenChatControllerServiceProviderTest extends AbstractServiceProviderTestCase
{
    public function testRegisterBindsCorrectly(): void
    {
        $this->assertServiceProviderBindings(
            ApiDbOpenChatControllerServiceProvider::class,
            [
                OpenChatPageRepositoryInterface::class => ApiOpenChatPageRepository::class,
                StatisticsPageRepositoryInterface::class => ApiStatisticsPageRepository::class
            ]
        );
    }
    
    public function testBindingsAreNotSingletons(): void
    {
        $this->assertServiceProviderNonSingletons(
            ApiDbOpenChatControllerServiceProvider::class,
            [
                OpenChatPageRepositoryInterface::class,
                StatisticsPageRepositoryInterface::class
            ]
        );
    }
}