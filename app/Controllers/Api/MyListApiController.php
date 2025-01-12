<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Config\SecretsConfig;
use App\Config\AppConfig;
use App\Services\Auth\AuthInterface;
use App\Services\User\MyOpenChatList;
use App\Services\User\MyOpenChatListUserLogger;

class MyListApiController
{
    function index(
        MyOpenChatList $myOpenChatList,
        AuthInterface $auth,
        MyOpenChatListUserLogger $myOpenChatListUserLogger,
    ) {
        if (!cookie()->has('myList')) {
            return false;
        }

        sessionStart();
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

        $hourlyUpdatedAt = new \DateTime(file_get_contents(AppConfig::getStorageFilePath('hourlyCronUpdatedAtDatetime')));

        return view('components/myList', compact('myList', 'hourlyUpdatedAt'));
    }
}
