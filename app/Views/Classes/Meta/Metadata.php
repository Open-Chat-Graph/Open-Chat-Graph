<?php

namespace App\Views\Meta;

use App\Config\AppConfig;
use Spatie\SchemaOrg\Schema;

class Metadata
{
    public string $title = 'オプチャグラフ';

    public string $description =
    'オプチャグラフはユーザーがオープンチャットを見つけて成長傾向をグラフで比較できる場所です。コメント機能で意見交換ができます。';

    public string $ogpDescription =
    'オプチャグラフはユーザーがオープンチャットを見つけて成長傾向をグラフで比較できる場所です。コメント機能で意見交換ができます。';

    public string $site_name = 'オプチャグラフ';
    public string $locale = 'ja_JP';
    public string $image_url;
    public string $site_url;
    public string $og_type;
    public string $thumbnail;

    public function __construct()
    {
        if (path() ?? '/' === '/') {
            $this->og_type = 'website';
        } else {
            $this->og_type = 'article';
        }

        $this->site_url = url();
        $this->image_url = url(AppConfig::DEFAULT_OGP_IMAGE_FILE_PATH);

        $this->title = t($this->title);
        $this->site_name = t($this->site_name);
    }

    public function setTitle(string $title, bool $includeSiteTitle = true): static
    {
        $this->title = h($title) . ($includeSiteTitle ? ('｜' . $this->title) : '');
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
        if ($this->thumbnail) $tags .= '<meta name="thumbnail" content="' . $this->thumbnail . '">' . "\n";

        return $tags;
    }

    public function generateTopPageSchema(): string
    {
        return Schema::webSite()
            ->name($this->site_name)
            ->url($this->site_url)
            ->description($this->description)
            ->image($this->image_url)
            ->toScript();
    }

    public function __toString(): string
    {
        return $this->generateTags();
    }
}
