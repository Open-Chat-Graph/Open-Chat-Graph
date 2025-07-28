<section class="tag-list-section">
    <ul class="tag-list">
        <?php

use App\Config\AppConfig;

 foreach (array_slice($tags, 0, AppConfig::$tagListLimit) as $key => $word) : ?>
            <li>
                <a class="tag-btn" href="<?php echo url('recommend/' . urlencode(htmlspecialchars_decode($word))) ?>">
                    <?php echo \App\Services\Recommend\TagDefinition\Ja\RecommendUtility::extractTag($word) ?>
                </a>
            </li>
        <?php endforeach ?>
    </ul>
</section>