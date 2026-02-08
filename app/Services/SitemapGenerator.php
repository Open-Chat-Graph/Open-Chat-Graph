<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\AppConfig;
use Asika\Sitemap\Sitemap;
use Asika\Sitemap\ChangeFreq;
use Asika\Sitemap\SitemapIndex;
use App\Models\Repositories\OpenChatListRepositoryInterface;
use App\Services\Recommend\RecommendUpdater;
use App\Services\Storage\FileStorageInterface;
use Shared\MimimalCmsConfig;

class SitemapGenerator
{
    const SITEMAP_PATH = '/sitemaps/';
    const SITEMAP_DIR = __DIR__ . '/../../public/sitemaps/';
    const INDEX_SITEMAP = __DIR__ . '/../../public/sitemap.xml';
    const MINIMUM_LASTMOD = '2025-08-23 21:30:00';
    private string $currentUrl = '';
    private int $currentNum = 0;
    private string $currentTmpDir = '';

    function __construct(
        private OpenChatListRepositoryInterface $ocRepo,
        private RecommendUpdater $recommendUpdater,
        private FileStorageInterface $fileStorage,
    ) {}

    function generate()
    {
        $currentLang = MimimalCmsConfig::$urlRoot;
        $langCode = $this->getLangCode($currentLang);

        // 現在のURL設定
        $this->currentUrl = AppConfig::$siteDomain . $currentLang . '/';

        // 一時ディレクトリで生成
        $tmpDir = self::SITEMAP_DIR . ".tmp-{$langCode}/";
        $finalDir = self::SITEMAP_DIR . "{$langCode}/";

        // 一時ディレクトリ作成
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        // 現在の言語用のインデックス
        $languageIndex = new SitemapIndex();
        $this->currentNum = 0;
        $this->currentTmpDir = $tmpDir;

        // 一時ディレクトリにサイトマップを生成
        $this->generateEachLanguage($languageIndex, $langCode);

        // 一時ファイルに言語別インデックスを保存
        $tmpIndexFile = __DIR__ . "/../../public/.tmp-sitemap-{$langCode}.xml";
        $finalIndexFile = __DIR__ . "/../../public/sitemap-{$langCode}.xml";
        $this->fileStorage->safeFileRewrite($tmpIndexFile, $languageIndex->render());

        // アトミック切り替え: 一気にリネーム
        if (is_dir($finalDir)) {
            $this->removeDirectory($finalDir);
        }
        rename($tmpDir, $finalDir);
        rename($tmpIndexFile, $finalIndexFile);

        // メインインデックス更新
        $this->updateMainSitemapIndex();

        // 従来のゴミファイル削除（初回のみ）
        $this->cleanLegacySitemapFiles();
    }

    private function generateEachLanguage(SitemapIndex $index, string $langCode)
    {
        $index->addItem($this->generateSitemap1($langCode), new \DateTime);

        foreach (array_chunk($this->ocRepo->getOpenChatSiteMapData(), 25000) as $openChat) {
            $index->addItem($this->genarateOpenChatSitemap($openChat, $langCode), new \DateTime);
        }
    }

    private function generateSitemap1(string $langCode): string
    {
        $datetime = $this->fileStorage->getContents('@hourlyCronUpdatedAtDatetime');

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

        return $this->saveXml($sitemap, $langCode);
    }

    private function genarateOpenChatSitemap(array $openChat, string $langCode): string
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

        return $this->saveXml($sitemap, $langCode);
    }

    private function addItem(Sitemap $sitemap, string $uri, string $datetime = 'now'): Sitemap
    {
        return $sitemap->addItem($this->currentUrl . $uri, lastmod: new \DateTime($datetime));
    }

    /**
     * @return string XML URL
     */
    private function saveXml(Sitemap $sitemap, string $langCode): string
    {
        $this->currentNum++;
        $n = $this->currentNum;

        $fileName = "sitemap-{$n}.xml";
        $filePath = $this->currentTmpDir . $fileName;
        $this->fileStorage->safeFileRewrite($filePath, $sitemap->render());

        // URLは最終的なパスを返す
        return AppConfig::$siteDomain . self::SITEMAP_PATH . "{$langCode}/" . $fileName;
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

    /**
     * 言語コードを取得
     *
     * @param string $lang URL rootの言語コード ('', '/tw', '/th')
     * @return string 言語コード ('ja', 'tw', 'th')
     */
    private function getLangCode(string $lang): string
    {
        return match ($lang) {
            '' => 'ja',
            '/tw' => 'tw',
            '/th' => 'th',
            default => 'ja',
        };
    }

    /**
     * メインサイトマップインデックスを更新
     */
    private function updateMainSitemapIndex(): void
    {
        $mainIndex = new SitemapIndex();

        foreach (array_keys(AppConfig::$dbName) as $lang) {
            $langCode = $this->getLangCode($lang);
            $indexFile = __DIR__ . "/../../public/sitemap-{$langCode}.xml";

            if (file_exists($indexFile)) {
                $url = AppConfig::$siteDomain . "/sitemap-{$langCode}.xml";
                $mainIndex->addItem($url, new \DateTime);
            }
        }

        $this->fileStorage->safeFileRewrite(self::INDEX_SITEMAP, $mainIndex->render());
    }

    /**
     * 従来のゴミサイトマップファイルを削除
     */
    private function cleanLegacySitemapFiles(): void
    {
        // /public/sitemaps/ 直下の sitemap*.xml を削除
        $files = glob(self::SITEMAP_DIR . 'sitemap*.xml');
        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    /**
     * ディレクトリを再帰的に削除
     *
     * @param string $dir 削除対象ディレクトリ
     */
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
