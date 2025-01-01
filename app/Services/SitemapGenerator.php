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
    const SITE_URL = 'https://openchat-review.me/';
    const SITEMAP_URL = 'https://openchat-review.me/sitemaps/';
    const SITEMAP_DIR = __DIR__ . '/../../public/sitemaps/';
    const INDEX_SITEMAP = __DIR__ . '/../../public/sitemap.xml';

    function __construct(
        private OpenChatListRepositoryInterface $ocRepo,
        private RecommendUpdater $recommendUpdater,
    ) {
    }

    function generate()
    {
        DB::$pdo = null;

        $index = new SitemapIndex();
        $index->addItem($this->generateSitemap1(), new \DateTime);

        $currentNum = 1;
        foreach (array_chunk($this->ocRepo->getOpenChatSiteMapData(), 25000) as $i => $openChat) {
            $index->addItem($this->genarateOpenChatSitemap($openChat, $i + 2), new \DateTime);
            $currentNum++;
        }

        safeFileRewrite(self::INDEX_SITEMAP, $index->render(), 0755);
    }
    // TODO: 多言語でのサイトマップ生成に対応させる
    private function generateSitemap1(): string
    {
        $date = file_get_contents(AppConfig::getStorageFilePath('dailyCronUpdatedAtDate'));
        $datetime = file_get_contents(AppConfig::getStorageFilePath('hourlyCronUpdatedAtDatetime'));

        $sitemap = new Sitemap();
        $sitemap->addItem(rtrim(self::SITE_URL, "/"), changeFreq: ChangeFreq::DAILY, lastmod: new \DateTime);
        $sitemap->addItem(self::SITE_URL . 'oc');
        $sitemap->addItem(self::SITE_URL . 'policy');
        $sitemap->addItem(self::SITE_URL . 'ranking', lastmod: $datetime);
        $sitemap->addItem(self::SITE_URL . 'ranking?keyword=' . urlencode('badge:スペシャルオープンチャット'), lastmod: $datetime);
        $sitemap->addItem(self::SITE_URL . 'ranking?keyword=' . urlencode('badge:公式認証オープンチャット'), lastmod: $datetime);


        foreach (AppConfig::OPEN_CHAT_CATEGORY[MimimalCmsConfig::$urlRoot] as $category) {
            $category && $sitemap->addItem(self::SITE_URL . 'ranking/' . $category, lastmod: $datetime);
        }

        foreach ($this->recommendUpdater->getAllTagNames() as $tag) {
            $sitemap->addItem(self::SITE_URL . 'recommend?tag=' . urlencode($tag), lastmod: $datetime);
        }

        foreach ($this->recommendUpdater->getAllTagNames() as $tag) {
            $sitemap->addItem(self::SITE_URL . 'ranking?keyword=' . urlencode('tag:' . $tag), lastmod: $datetime);
        }

        return $this->saveXml($sitemap, 1);
    }

    private function genarateOpenChatSitemap(array $openChat, int $n): string
    {
        $sitemap = new Sitemap();
        foreach ($openChat as $oc) {
            ['id' => $id, 'updated_at' => $updated_at] = $oc;
            $this->addItem($sitemap, "oc/{$id}", $updated_at);
        }

        return $this->saveXml($sitemap, $n);
    }

    private function addItem(Sitemap $sitemap, string $uri, string $datetime = 'now'): Sitemap
    {
        return $sitemap->addItem(self::SITE_URL . $uri, lastmod: new \DateTime($datetime));
    }

    /**
     * @return string XML URL
     */
    private function saveXml(Sitemap $sitemap, int $n): string
    {
        $fileName = "sitemap{$n}.xml";
        safeFileRewrite(self::SITEMAP_DIR . $fileName, $sitemap->render(), 0755);

        return self::SITEMAP_URL . $fileName;
    }
}
