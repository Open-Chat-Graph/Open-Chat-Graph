<!-- @param array $openChatList (optional) -->
<!-- @param string $_now -->
<!-- @param string $_select (optional) -->
<!-- @param string $_label (optional) -->
<!-- @param array $_pagerNavArg (optional) -->
<!-- @param int $totalRecords -->
<!-- @param int $maxPageNumber -->
<!-- @param int $page -->
<!-- @param string $titleValue -->
<!-- @param int $percent (optional・0件時の逃げ道表示用) -->
<!-- @param int $dmin (optional・0件時の期間解除ボタン表示用) -->
<!-- @param int $dmax (optional・同上) -->
<div class="rb-results-inner"
    data-total-records="<?php echo $totalRecords ?>"
    data-max-page="<?php echo $maxPageNumber ?>"
    data-page="<?php echo $page ?>"
    data-title="<?php echo $titleValue ?>">
    <!-- select要素ページネーション -->
    <?php if (isset($_select)) : ?>
        <nav class="page-select unset" style="flex-direction: column; padding: 1rem 0 0 0; margin: 0 0 12px 0;">
            <div style="font-weight: bold; font-size: 13px;">
                1ページあたり200件の表示
            </div>
            <form class="unset" style="width: 100%;">
                <select id="page-selector" class="unset">
                    <?php echo $_select ?>
                </select>
                <label for="page-selector" class="unset"><span><?php echo $_label ?></span></label>
            </form>
        </nav>
    <?php endif ?>
    <small class="rb-count"><?php echo number_format($totalRecords) ?><?php echo ($hasMore ?? false) ? '件以上' : '件' ?>の結果 (<?php echo $maxPageNumber ?>ページ中/<?php echo $page ?>ページ目)</small>
    <?php if (isset($openChatList)) : ?>
        <?php viewComponent('open_chat_list_ranking_ban', compact('openChatList', '_now')) ?>
    <?php else : ?>
        <div class="rb-empty">
            <p>0件の結果</p>
            <?php if (isset($percent) && $percent < 100) : ?>
                <p class="rb-empty-hint">いまは「ふつうの順位落ち」（最後に載っていた順位が下位の部屋）を除外しています。<br>探している部屋は、除外された中にあるかもしれません。</p>
                <button type="button" class="rb-widen">除外せずにすべて表示する</button>
            <?php else : ?>
                <p class="rb-empty-hint">条件に合う部屋が見つかりませんでした。キーワードや期間の条件をゆるめてみてください。</p>
            <?php endif ?>
            <?php if (isset($dmin, $dmax) && ($dmin > 0 || $dmax > 0)) : ?>
                <button type="button" class="rb-clear-duration">「消えていた期間」の絞り込みを解除する</button>
            <?php endif ?>
        </div>
    <?php endif ?>
    <!-- 次のページ・前のページボタン -->
    <?php if (isset($_pagerNavArg)) : ?>
        <?php viewComponent('pager_nav_ranking_ban', $_pagerNavArg) ?>
    <?php endif ?>
</div>
