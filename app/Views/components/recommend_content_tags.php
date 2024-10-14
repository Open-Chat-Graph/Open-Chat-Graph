<div class="list-aside">
    <h3 class="list-title">
        <span>関連のテーマ</span>
    </h3>
    <?php if (isset($desc)) : ?>
        <?php viewComponent('recommend_tag_desc') ?>
    <?php endif ?>
    <?php viewComponent('tag_list_section', compact('tags')) ?>
</div>