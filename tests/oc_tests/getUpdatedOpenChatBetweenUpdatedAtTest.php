<?php

declare(strict_types=1);

use App\Models\Repositories\UpdateOpenChatRepository;
use App\Services\OpenChat\Utility\OpenChatServicesUtility;
use PHPUnit\Framework\TestCase;

class getUpdatedOpenChatBetweenUpdatedAtTest extends TestCase
{
    public function test()
    {

        /**
         * @var UpdateOpenChatRepository $inst
         */
        $inst = app()->make(UpdateOpenChatRepository::class);

        $start = OpenChatServicesUtility::getModifiedCronTime('now');
        $end = OpenChatServicesUtility::getModifiedCronTime(strtotime('+1hour'));

        $res = $inst->getUpdatedOpenChatBetweenUpdatedAt($start, $end);
        $res = $inst->getOpenChatImgAll();
        debug($res);

        $this->assertTrue(true);
    }
}
