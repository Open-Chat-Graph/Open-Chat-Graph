<?php

namespace App\Views\Meta;

use App\Views\Meta\Metadata;

class OcPageMeta
{
    function generateMetadata(int $open_chat_id, array $oc): Metadata
    {
        $name = $oc['name'];

        $desc = truncateDescription($oc['description'], 160) ?: (t('LINEオープンチャット') . sprintfT('「%s」', $oc['name']));
        
        return meta()
            ->setTitle($name)
            ->setDescription("{$desc}")
            ->setOgpDescription("{$desc}");
    }
}
