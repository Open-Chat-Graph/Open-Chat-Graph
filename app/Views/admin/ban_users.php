<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shadow Ban Users</title>
    <style>
        body { font-family: sans-serif; margin: 20px; background: #f5f5f5; }
        h1 { color: #333; margin-bottom: 5px; }
        .back-link { margin-bottom: 20px; }
        .back-link a { color: #1a73e8; text-decoration: none; margin-right: 16px; }
        .back-link a:hover { text-decoration: underline; }
        .pagination { margin: 20px 0; }
        .pagination select { padding: 5px; font-size: 14px; }
        .pagination button { padding: 5px 15px; font-size: 14px; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        th, td { padding: 8px 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f9f9f9; font-weight: bold; position: sticky; top: 0; }
        tr:hover { background: #f5f5f5; }
        .date-col { width: 130px; white-space: nowrap; font-family: monospace; font-size: 10px; }
        .id-col { width: 50px; font-family: monospace; font-size: 12px; }
        .uid-col { max-width: 120px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-family: monospace; font-size: 11px; }
        .ip-col { font-family: monospace; font-size: 11px; white-space: nowrap; }
        .name-col { max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 13px; }
        .btn-unban {
            padding: 4px 10px;
            background: #e53935;
            color: #fff;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            text-decoration: none;
            font-size: 12px;
        }
        .btn-unban:hover { background: #c62828; }

        @media (max-width: 768px) {
            body { margin: 10px; }
            table, thead, tbody, th, td, tr { display: block; }
            thead { display: none; }
            tr {
                margin-bottom: 12px;
                border: 1px solid #ddd;
                border-radius: 4px;
                padding: 8px;
                background: #fff;
            }
            td {
                padding: 4px 8px;
                border-bottom: none;
                max-width: 100%;
            }
            td::before {
                content: attr(data-label);
                font-weight: bold;
                font-size: 10px;
                color: #666;
                display: block;
                margin-bottom: 2px;
            }
            .uid-col { max-width: 100%; }
            .name-col { max-width: 100%; white-space: normal; }
        }
    </style>
    <script>window.addEventListener('pageshow', function(e) { if (e.persisted) location.reload(); });</script>
</head>

<body>
    <div class="back-link">
        <a href="<?php echo url('admin/log') ?>">&larr; Back to Log List</a>
        <a href="<?php echo url('admin/log/admin-action') ?>">Admin Action Log</a>
    </div>

    <h1>Shadow Ban Users</h1>
    <p>Page <?php echo $currentPage ?> / <?php echo $totalPages ?> (<?php echo $totalCount ?>件, 50件/ページ)</p>

    <?php if ($totalPages > 1): ?>
    <form method="get" class="pagination">
        <label>Page:
            <?php if ($totalPages <= 100): ?>
                <select name="page">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <option value="<?php echo $i ?>" <?php echo $i === $currentPage ? 'selected' : '' ?>><?php echo $i ?></option>
                    <?php endfor; ?>
                </select>
            <?php else: ?>
                <input type="number" name="page" value="<?php echo $currentPage ?>" min="1" max="<?php echo $totalPages ?>" style="width: 80px;">
            <?php endif; ?>
        </label>
        <button type="submit">Go</button>
    </form>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th class="id-col">ID</th>
                <th class="uid-col">user_id</th>
                <th class="ip-col">IP</th>
                <th class="date-col">バン日時</th>
                <th class="name-col">ユーザー名</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($banUsers)): ?>
                <tr><td colspan="6">バンユーザーはいません。</td></tr>
            <?php else: ?>
                <?php foreach ($banUsers as $ban): ?>
                <tr>
                    <td class="id-col" data-label="ID"><?php echo $ban['id'] ?></td>
                    <td class="uid-col" data-label="user_id" title="<?php echo htmlspecialchars($ban['user_id']) ?>"><?php echo htmlspecialchars(mb_strimwidth($ban['user_id'], 0, 16, '...')) ?></td>
                    <td class="ip-col" data-label="IP"><?php echo htmlspecialchars($ban['ip']) ?></td>
                    <td class="date-col" data-label="バン日時"><?php echo htmlspecialchars($ban['created_at']) ?></td>
                    <td class="name-col" data-label="ユーザー名" title="<?php echo htmlspecialchars($ban['name']) ?>"><?php echo htmlspecialchars($ban['name'] ?: '-') ?></td>
                    <td data-label="操作">
                        <a class="btn-unban" href="<?php echo url('admin-api/unbanuser') ?>?banId=<?php echo $ban['id'] ?>">解除</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ($totalPages > 1): ?>
    <form method="get" class="pagination">
        <label>Page:
            <?php if ($totalPages <= 100): ?>
                <select name="page">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <option value="<?php echo $i ?>" <?php echo $i === $currentPage ? 'selected' : '' ?>><?php echo $i ?></option>
                    <?php endfor; ?>
                </select>
            <?php else: ?>
                <input type="number" name="page" value="<?php echo $currentPage ?>" min="1" max="<?php echo $totalPages ?>" style="width: 80px;">
            <?php endif; ?>
        </label>
        <button type="submit">Go</button>
    </form>
    <?php endif; ?>
</body>

</html>
