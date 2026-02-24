<?php

/** @var \App\Services\OpenChatAdmin\Dto\AdminOpenChatDto $_adminDto */
?>
<div style="padding: 0 1rem;">
    <form action="/admin-api" method="POST" style="margin: 1rem 0;">
        <b>タグ: <?php echo $_adminDto->recommendTag ?: '無し' ?></b>
        <label>タグ変更</label>
        <input type="text" name="tag">
        <input type="hidden" name="id" value="<?php echo $_adminDto->id ?>">
        <input type="hidden" name="type" value="modifyTag">
        <input type="submit">
    </form>
    <form action="/admin-api" method="POST" style="margin: 1rem 0;">
        <b>Modifyタグ: <?php echo $_adminDto->modifyTag !== false ? ($_adminDto->modifyTag ?: '空文字') : '無し' ?></b>
        <label>タグを削除</label>
        <input type="hidden" name="id" value="<?php echo $_adminDto->id ?>">
        <input type="hidden" name="type" value="deleteModifyTag">
        <input type="submit">
    </form>
    <form action="/admin-api/deletecomment" method="POST" style="margin: 1rem 0;">
        <label for="comments-delete">コメントのフラグを変更</label>
        <select name="commentId" id="comments-delete" style="width: 5rem; font-size:1rem">
            <?php foreach ($_adminDto->commentIdArray as $commentId) : ?>
                <option value="<?php echo $commentId ?>"><?php echo $commentId ?></option>
            <?php endforeach ?>
        </select>
        <label for="delete-flag">Flag</label>
        <?php $flagLabels = \App\Config\AppConfig::COMMENT_FLAG_LABELS; ?>
        <select name="flag" id="delete-flag" style="width: 5rem; font-size:1rem">
            <?php foreach ([1, 2, 5, 4, 0, 3] as $v): ?>
                <option value="<?php echo $v ?>"><?php echo $flagLabels[$v] ?></option>
            <?php endforeach ?>
        </select>
        <input type="hidden" name="id" value="<?php echo $_adminDto->id ?>">
        <input type="submit">
    </form>
    <form action="/admin-api/deleteuser" method="POST" style="margin: 1rem 0;">
        <label for="user-delete">ユーザーをシャドウバン</label>
        <select name="commentId" id="user-delete" style="width: 5rem; font-size:1rem">
            <?php foreach ($_adminDto->commentIdArray as $commentId) : ?>
                <option value="<?php echo $commentId ?>"><?php echo $commentId ?></option>
            <?php endforeach ?>
        </select>
        <input type="hidden" name="id" value="<?php echo $_adminDto->id ?>">
        <input type="submit">
    </form>
    <form action="/admin-api/deletecommentsall" method="POST" style="margin: 1rem 0;">
        <input type="hidden" name="id" value="<?php echo $_adminDto->id ?>">
        <input type="submit" value="全コメントを削除（通常削除）">
    </form>
    <form action="/admin-api/restorecommentsall" method="POST" style="margin: 1rem 0;">
        <input type="hidden" name="id" value="<?php echo $_adminDto->id ?>">
        <input type="submit" value="削除を一斉復元（シャドウ削除・通常削除）">
    </form>
    <form action="/admin-api/harddeletecommentsall" method="POST" style="margin: 1rem 0;">
        <input type="hidden" name="id" value="<?php echo $_adminDto->id ?>">
        <input type="submit" value="全コメントを完全削除">
    </form>
    <form action="/admin-api/bulkshadowban" method="POST" style="margin: 1rem 0;">
        <input type="hidden" name="id" value="<?php echo $_adminDto->id ?>">
        <input type="submit" value="一斉シャドウバン（全投稿者BAN + 全コメントシャドウ削除）">
    </form>
    <?php if ($_adminDto->commentBanRemainingDays !== null): ?>
        <form action="/admin-api/commentunbanroom" method="POST" style="margin: 1rem 0;">
            <input type="hidden" name="id" value="<?php echo $_adminDto->id ?>">
            <input type="submit" value="コメント禁止を解除（残り<?php echo $_adminDto->commentBanRemainingDays ?>日）">
        </form>
    <?php else: ?>
        <form action="/admin-api/commentbanroom" method="POST" style="margin: 1rem 0;">
            <input type="hidden" name="id" value="<?php echo $_adminDto->id ?>">
            <input type="submit" value="コメントを1週間禁止">
        </form>
    <?php endif ?>
    <div style="margin: 1rem 0;">
        <a href="<?php echo url('admin/log/admin-action') ?>" target="_blank">操作ログ</a>
    </div>
</div>
