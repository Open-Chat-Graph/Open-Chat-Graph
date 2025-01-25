<?php

namespace App\Views\Schema;

use App\Config\AppConfig;
use App\Views\Meta\Metadata;
use Shared\MimimalCmsConfig;
use Spatie\SchemaOrg\DiscussionForumPosting;
use Spatie\SchemaOrg\Schema;

class PageBreadcrumbsListSchema
{
    const AuthorName = 'pika-0203';
    const AuthorUrl = 'https://github.com/pika-0203';
    public string $publisherName;
    public string $publisherLogo;
    public string $siteImg;

    function __construct(
        private Metadata $metadata
    ) {
        $this->publisherName = 'OpenChat Graph';
        $this->publisherLogo = url(['urlRoot' => '', 'paths' => ['assets/icon-192x192.png']]);
        $this->siteImg = url(['urlRoot' => '', 'paths' => ['assets/ogp.png']]);
    }

    // パンくずリスト
    function generateSchema(string $listItemName, string $path, string $secondName = '', string $secondPath = '', bool $fullPath = false): string
    {
        $breadcrumbList = Schema::breadcrumbList();

        $itemListElement = [
            Schema::listItem()
                ->position(1)
                ->name(t('トップ'))
                ->item(rtrim(url(), '/')),
            Schema::listItem()
                ->position(2)
                ->name($listItemName)
                ->item(url($path)),
        ];

        if ($secondName && $secondPath) {
            $itemListElement[] = Schema::listItem()
                ->position(3)
                ->name($secondName)
                ->item(url($fullPath ? $secondPath : ($path . '/' . $secondPath)));
        }

        $breadcrumbList->itemListElement($itemListElement);

        return $breadcrumbList->toScript();
    }

    // organization
    function publisher()
    {
        $publisherName = $this->publisherName;
        $publisherLogo = $this->publisherLogo;
        return Schema::organization()
            ->name($publisherName)
            ->logo($publisherLogo)
            ->email('support@openchat-review.me')
            ->url(url(["urlRoot" => "", "paths" => []]))
            ->sameAs(['https://x.com/openchat_graph']);
    }

    function person()
    {
        $authorName = self::AuthorName;
        $authorUrl = self::AuthorUrl;
        return Schema::person()
            ->name($authorName)
            ->url($authorUrl);
    }

    function lineOcOrganization()
    {
        return Schema::organization()
            ->name(t('LINEオープンチャット'))
            ->alternateName(t('オプチャ'))
            ->url(t('https://openchat.line.me/jp'));
    }

    function lineOrganization()
    {
        return Schema::organization()
            ->name('LINE');
    }

    function room(array $room): DiscussionForumPosting
    {
        return Schema::discussionForumPosting()
            ->headline($room['name'])
            ->description($room['description'])
            ->url(AppConfig::LINE_OPEN_URL[MimimalCmsConfig::$urlRoot] . $room['emid'] . AppConfig::LINE_OPEN_URL_SUFFIX)
            ->sameAs($room['url'] ? AppConfig::LINE_APP_URL . $room['url'] . AppConfig::LINE_APP_SUFFIX : '')
            ->interactionStatistic(
                Schema::interactionCounter()
                    ->interactionType('https://schema.org/FollowAction')
                    ->userInteractionCount($room['member'])
            )
            ->image([
                imgUrl($room['id'], $room['img_url']),
            ])
            ->datePublished(new \DateTime($room['created_at']))
            ->dateModified(new \DateTime($room['updated_at']))
            ->provider(
                $this->lineOcOrganization()
            )
            ->author(
                Schema::person()
                    ->name($room['name'])
                    ->url(AppConfig::LINE_OPEN_URL[MimimalCmsConfig::$urlRoot] . $room['emid'] . AppConfig::LINE_OPEN_URL_SUFFIX)
            );
    }

    function actionApplication()
    {
        return Schema::softwareApplication()
            ->name('LINE')
            ->url('https://line.me/download')
            ->operatingSystem('iOS/Android/Windows/macOS')
            ->applicationCategory('https://www.wikidata.org/wiki/Q615985');
    }

    function potentialAction()
    {
        return Schema::InteractAction()
            ->target(
                Schema::entryPoint()
                    ->urlTemplate(AppConfig::LINE_APP_URL . '{invitationTicket}' . AppConfig::LINE_APP_SUFFIX)
                    ->actionApplication($this->actionApplication())
            )
            ->additionalType('https://schema.org/FollowAction')
            ->name(t('LINEで開く'));
    }

    function generateRecommend(
        string $title,
        string $description,
        string $url,
        \DateTimeInterface $datePublished,
        \DateTimeInterface $dateModified,
        string $tag,
        array $rooms // オープンチャットルームの情報を配列で追加
    ): string {
        $count = count($rooms);
        // 各オープンチャットルームをItemListとして追加
        $itemList = Schema::itemList();

        $listArray = [];
        foreach ($rooms as $index => $room) {
            $listArray[] = Schema::listItem()
                ->item(
                    schema::article()
                        ->headline($room['name'])
                        ->description($room['description'])
                        ->image(imgPreviewUrl($room['id'], $room['img_url']))
                        ->url(url('oc/' . $room['id']))
                        ->position($index + 1)
                        ->author($this->person())
                );
        }

        $itemList->itemListElement($listArray);

        $time = $dateModified->format('G:i');

        $webSite = Schema::article()
            ->headline($title)
            ->description($description)
            ->image(imgUrl($rooms[0]['id'], $rooms[0]['img_url']))
            ->publisher($this->publisher())
            ->author($this->person())
            ->datePublished($datePublished)
            ->dateModified($dateModified)
            ->articleSection([$title, '関連のテーマ', "【{$time}】「{$tag}」おすすめランキングTOP{$count}", "人数増加アイコンの説明"])
            ->about(Schema::thing()->name($tag))
            ->mainEntityOfPage(
                Schema::collectionPage()
                    ->id($url)
            )
            ->mainEntity($itemList);

        return $webSite->toScript();
    }

    function searchAction()
    {
        return Schema::searchAction()
            ->target(
                Schema::entryPoint()
                    ->urlTemplate(url('ranking?keyword={search_term_string}'))
            )
            ->query('equired name=search_term_string');
    }
}
