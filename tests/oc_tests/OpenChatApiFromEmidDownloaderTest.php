<?php

use PHPUnit\Framework\TestCase;
use App\Services\OpenChat\Crawler\OpenChatApiFromEmidDownloader;

class OpenChatApiFromEmidDownloaderTest extends TestCase
{
    public function testOpenChatApiFromEmidDownloader()
    {
        /**
         * @var OpenChatApiFromEmidDownloader $openChatApiOcDataFromEmidDownloader
         */
        $openChatApiOcDataFromEmidDownloader = app(OpenChatApiFromEmidDownloader::class);

        $emid = "Fnc7dW-fmcEkr7R4uF86DrW-1Ve-RfHRT7urnv2h59Ief3TXxff6S3SyJBA";

        $res = $openChatApiOcDataFromEmidDownloader->fetchOpenChatDto($emid);
        var_dump($res);

        $this->assertIsObject($res);
    }

    public function testfetchOpenChatApiInvitationTicketFromEmid()
    {
        /**
         * @var OpenChatApiFromEmidDownloader $openChatApiOcDataFromEmidDownloader
         */
        $openChatApiOcDataFromEmidDownloader = app(OpenChatApiFromEmidDownloader::class);

        $emid = "Z9QNexmVncqPHZXrvr9OwyriDV8qaO-a9cziFgCzzOv6EWTKqoTbf76Hqq8";

        $res = $openChatApiOcDataFromEmidDownloader->fetchOpenChatApiFromEmidDtoElement($emid);
        var_dump($res);

        $this->assertIsArray($res);
    }
}
