<?php

declare(strict_types=1);

namespace App\Models\Repositories;

use App\Models\Repositories\DB;
use App\Services\Sitemap\LastmodPolicy;

class OcSitemapLastmodRepository implements OcSitemapLastmodRepositoryInterface
{
    public function refreshLastmod(): int
    {
        DB::connect();

        // 閾値式は LastmodPolicy::significanceThreshold() と一致させること:
        //   max(ceil(snapshot * 0.01), 5)  ==  GREATEST(CEILING(snapshot * 0.01), 5)
        $ratio = LastmodPolicy::RELATIVE_RATIO;
        $floor = LastmodPolicy::ABSOLUTE_FLOOR;

        $sql =
            "INSERT INTO oc_sitemap_lastmod (open_chat_id, lastmod, member_snapshot)
             SELECT o.id, CURRENT_TIMESTAMP, o.member
               FROM open_chat o
               LEFT JOIN oc_sitemap_lastmod l ON l.open_chat_id = o.id
              WHERE l.open_chat_id IS NULL
                 OR o.updated_at > l.lastmod
                 OR ABS(o.member - l.member_snapshot)
                      >= GREATEST(CEILING(l.member_snapshot * {$ratio}), {$floor})
             ON DUPLICATE KEY UPDATE
                 lastmod         = CURRENT_TIMESTAMP,
                 member_snapshot = VALUES(member_snapshot)";

        return DB::execute($sql)->rowCount();
    }
}
