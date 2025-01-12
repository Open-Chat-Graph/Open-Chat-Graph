<?php
$_meta = meta()->setTitle("400 Bad Request")
    ->setDescription('Cookie を有効にしてください。')
    ->setOgpDescription('Cookie を有効にしてください。');

$_css = ['room_list', 'site_header', 'site_footer'];
?>

<!DOCTYPE html>
<html lang="<?php echo t('ja_JP') ?>">
<?php viewComponent('head', compact('_css', '_meta')) ?>
<style>
    /* Increase size of the main heading */
    h1 {
        font-size: 5rem;
    }

    /* Break long lines in the code section */
    code {
        word-wrap: break-word;
    }

    /* Set width, center, and add padding to the ordered list */
    ol {
        width: fit-content;
        margin: 0 auto;
        margin-top: 1.5rem;
        padding: 0 1rem;
    }

    /* Break URLs to fit in the list */
    a {
        word-break: break-all;
    }
</style>

<body>
    <!-- 固定ヘッダー -->
    <?php viewComponent('site_header') ?>
    <main>
        <header>
            <h1>400</h1>
            <h2>Bad Request</h2>
            <br>
            <p>Cookie を有効にしてください。</p>
        </header>
    </main>
    <footer style="margin-top: 3rem;">
        <?php viewComponent('footer_inner') ?>
    </footer>
    <script defer src="<?php echo fileUrl("/js/site_header_footer.js", urlRoot: '') ?>"></script>
</body>

</html>