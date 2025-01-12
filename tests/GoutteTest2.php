<?php

use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\BrowserKit\CookieJar;
use PHPUnit\Framework\TestCase;

class GoutteTest2 extends TestCase
{
    public function testGetTitle()
    {
        $cookieJar = new CookieJar();
        $client = new HttpBrowser(HttpClient::create(), null, $cookieJar);
        $ua = 'Mozilla/5.0 (Linux; Android 11';
        $brands = ['Samsung', 'Google', 'Xiaomi', 'OnePlus'];
        $rand_brand = $brands[array_rand($brands)];
        $models = ['SM-G970', 'Pixel 5', 'Mi 10T', 'ONEPLUS A6013'];
        $rand_model = $models[array_rand($models)];
        $ua .= "; {$rand_brand} {$rand_model}) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/111.0.0.0 Mobile Safari/537.36";

        $client->setServerParameter('HTTP_USER_AGENT', $ua);
        $client->setServerParameter('HTTP_ACCEPT_LANGUAGE', 'ja-JP');

        $crawler = $client->request('GET', 'http://www.iqiq.jp/pc/iqtest/question.php?age=0&pref=0');

        $title = $crawler->filter('#main > div.box640_pi > div.iq_quest > div:nth-child(8) > a')->attr('href');
        var_dump($title);
    }
}
