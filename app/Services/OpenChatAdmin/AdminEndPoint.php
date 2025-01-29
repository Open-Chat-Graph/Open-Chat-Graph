<?php

declare(strict_types=1);

namespace App\Services\OpenChatAdmin;

use App\Services\Recommend\RecommendUpdater;
use App\Services\Recommend\TagDefinition\Ja\RecommendUtility;
use Shared\Exceptions\BadRequestException as Bad;
use Shadow\Kernel\Reception as R;
use App\Models\Repositories\DB;

class AdminEndPoint
{
    function modifyTag(string $id)
    {
        if (!R::has('tag')) throw new Bad('tag is NULL');;

        if (!DB::fetchColumn('SELECT id FROM open_chat WHERE id = ' . $id)) throw new Bad("存在しないID: {$id}");

        if ($tag = R::input('tag')) {
            /** @var RecommendUpdater $recommendUpdater */
            $recommendUpdater = app(RecommendUpdater::class);
            $tags = $recommendUpdater->getAllTagNames();
            $tagWords = array_map(fn ($w) => RecommendUtility::extractTag($w), $tags);
            $key = array_search($tag, $tagWords);
            if ($key === false) throw new Bad("存在しないタグ: {$tag}");;

            $target = $tags[$key];
            DB::execute(
                "INSERT INTO modify_recommend (id, tag) VALUES({$id}, '{$target}') 
                    ON DUPLICATE KEY UPDATE id = {$id}, tag = '{$target}'"
            );
            DB::execute(
                "INSERT INTO recommend VALUES({$id}, '{$target}') 
                    ON DUPLICATE KEY UPDATE id = {$id}, tag = '{$target}'"
            );
        } else {
            DB::execute(
                "INSERT INTO modify_recommend (id, tag) VALUES({$id}, '') 
                    ON DUPLICATE KEY UPDATE id = {$id}, tag = ''"
            );
            DB::execute(
                "DELETE FROM recommend WHERE id = {$id}"
            );
        }
    }

    function deleteModifyTag(string $id)
    {
        DB::execute(
            "DELETE FROM modify_recommend WHERE id = {$id}"
        );
    }
}
