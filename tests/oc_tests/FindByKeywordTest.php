<?php

declare(strict_types=1);

namespace Tests\Unit\Models\Repositories;

use App\Models\Repositories\OpenChatListRepository;
use PHPUnit\Framework\TestCase;

class FindByKeywordTest extends TestCase
{
    /** @var OpenChatListRepository */
    private $repository;

    public function setUp(): void
    {
        // OpenChatListRepositoryクラスのインスタンスを生成
        $this->repository = new OpenChatListRepository();
    }

    public function testFindByKeyword(): void
    {
        // findByKeywordメソッドを実行し、結果を取得
        $result = $this->repository->findByKeyword('ライブトーク', 400, 40);

        foreach ($result['result'] as $oc) {
            debug($oc);
        }

        $this->assertIsArray($result);
    }
}
