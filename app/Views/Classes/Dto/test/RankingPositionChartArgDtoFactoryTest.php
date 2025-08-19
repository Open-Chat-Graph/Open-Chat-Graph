<?php

declare(strict_types=1);

namespace App\Views\Classes\Dto\test;

use PHPUnit\Framework\TestCase;
use App\Views\Classes\Dto\RankingPositionChartArgDtoFactory;
use App\Views\Dto\RankingPositionChartArgDto;
use Shared\MimimalCmsConfig;

class RankingPositionChartArgDtoFactoryTest extends TestCase
{
    private RankingPositionChartArgDtoFactory $factory;
    
    protected function setUp(): void
    {
        $this->factory = new RankingPositionChartArgDtoFactory();
        MimimalCmsConfig::$urlRoot = 'test-root';
    }
    
    public function testCreateWithCategory(): void
    {
        $oc = [
            'id' => 123,
            'category' => 5,
            'api_created_at' => 1234567890,
        ];
        $categoryName = 'スポーツ';
        
        $dto = $this->factory->create($oc, $categoryName);
        
        $this->assertInstanceOf(RankingPositionChartArgDto::class, $dto);
        $this->assertEquals(123, $dto->id);
        $this->assertEquals(5, $dto->categoryKey);
        $this->assertEquals('スポーツ', $dto->categoryName);
        $this->assertEquals('test-root', $dto->urlRoot);
    }
    
    public function testCreateWithoutCategoryButWithApiCreatedAt(): void
    {
        $oc = [
            'id' => 456,
            'api_created_at' => 1234567890,
        ];
        $categoryName = 'すべて';
        
        $dto = $this->factory->create($oc, $categoryName);
        
        $this->assertEquals(456, $dto->id);
        $this->assertEquals(0, $dto->categoryKey);
        $this->assertEquals('すべて', $dto->categoryName);
    }
    
    public function testCreateWithoutCategoryAndStringApiCreatedAt(): void
    {
        $oc = [
            'id' => 789,
            'api_created_at' => '2024-01-01',
        ];
        $categoryName = '未指定';
        
        $dto = $this->factory->create($oc, $categoryName);
        
        $this->assertEquals(789, $dto->id);
        $this->assertNull($dto->categoryKey);
        $this->assertEquals('未指定', $dto->categoryName);
    }
}