<!-- @param array $openChatList (optional) -->
<!-- @param string $_now -->
<!-- @param string $_select (optional) -->
<!-- @param string $_label (optional) -->
<!-- @param array $_pagerNavArg (optional) -->
<!-- @param int $totalRecords -->
<!-- @param int $maxPageNumber -->
<!-- @param int $page -->
<!-- @param string $titleValue -->
<div class="rb-results-inner"
    data-total-records="<?php echo $totalRecords ?>"
    data-max-page="<?php echo $maxPageNumber ?>"
    data-page="<?php echo $page ?>"
    data-title="<?php echo $titleValue ?>">
    <!-- select要素ページネーション -->
    <?php if (isset($_select)) : ?>
        <nav class="page-select unset" style="flex-direction: column; padding: 1rem 0 0 0; margin: 0 0 12px 0;">
            <div style="font-weight: bold; font-size: 13px;">
                1ページあたり50件の表示
            </div>
            <form class="unset" style="width: 100%;">
                <select id="page-selector" class="unset">
                    <?php echo $_select ?>
                </select>
                <label for="page-selector" class="unset"><span><?php echo $_label ?></span></label>
            </form>
        </nav>
    <?php endif ?>
    <small class="rb-count"><?php echo number_format($totalRecords) ?>件の結果 (<?php echo $maxPageNumber ?>ページ中/<?php echo $page ?>ページ目)</small>
    <?php if (isset($openChatList)) : ?>
        <?php viewComponent('open_chat_list_ranking_ban', compact('openChatList', '_now')) ?>
    <?php else : ?>
        <p class="rb-empty">0件の結果</p>
    <?php endif ?>
    <!-- 次のページ・前のページボタン -->
    <?php if (isset($_pagerNavArg)) : ?>
        <?php viewComponent('pager_nav_ranking_ban', $_pagerNavArg) ?>
    <?php endif ?>
</div>
