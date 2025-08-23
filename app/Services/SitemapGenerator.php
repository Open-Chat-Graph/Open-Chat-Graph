<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\AppConfig;
use Asika\Sitemap\Sitemap;
use Asika\Sitemap\ChangeFreq;
use Asika\Sitemap\SitemapIndex;
use App\Models\Repositories\OpenChatListRepositoryInterface;
use App\Services\Recommend\RecommendUpdater;
use App\Models\Repositories\DB;
use Shared\MimimalCmsConfig;

class SitemapGenerator
{
    const SITE_URL = 'https://openchat-review.me';
    const SITEMAP_PATH = 'https://openchat-review.me/sitemaps/';
    const SITEMAP_DIR = __DIR__ . '/../../public/sitemaps/';
    const INDEX_SITEMAP = __DIR__ . '/../../public/sitemap.xml';
    const MINIMUM_LASTMOD = '2025-08-23 21:30:00';
    private string $currentUrl = '';
    private int $currentNum = 0;

    function __construct(
        private OpenChatListRepositoryInterface $ocRepo,
        private RecommendUpdater $recommendUpdater,
    ) {}

    function generate()
    {
        $ccurrentUrlRoot = MimimalCmsConfig::$urlRoot;
        $index = new SitemapIndex();
        foreach (array_keys(AppConfig::$dbName) as $lang) {
            MimimalCmsConfig::$urlRoot = $lang;
            $this->currentUrl = self::SITE_URL . $lang . '/';
            $this->generateEachLanguage($index);
        }

        safeFileRewrite(self::INDEX_SITEMAP, $index->render(), 0755);
        MimimalCmsConfig::$urlRoot = $ccurrentUrlRoot;
        $this->cleanSitemapFiles(self::SITEMAP_DIR, $this->currentNum);
    }

    private function generateEachLanguage(SitemapIndex $index)
    {
        $index->addItem($this->generateSitemap1(), new \DateTime);

        foreach (array_chunk($this->ocRepo->getOpenChatSiteMapData(), 25000) as $openChat) {
            $index->addItem($this->genarateOpenChatSitemap($openChat), new \DateTime);
        }
    }

    private function generateSitemap1(): string
    {
        $date = file_get_contents(AppConfig::getStorageFilePath('dailyCronUpdatedAtDate'));
        $datetime = file_get_contents(AppConfig::getStorageFilePath('hourlyCronUpdatedAtDatetime'));

        $sitemap = new Sitemap();
        $sitemap->addItem(rtrim($this->currentUrl, "/"), changeFreq: ChangeFreq::DAILY, lastmod: new \DateTime);

        if (MimimalCmsConfig::$urlRoot === '') {
            $sitemap->addItem($this->currentUrl . 'oc');
        }
        
        $sitemap->addItem($this->currentUrl . 'policy');
        $sitemap->addItem($this->currentUrl . 'ranking', lastmod: $datetime);
        $sitemap->addItem($this->currentUrl . 'ranking?keyword=' . urlencode('badge:' . AppConfig::OFFICIAL_EMBLEMS[MimimalCmsConfig::$urlRoot][1]), lastmod: $datetime);
        $sitemap->addItem($this->currentUrl . 'ranking?keyword=' . urlencode('badge:' . AppConfig::OFFICIAL_EMBLEMS[MimimalCmsConfig::$urlRoot][2]), lastmod: $datetime);

        foreach (AppConfig::OPEN_CHAT_CATEGORY[MimimalCmsConfig::$urlRoot] as $category) {
            $category && $sitemap->addItem($this->currentUrl . 'ranking/' . $category, lastmod: $datetime);
        }

        foreach ($this->recommendUpdater->getAllTagNames() as $tag) {
            $sitemap->addItem($this->currentUrl . 'recommend/' . urlencode($tag), lastmod: $datetime);
        }

        foreach ($this->recommendUpdater->getAllTagNames() as $tag) {
            $sitemap->addItem($this->currentUrl . 'ranking?keyword=' . urlencode('tag:' . $tag), lastmod: $datetime);
        }

        return $this->saveXml($sitemap);
    }

    private function genarateOpenChatSitemap(array $openChat): string
    {
        $sitemap = new Sitemap();
        foreach ($openChat as $oc) {
            ['id' => $id, 'updated_at' => $updated_at] = $oc;
            // updated_atが最小日時より古い場合は最小日時を使用
            if ($updated_at < self::MINIMUM_LASTMOD) {
                $updated_at = self::MINIMUM_LASTMOD;
            }
            $this->addItem($sitemap, "oc/{$id}", $updated_at);
        }

        return $this->saveXml($sitemap);
    }

    private function addItem(Sitemap $sitemap, string $uri, string $datetime = 'now'): Sitemap
    {
        return $sitemap->addItem($this->currentUrl . $uri, lastmod: new \DateTime($datetime));
    }

    /**
     * @return string XML URL
     */
    private function saveXml(Sitemap $sitemap): string
    {
        $this->currentNum++;
        $n = $this->currentNum;

        $fileName = "sitemap{$n}.xml";
        safeFileRewrite(self::SITEMAP_DIR . $fileName, $sitemap->render(), 0755);

        return self::SITEMAP_PATH . $fileName;
    }

    /**
     * 指定ディレクトリのsitemapファイルを削除
     *
     * @param string $directory 対象ディレクトリ
     * @param int $maxNumber 削除しない最大番号
     */
    private function cleanSitemapFiles(string $directory, int $maxNumber): void
    {
        // ディレクトリ内のファイルを取得
        $files = scandir($directory);

        foreach ($files as $file) {
            // ファイル名が 'sitemap' で始まり '.xml' で終わるかを確認
            if (
                preg_match('/^sitemap(\d+)\.xml$/', $file, $matches)
                && (int)$matches[1] > $maxNumber
            ) {
                unlink("{$directory}/{$file}");
            }
        }
    }
}
