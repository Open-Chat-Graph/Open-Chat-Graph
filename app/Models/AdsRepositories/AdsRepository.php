<?php

declare(strict_types=1);

namespace App\Models\AdsRepositories;

use App\Views\Dto\AdsDto;
use App\Models\Repositories\DB;

class AdsRepository
{
    function insertAds(
        string $ads_title,
        string $ads_sponsor_name,
        string $ads_paragraph,
        string $ads_href,
        string $ads_img_url,
        string $ads_tracking_url,
        string $ads_title_button,
    ): int {
        $query =
            "INSERT INTO
                ads (
                    ads_title,
                    ads_sponsor_name,
                    ads_paragraph,
                    ads_href,
                    ads_img_url,
                    ads_tracking_url,
                    ads_title_button
                )
            VALUES
                (
                    :ads_title,
                    :ads_sponsor_name,
                    :ads_paragraph,
                    :ads_href,
                    :ads_img_url,
                    :ads_tracking_url,
                    :ads_title_button
                )";

        return DB::executeAndGetLastInsertId($query, compact(
            'ads_title',
            'ads_sponsor_name',
            'ads_paragraph',
            'ads_href',
            'ads_img_url',
            'ads_tracking_url',
            'ads_title_button',
        ));
    }

    function insertTagMap(int $ads_id, string $tag): void
    {
        $query =
            "INSERT INTO
                ads_tag_map (ads_id, tag)
            VALUES
                (:ads_id, :tag) ON DUPLICATE KEY
            UPDATE
                ads_id = VALUES(ads_id)";

        DB::execute($query, compact('ads_id', 'tag'));
    }

    function deleteTagMap(string $tag): void
    {
        $query =
            "DELETE FROM
                ads_tag_map
            WHERE
                tag = :tag";

        DB::execute($query, compact('tag'));
    }

    /**
     * @return array<int, string>
     */
    function getAdsListAll(): array
    {
        $query =
            "SELECT
                id,
                ads_title
            FROM
                ads
            ORDER BY
                updated_at DESC";

        return DB::fetchAll($query, args: [\PDO::FETCH_KEY_PAIR]);
    }

    /**
     * @return array<string, int>
     */
    function getTagMapAll(): array
    {
        $query =
            "SELECT
                tag,
                ads_id
            FROM
                ads_tag_map";

        return DB::fetchAll($query, args: [\PDO::FETCH_KEY_PAIR]);
    }

    function getAdsByTag(string $tag, int $defaultAdsId): AdsDto|false
    {
        $query =
            "SELECT
                t1.id,
                t1.ads_title,
                t1.ads_sponsor_name,
                t1.ads_paragraph,
                t1.ads_href,
                t1.ads_img_url,
                t1.ads_title_button,
                t1.ads_tracking_url,
                t1.updated_at
            FROM
                ads AS t1
            WHERE
                t1.id = IFNULL(
                    (
                        SELECT
                            ads_id
                        FROM
                            ads_tag_map
                        WHERE
                            tag = :tag
                    ),
                    :defaultAdsId
                )";

        return DB::fetch(
            $query,
            compact('tag', 'defaultAdsId'),
            [\PDO::FETCH_CLASS, AdsDto::class]
        );
    }

    function getAdsById(int $id): AdsDto|false
    {
        $query =
            "SELECT
                id,
                ads_title,
                ads_sponsor_name,
                ads_paragraph,
                ads_href,
                ads_img_url,
                ads_tracking_url,
                ads_title_button,
                updated_at
            FROM
                ads
            WHERE
                id = :id";

        return DB::fetch(
            $query,
            compact('id'),
            [\PDO::FETCH_CLASS, AdsDto::class]
        );
    }

    /**
     *  @return AdsDto[]
     */
    function getAdsAll(): array
    {
        $query =
            "SELECT
                id,
                ads_title,
                ads_sponsor_name,
                ads_paragraph,
                ads_href,
                ads_img_url,
                ads_tracking_url,
                ads_title_button,
                updated_at
            FROM
                ads
            ORDER BY
                updated_at DESC";

        return DB::fetchAll(
            $query,
            args: [\PDO::FETCH_CLASS, AdsDto::class]
        );
    }

    function deleteAdsById(int $id): void
    {
        $query =
            "DELETE FROM
                ads
            WHERE
                id = :id";

        DB::execute(
            $query,
            compact('id')
        );
    }

    function updateAds(
        int $id,
        string $ads_title,
        string $ads_sponsor_name,
        string $ads_paragraph,
        string $ads_href,
        string $ads_img_url,
        string $ads_tracking_url,
        string $ads_title_button,
    ): void {
        $updated_at = (new \DateTime())->format('Y-m-d H:i:s');

        $query =
            "UPDATE
                ads
            SET
                ads_title = :ads_title,
                ads_sponsor_name = :ads_sponsor_name,
                ads_paragraph = :ads_paragraph,
                ads_href = :ads_href,
                ads_img_url = :ads_img_url,
                ads_tracking_url = :ads_tracking_url,
                ads_title_button = :ads_title_button,
                updated_at = :updated_at
            WHERE   
                id = :id";

        DB::execute($query, compact(
            'id',
            'ads_title',
            'ads_sponsor_name',
            'ads_paragraph',
            'ads_href',
            'ads_img_url',
            'ads_tracking_url',
            'ads_title_button',
            'updated_at',
        ));
    }
}
