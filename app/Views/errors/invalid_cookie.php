<?php
$_meta = meta()->setTitle("400 Bad Request")
    ->setDescription('ページを再読み込みしてから、もう一度送信してください。')
    ->setOgpDescription('ページを再読み込みしてから、もう一度送信してください。');

$_css = ['components/room_list', 'components/site_header', 'components/site_footer'];
?>

<!DOCTYPE html>
<html lang="<?php echo t('ja') ?>">
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
        <?php // mvp.css は素の header を装飾しなくなったため、中央寄せと余白（旧 header{padding:3rem 1rem} 相当）はここで指定 ?>
        <header style="text-align: center; padding: 0 1rem 3rem;">
            <h1>400</h1>
            <h2>Bad Request</h2>
            <br>
            <p>ページの有効期限が切れたか、Cookie が無効になっています。<br>ページを再読み込みしてから、もう一度送信してください。</p>
        </header>
    </main>
    <footer style="margin-top: 3rem;">
        <?php viewComponent('footer_inner') ?>
    </footer>
    <script defer src="<?php echo fileUrl("/js/site_header_footer.js", urlRoot: '') ?>"></script>
</body>

</html>