<?php

namespace App\Views\Meta;

use App\Views\Classes\CollapseKeywordEnumerations;
use App\Views\Meta\Metadata;

class OcPageMeta
{
    /**
     * @param array<string,mixed> $oc       オープンチャット情報 (name / description 等)
     * @param array<string,mixed>|null $narrative narrative service の戻り値。
     *   形式: ['meta_description' => string, 'summary' => string, 'detail' => string, ...]
     *   null または 'meta_description' 欠落時は既存ロジック (LINE description) を完全維持。
     */
    function generateMetadata(int $open_chat_id, array $oc, ?array $narrative = null): Metadata
    {
        $name = $oc['name'];

        // narrative がある場合は LINE 公式 description との差別化のため独自 description を優先
        if (!empty($narrative) && !empty($narrative['meta_description'])) {
            $desc = $narrative['meta_description'];
        } else {
            $desc = $oc['description'] ?: (t('LINEオープンチャット') . sprintfT('「%s」', $oc['name']));
        }

        return meta()
            ->setTitle($name)
            ->setDescription("{$desc}")
            ->setOgpDescription("{$desc}");
    }
}
