<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Action Detail #<?php echo $log['id'] ?></title>
    <style>
        body { font-family: sans-serif; margin: 20px; background: #f5f5f5; }
        h1 { color: #333; margin-bottom: 5px; }
        .back-link { margin-bottom: 20px; }
        .back-link a { color: #1a73e8; text-decoration: none; }
        .back-link a:hover { text-decoration: underline; }
        .section { background: #fff; padding: 16px; margin-bottom: 16px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .section h2 { margin-top: 0; font-size: 16px; color: #555; border-bottom: 1px solid #eee; padding-bottom: 8px; }
        .field { margin-bottom: 8px; }
        .field-label { font-weight: bold; font-size: 12px; color: #666; }
        .field-value { font-size: 14px; word-break: break-all; }
        .field-value.mono { font-family: 'Consolas', 'Monaco', monospace; font-size: 13px; }
        .comment-text { white-space: pre-wrap; background: #f9f9f9; padding: 12px; border-radius: 4px; font-size: 14px; line-height: 1.6; }
        .image-grid { display: flex; flex-wrap: wrap; gap: 8px; }
        .image-grid a { display: block; }
        .image-grid img { max-width: 200px; max-height: 200px; border-radius: 4px; border: 1px solid #ddd; }
        .flag-form { margin-top: 16px; padding-top: 16px; border-top: 1px solid #eee; }
        .flag-form select { padding: 6px; font-size: 14px; }
        .flag-form button {
            padding: 6px 16px;
            background: #d32f2f;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        .flag-form button:hover { background: #b71c1c; }
    </style>
    <script>window.addEventListener('pageshow', function(e) { if (e.persisted) location.reload(); });</script>
</head>

<?php
    use App\Models\CommentRepositories\Enum\CommentLogType;
    $typeEnum = CommentLogType::tryFrom($log['type']);
    $typeLabel = $typeEnum ? $typeEnum->adminLabel($log['flag']) : $log['type'];
?>

<body>
    <div class="back-link">
        <a href="javascript:history.back()">&larr; Back</a> |
        <a href="<?php echo url('admin/log/admin-action') ?>">Admin Action Log List</a>
    </div>

    <h1>Admin Action Detail #<?php echo $log['id'] ?></h1>

    <div class="section">
        <h2>操作情報</h2>
        <div class="field">
            <div class="field-label">操作</div>
            <div class="field-value"><?php echo htmlspecialchars($typeLabel) ?></div>
        </div>
        <div class="field">
            <div class="field-label">操作日時</div>
            <div class="field-value mono"><?php echo htmlspecialchars($log['data']) ?></div>
        </div>
        <div class="field">
            <div class="field-label">対象ルーム</div>
            <div class="field-value">
                <?php if ($ocId): ?>
                    <a href="<?php echo url("oc/{$ocId}/admin") ?>" target="_blank"><?php echo htmlspecialchars($ocName ?: "ID:{$ocId}") ?></a>
                    (ID: <?php echo $ocId ?>)
                <?php else: ?>
                    -
                <?php endif; ?>
            </div>
        </div>
        <div class="field">
            <div class="field-label">comment_id (entity_id)</div>
            <div class="field-value mono"><?php echo $log['entity_id'] ?></div>
        </div>
    </div>

    <div class="section">
        <h2>コメント情報</h2>
        <?php if ($log['name'] !== null): ?>
            <div class="field">
                <div class="field-label">投稿者名</div>
                <div class="field-value"><?php echo htmlspecialchars($log['name'] ?: '匿名') ?></div>
            </div>
            <div class="field">
                <div class="field-label">投稿日時</div>
                <div class="field-value mono"><?php echo htmlspecialchars($log['comment_time'] ?? '-') ?></div>
            </div>
            <div class="field">
                <div class="field-label">現在のフラグ</div>
                <div class="field-value">
                    <?php
                        $currentFlag = $log['flag'];
                        $currentFlagLabel = $flagLabels[$currentFlag] ?? "flag={$currentFlag}";
                        echo htmlspecialchars("{$currentFlagLabel} ({$currentFlag})");
                    ?>
                </div>
            </div>
            <div class="field">
                <div class="field-label">コメント本文</div>
                <div class="comment-text"><?php echo htmlspecialchars($log['text'] ?? '') ?></div>
            </div>
        <?php else: ?>
            <p style="color: #999;">コメントは完全削除済みです。</p>
        <?php endif; ?>
    </div>

    <?php if ($posterLog): ?>
    <div class="section">
        <h2>投稿者情報</h2>
        <div class="field">
            <div class="field-label">user_id</div>
            <div class="field-value mono"><?php echo htmlspecialchars($log['user_id'] ?? '-') ?></div>
        </div>
        <div class="field">
            <div class="field-label">IP</div>
            <div class="field-value mono"><?php echo htmlspecialchars($posterLog['ip']) ?></div>
        </div>
        <div class="field">
            <div class="field-label">User Agent</div>
            <div class="field-value mono"><?php echo htmlspecialchars($posterLog['ua']) ?></div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($images)): ?>
    <div class="section">
        <h2>画像 (<?php echo count($images) ?>件)</h2>
        <div class="image-grid">
            <?php foreach ($images as $img): ?>
                <a href="<?php echo url('admin-api/comment-image') ?>?filename=<?php echo urlencode($img['filename']) ?>" target="_blank">
                    <img src="<?php echo url('admin-api/comment-image') ?>?filename=<?php echo urlencode($img['filename']) ?>" alt="comment image">
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($log['name'] !== null && $ocId && !empty($log['comment_seq_id'])): ?>
    <div class="section">
        <h2>フラグ変更</h2>
        <form class="flag-form" onsubmit="return confirm('フラグを変更しますか？')" action="<?php echo url('admin-api/deletecomment') ?>" method="POST">
            <input type="hidden" name="id" value="<?php echo $ocId ?>">
            <input type="hidden" name="commentId" value="<?php echo $log['comment_seq_id'] ?>">
            <select name="flag">
                <?php foreach ($flagLabels as $value => $label): ?>
                    <option value="<?php echo $value ?>" <?php echo $value === ($log['flag'] ?? -1) ? 'selected' : '' ?>><?php echo htmlspecialchars("{$label} ({$value})") ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit">変更</button>
        </form>
    </div>
    <?php endif; ?>
</body>

</html>
