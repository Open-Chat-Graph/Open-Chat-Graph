<!-- @param string $path -->
<!-- @param string $_queryString -->
<!-- @param int $pageNumber -->
<!-- @param ing $maxPageNumber -->
<nav class="search-pager">
    <?php

    use App\Views\SelectElementPagination;

    if ($pageNumber < $maxPageNumber) : ?>
        <div class="button01 prev">
            <a href="<?php echo SelectElementPagination::pagerUrl($path, $pageNumber + 1, $maxPageNumber) . ($_queryString ?? '') ?>">
                <?php echo $pageNumber + 1 ?>
                ページへ
            </a>
        </div>
    <?php endif ?>
    <span class="button01label"><?php echo $pageNumber . ' / ' . $maxPageNumber ?></span>
    <?php if ($pageNumber > 1) : ?>
        <div class="button01 next">
            <a href="<?php echo SelectElementPagination::pagerUrl($path, $pageNumber - 1, $maxPageNumber) . ($_queryString ?? '') ?>">過去のページへ</a>
        </div>
    <?php endif ?>
</nav>