<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Config\SecretsConfig;
use App\Services\Auth\AuthInterface;
use App\Services\Storage\FileStorageInterface;
use App\Services\User\MyOpenChatList;
use App\Services\User\MyOpenChatListUserLogger;

class MyListApiController
{
    function index(
        MyOpenChatList $myOpenChatList,
        AuthInterface $auth,
        MyOpenChatListUserLogger $myOpenChatListUserLogger,
        FileStorageInterface $fileStorage,
    ) {
        if (!cookie()->has('myList')) {
            return false;
        }

        [$expires, $myListIdArray, $myList] = $myOpenChatList->init();
        if (!$expires)
            return false;

        $userId = $auth->loginCookieUserId();

        if ($userId !== SecretsConfig::$adminApiKey)
            $myOpenChatListUserLogger->userMyListLog(
                $userId,
                $expires,
                $myListIdArray
            );

        $hourlyUpdatedAt = new \DateTime($fileStorage->getContents('@hourlyCronUpdatedAtDatetime'));

        return view('components/myList', compact('myList', 'hourlyUpdatedAt'));
    }
}
