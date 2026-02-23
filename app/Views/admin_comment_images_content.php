<!DOCTYPE html>
<html lang="ja">

<?php
$_meta = meta();
$_meta->title = 'コメント画像管理';
$_css = ['site_header', 'site_footer'];
viewComponent('policy_head', compact('_css', '_meta'));
?>
<script src="https://cdn.tailwindcss.com/3.4.17"></script>
<script>
    tailwind.config = { corePlugins: { preflight: false } }
    window.addEventListener('pageshow', function(e) { if (e.persisted) location.reload(); });
</script>

<body>
    <div class="body">
        <?php viewComponent('site_header') ?>
        <main class="max-w-5xl mx-auto px-4 py-6" style="overflow: hidden;">
            <h2 class="text-xl font-bold mb-4">コメント画像管理</h2>

            <section class="mb-6 p-4 bg-gray-50 rounded-lg">
                <h3 class="text-base font-semibold mb-2">統計</h3>
                <div class="flex gap-6 text-sm">
                    <span>総画像数: <b><?php echo $stats['count'] ?></b>件</span>
                    <span>全体容量: <b id="total-size">計算中...</b></span>
                    <span>削除済み容量: <b id="deleted-size">計算中...</b></span>
                </div>
            </section>
            <script>
                fetch('<?php echo url("admin-api/commentimagestorage") ?>', { method: 'POST' })
                    .then(r => r.json())
                    .then(d => {
                        document.getElementById('total-size').textContent =
                            (d.total_size / 1024 / 1024).toFixed(2) + ' MB';
                        document.getElementById('deleted-size').textContent =
                            (d.deleted_size / 1024 / 1024).toFixed(2) + ' MB';
                    })
                    .catch(() => {
                        document.getElementById('total-size').textContent = '取得失敗';
                        document.getElementById('deleted-size').textContent = '取得失敗';
                    });
            </script>

            <section>
                <div class="flex items-center gap-4 mb-4">
                    <div class="flex gap-1">
                        <a href="?tab=active"
                            class="px-3 py-1.5 rounded text-sm no-underline border <?php echo $tab === 'active' ? 'bg-gray-800 text-white border-gray-800' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-100' ?>">
                            掲載中
                        </a>
                        <a href="?tab=deleted"
                            class="px-3 py-1.5 rounded text-sm no-underline border <?php echo $tab === 'deleted' ? 'bg-gray-800 text-white border-gray-800' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-100' ?>">
                            削除済み
                        </a>
                    </div>

                    <span class="text-sm text-gray-500">
                        <?php echo $tab === 'active' ? '掲載中の画像' : '削除済みコメントの画像' ?> (<?php echo $totalCount ?>件)
                    </span>

                    <?php if ($tab === 'deleted' && !empty($images)): ?>
                        <form method="post" action="<?php echo url('admin-api/deletedcommentimages') ?>" class="ml-auto">
                            <button type="submit" onclick="return confirm('削除済みコメントの画像を全て物理削除しますか？')"
                                class="px-3 py-1 text-xs bg-red-600 text-white rounded border-0 cursor-pointer hover:bg-red-700">
                                全て物理削除
                            </button>
                        </form>
                    <?php endif; ?>
                </div>

                <?php if ($totalPages > 1): ?>
                    <div class="mb-3 text-sm">
                        <label class="mr-1">ページ:</label>
                        <select onchange="location.href='?tab=<?php echo $tab ?>&page='+this.value"
                            class="border border-gray-300 rounded px-2 py-1 text-sm">
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <option value="<?php echo $i ?>" <?php echo $i === $page ? 'selected' : '' ?>><?php echo $i ?> / <?php echo $totalPages ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <?php if (!empty($images)): ?>
                    <div class="grid gap-2" style="grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));">
                        <?php foreach ($images as $img): ?>
                            <div class="border border-gray-200 rounded p-2 pb-3 text-center text-xs">
                                <?php $imgUrl = url('comment-img/' . substr($img['filename'], 0, 2) . '/' . $img['filename']); ?>
                                <a href="<?php echo $imgUrl ?>" target="_blank" rel="noopener" class="block mb-3">
                                    <img src="<?php echo $imgUrl ?>"
                                        alt="" loading="lazy"
                                        class="w-full rounded-sm bg-gray-100" style="height:80px;object-fit:cover;">
                                </a>
                                <?php if ($tab === 'active'): ?>
                                    <div class="truncate leading-relaxed">
                                        <a href="<?php echo url('oc/' . $img['open_chat_id']) ?>" target="_blank" rel="noopener"
                                            class="text-blue-600 hover:underline">
                                            <?php echo htmlspecialchars($openChatNames[$img['open_chat_id']] ?? 'ID:' . $img['open_chat_id']) ?>
                                        </a>
                                    </div>
                                    <div class="text-gray-500 mt-1">コメント #<?php echo $img['comment_number'] ?></div>
                                <?php else: ?>
                                    <div class="text-gray-500">#<?php echo $img['id'] ?> / c:<?php echo $img['comment_id'] ?></div>
                                    <form method="post" action="<?php echo url('admin-api/deletecommentimage') ?>" class="inline">
                                        <input type="hidden" name="imageId" value="<?php echo $img['id'] ?>">
                                        <button type="submit" onclick="return confirm('この画像を物理削除しますか？')"
                                            class="mt-1 px-2 py-0.5 text-xs bg-gray-700 text-white rounded border-0 cursor-pointer hover:bg-gray-900">
                                            削除
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-sm text-gray-500">画像はありません</p>
                <?php endif; ?>

                <?php if ($totalPages > 1): ?>
                    <div class="mt-4 text-sm">
                        <label class="mr-1">ページ:</label>
                        <select onchange="location.href='?tab=<?php echo $tab ?>&page='+this.value"
                            class="border border-gray-300 rounded px-2 py-1 text-sm">
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <option value="<?php echo $i ?>" <?php echo $i === $page ? 'selected' : '' ?>><?php echo $i ?> / <?php echo $totalPages ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                <?php endif; ?>
            </section>
        </main>
        <footer>
            <?php viewComponent('footer_inner') ?>
        </footer>
    </div>
</body>

</html>
