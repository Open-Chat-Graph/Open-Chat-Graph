<?php

declare(strict_types=1);

namespace App\Services\User;

use Shadow\Kernel\Validator;
use App\Config\AppConfig;

class CookieListService
{
    public string $cookieName;
    public int $myListLimit;
    private array $myListArray = [];
    private int $expires;

    function init(string $cookieName = 'myList', $myListLimit = AppConfig::LIST_LIMIT_MY_LIST): bool
    {
        if (!cookie()->has($cookieName)) {
            return false;
        }

        $this->cookieName = $cookieName;
        $this->myListLimit = $myListLimit;

        $result = $this->validation();
        if (!$result) {
            cookie()->remove($this->cookieName);
            return false;
        }

        [$myListArray, $expires] = $result;
        $this->expires = $expires;

        if (isWithinHalfExpires($expires, 3600 * 24 * 365)) {
            $this->setCookie($this->getCookie());
        }

        $this->myListArray = $myListArray;
        return true;
    }

    private function validation(): array|false
    {
        $myListCookie = $this->getCookie();
        if ($myListCookie === [] || count($myListCookie) > $this->myListLimit + 1) {
            return false;
        }

        $existsExpires = null;
        $expires = 0;
        $newList = [];

        foreach ($myListCookie as $key => $value) {
            if ($existsExpires === null && $key === 'expires') {
                $existsExpires = true;
                $expires = $value;
                continue;
            }

            $keyResult = Validator::num($key, min: 1);
            $valueResult = Validator::num($value, min: 1, max: 10);

            if ($keyResult === false || $valueResult === false) {
                return false;
            }

            $newList[$keyResult] = $valueResult;
        }

        if ($existsExpires === null || Validator::num($expires) === false || $newList === []) {
            return false;
        }

        return [$newList, $expires];
    }

    private function getCookie(): array
    {
        $cookie = cookie($this->cookieName);
        if (!$cookie) {
            return [];
        }

        $json = json_decode($cookie, true);
        if (!is_array($json)) {
            return [];
        }

        return $json;
    }

    private function setCookie(array $array)
    {
        $expires = time() + 3600 * 24 * 365;
        $array['expires'] = $expires;

        cookie(
            ['myList' => json_encode($array)],
            $expires,
            httpOnly: false
        );

        $this->expires = $expires;
    }

    /**
     * @throws \RuntimeExeption 
     */
    function setListArrayCookie(array $array)
    {
        if (count($array) > $this->myListLimit) {
            throw new \RuntimeException('List array is too long');
        }

        foreach ($array as $key => $value) {
            $keyResult = Validator::num($key, min: 1);
            $valueResult = Validator::num($value, min: 1, max: 10);

            if ($keyResult === false || $valueResult === false) {
                throw new \RuntimeException('Invalid list array');
            }
        }

        $this->setCookie($array);
    }

    function getListArray(): array
    {
        return $this->myListArray;
    }

    function getExpires(): int
    {
        return $this->expires;
    }
}
