<section class="tag-list-section">
    <ul class="tag-list">
        <?php foreach ($tags as $key => $word) : ?>
            <li>
                <a class="tag-btn" href="<?php echo url('recommend?tag=' . urlencode(htmlspecialchars_decode($word))) ?>">
                    <?php echo \App\Services\Recommend\TagDefinition\Ja\RecommendUtility::extractTag($word) ?>
                </a>
            </li>
        <?php endforeach ?>
    </ul>
</section>