<?php

namespace App\Views\Meta;

use App\Config\AppConfig;
use Spatie\SchemaOrg\Schema;

class Metadata
{
    public string $title;
    public string $description;
    public string $ogpDescription;
    public string $site_name;
    public string $locale;
    public string $image_url;
    public string $site_url;
    public string $og_type;
    public string $thumbnail;

    public function __construct()
    {
        if (path('') ?? '/' === '/') {
            $this->og_type = 'website';
        } else {
            $this->og_type = 'article';
        }

        $this->site_url = url();
        $this->image_url = url(['urlRoot' => '', 'paths' => [AppConfig::DEFAULT_OGP_IMAGE_FILE_PATH]]);

        $siteTitle = t('オプチャグラフ');
        $this->site_name = $siteTitle;

        if (AppConfig::$isStaging) {
            $this->title = t('[開発環境]') . ' ' . $siteTitle;
        } else {
            $this->title = $siteTitle;
        }

        $this->locale = t('ja');

        $description = t('LINEオープンチャットの「今」が一目でわかる人気ランキングサイト。最新の人気チャットルームや成長トレンドをシンプルなグラフで表示。初心者からベテランまで、誰でも簡単に活用できます。');
        $this->description = $description;
        $this->ogpDescription = $description;
    }

    public function setTitle(string $title, bool $includeSiteTitle = true): static
    {
        $prefix = AppConfig::$isStaging ? t('[開発環境]') . ' ' : '';
        $this->title = $prefix . h($title) . ($includeSiteTitle ? ('｜' . $this->site_name) : '');
        return $this;
    }

    public function setDescription(string $description): static
    {
        $this->description = h($description);
        return $this;
    }

    public function setOgpDescription(string $ogpDescription): static
    {
        $this->ogpDescription = h($ogpDescription);
        return $this;
    }

    public function setImageUrl(string $image_url): static
    {
        $this->image_url = h($image_url);
        return $this;
    }

    public function generateTags(bool $query = false): string
    {
        if (!isset($this->thumbnail)) $this->thumbnail = $this->image_url;

        $url = $query ? rtrim(url(path()), '/')
            : rtrim(url(strstr(path(), '?', true) ?: path()), '/');

        $tags = '';
        $tags .= '<title>' . $this->title . '</title>' . "\n";
        $tags .= '<meta name="description" content="' . $this->description . '">' . "\n";
        $tags .= '<meta property="og:locale" content="' . $this->locale . '">' . "\n";
        $tags .= '<meta property="og:url" content="' . $url . '">' . "\n";
        $tags .= '<meta property="og:type" content="' . $this->og_type . '">' . "\n";
        $tags .= '<meta property="og:title" content="' . $this->title . '">' . "\n";
        $tags .= '<meta property="og:description" content="' . $this->ogpDescription . '">' . "\n";
        if ($this->image_url) $tags .= '<meta property="og:image" content="' . $this->image_url . '">' . "\n";
        $tags .= '<meta property="og:site_name" content="' . $this->site_name . '">' . "\n";
        $tags .= '<meta name="twitter:card" content="summary">' . "\n";
        $tags .= '<meta name="twitter:site" content="@openchat_graph">' . "\n";

        if ($this->thumbnail) $tags .= '<meta name="thumbnail" content="' . $this->thumbnail . '">' . "\n";

        return $tags;
    }

    public function generateTopPageSchema(): string
    {
        return Schema::webSite()
            ->name($this->site_name)
            ->inLanguage($this->locale)
            ->url(url())
            ->image($this->image_url)
            ->toScript();
    }

    public function __toString(): string
    {
        return $this->generateTags();
    }
}
