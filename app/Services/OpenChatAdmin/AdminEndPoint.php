<?php

declare(strict_types=1);

namespace App\Services\OpenChatAdmin;

use App\Models\RecommendRepositories\ModifyRecommendRepositoryInterface;
use App\Models\Repositories\OpenChatPageRepositoryInterface;
use App\Services\Recommend\RecommendUpdater;
use App\Services\Recommend\TagDefinition\Ja\RecommendUtility;
use Shared\Exceptions\BadRequestException as Bad;
use Shadow\Kernel\Reception as R;

class AdminEndPoint
{
    function __construct(
        private OpenChatPageRepositoryInterface $openChatRepository,
        private ModifyRecommendRepositoryInterface $modifyRecommendRepository,
    ) {
    }

    function modifyTag(string $id)
    {
        if (!R::has('tag')) throw new Bad('tag is NULL');;

        if (!$this->openChatRepository->isExistsOpenChat((int) $id)) throw new Bad("存在しないID: {$id}");

        if ($tag = R::input('tag')) {
            /** @var RecommendUpdater $recommendUpdater */
            $recommendUpdater = app(RecommendUpdater::class);
            $tags = $recommendUpdater->getAllTagNames();
            $tagWords = array_map(fn ($w) => RecommendUtility::extractTag($w), $tags);
            $key = array_search($tag, $tagWords);
            if ($key === false) throw new Bad("存在しないタグ: {$tag}");;

            $target = $tags[$key];
            $this->modifyRecommendRepository->upsertModifyTag((int) $id, $target);
            $this->modifyRecommendRepository->upsertRecommendTag((int) $id, $target);
        } else {
            $this->modifyRecommendRepository->upsertModifyTag((int) $id, '');
            $this->modifyRecommendRepository->deleteRecommendTag((int) $id);
        }
    }

    function deleteModifyTag(string $id)
    {
        $this->modifyRecommendRepository->deleteModifyTag((int) $id);
    }
}
