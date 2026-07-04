<?php

namespace App\Views\Meta;

use App\Config\AppConfig;
use Spatie\SchemaOrg\Schema;

class Metadata
{
    /**
     * 検索結果でアイコン横に出る「サイト名ラベル」(og:site_name / WebSite schema name)。
     * タイトル接尾辞($site_name)とは切り離し、全言語共通の英語ブランドで統一する
     * (LINE公式が言語問わずラベルを「LINE」で出すのと同じ。日本語の「オプチャグラフ」は
     * タイトル・本文に残るので国内のブランド検索には影響しない)。
     */
    public const BRAND_LABEL = 'Open Chat Graph';

    public string $title;
    public string $description;
    public string $ogpDescription;
    public string $site_name;
    public string $locale;
    public string $image_url;
    public string $site_url;
    public string $og_type;
    public string $thumbnail;

    /** twitter:card の種別。動的OGP画像(1200x630)を持つページは summary_large_image にする */
    public string $twitterCard = 'summary';

    public function __construct()
    {
        // 演算子優先順位の修正: `===` は `??` より強く結合するため、
        // 旧 `path('') ?? '/' === '/'` は `path('') ?? ('/' === '/')` = 実質 `path('')`
        // と評価され（path() は常に非null文字列を返す）、全ページが 'website' に固定されていた。
        // 本来の意図「トップ(=/)のみ website、下層は article」を括弧で復元する。
        if ((path('') ?? '/') === '/') {
            $this->og_type = 'website';
        } else {
            $this->og_type = 'article';
        }

        $this->site_url = url();
        // デフォルトOGPは言語別（日本語版の画像が tw/th のシェアに出るチグハグを避ける）
        $this->image_url = url(['urlRoot' => '', 'paths' => [AppConfig::defaultOgpImagePath()]]);

        $siteTitle = t('オプチャグラフ');
        $this->site_name = $siteTitle;

        if (AppConfig::$isStaging) {
            $this->title = $siteTitle . t(' (開発環境)');
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
        $suffix = AppConfig::$isStaging ? t(' (開発環境)') : '';
        $this->title = h($title) . ($includeSiteTitle ? ('｜' . $this->site_name) : '') . $suffix;
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

    /** 検索用サムネイル(meta name="thumbnail")のURL。setImageUrl と同様にエスケープして格納する */
    public function setThumbnail(string $thumbnail): static
    {
        $this->thumbnail = h($thumbnail);
        return $this;
    }

    public function setTwitterCard(string $twitterCard): static
    {
        $this->twitterCard = h($twitterCard);
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
        $tags .= '<meta property="og:site_name" content="' . self::BRAND_LABEL . '">' . "\n";
        $tags .= '<meta name="twitter:card" content="' . $this->twitterCard . '">' . "\n";
        $tags .= '<meta name="twitter:site" content="@openchat_graph">' . "\n";

        if ($this->thumbnail) $tags .= '<meta name="thumbnail" content="' . $this->thumbnail . '">' . "\n";

        return $tags;
    }

    public function generateTopPageSchema(): string
    {
        return Schema::webSite()
            ->name(self::BRAND_LABEL)
            ->inLanguage($this->locale)
            ->url(url())
            ->image($this->image_url)
            ->publisher(
                Schema::organization()
                    ->name(t('オプチャグラフ'))
                    ->url(url())
                    ->logo(url(['urlRoot' => '', 'paths' => [AppConfig::SITE_ICON_FILE_PATH]]))
                    ->sameAs(AppConfig::BRAND_SAME_AS)
            )
            ->toScript();
    }

    public function __toString(): string
    {
        return $this->generateTags();
    }
}
