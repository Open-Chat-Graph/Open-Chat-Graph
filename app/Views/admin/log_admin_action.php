<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Action Log</title>
    <style>
        body { font-family: sans-serif; margin: 20px; background: #f5f5f5; }
        h1 { color: #333; margin-bottom: 5px; }
        .back-link { margin-bottom: 20px; }
        .back-link a { color: #1a73e8; text-decoration: none; }
        .back-link a:hover { text-decoration: underline; }
        .pagination { margin: 20px 0; }
        .pagination select { padding: 5px; font-size: 14px; }
        .pagination button { padding: 5px 15px; font-size: 14px; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        th, td { padding: 8px 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f9f9f9; font-weight: bold; position: sticky; top: 0; }
        tr:hover { background: #f5f5f5; }
        .date-col { width: 130px; white-space: nowrap; font-family: monospace; font-size: 10px; }
        .oc-col { max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 10px; }
        .text-col {
            max-width: 250px;
            font-size: 13px;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
        }
        .type-col { font-size: 12px; white-space: nowrap; }
        .btn-detail {
            padding: 4px 10px;
            background: #1a73e8;
            color: #fff;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            text-decoration: none;
            font-size: 12px;
        }
        .btn-detail:hover { background: #1557b0; }

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
            .text-col { max-width: 100%; }
            .oc-col { max-width: 100%; white-space: normal; }
        }
    </style>
    <script>window.addEventListener('pageshow', function(e) { if (e.persisted) location.reload(); });</script>
</head>

<?php use App\Models\CommentRepositories\Enum\CommentLogType; ?>

<body>
    <div class="back-link">
        <a href="<?php echo url('admin/log') ?>">&larr; Back to Log List</a>
        <a href="<?php echo url('admin/ban-users') ?>" style="margin-left: 16px;">Shadow Ban Users</a>
    </div>

    <h1>Admin Action Log</h1>
    <p>Page <?php echo $currentPage ?> / <?php echo $totalPages ?> (<?php echo $totalCount ?>件, 50件/ページ)</p>

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

    <table>
        <thead>
            <tr>
                <th class="date-col">日時</th>
                <th class="type-col">操作</th>
                <th class="oc-col">ルーム</th>
                <th class="text-col">コメント</th>
                <th>詳細</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)): ?>
                <tr><td colspan="5">操作ログはありません。</td></tr>
            <?php else: ?>
                <?php foreach ($logs as $log):
                    $ocId = $log['open_chat_id'] ?? null;
                    $ocNameDisplay = $ocId ? ($ocNames[$ocId] ?? "ID:{$ocId}") : '-';
                    $typeEnum = CommentLogType::tryFrom($log['type']);
                    $typeLabel = $typeEnum ? $typeEnum->adminLabel($log['flag']) : $log['type'];
                ?>
                <tr>
                    <td class="date-col" data-label="日時"><?php echo htmlspecialchars($log['data']) ?></td>
                    <td class="type-col" data-label="操作"><?php echo htmlspecialchars($typeLabel) ?></td>
                    <td class="oc-col" data-label="ルーム" title="<?php echo htmlspecialchars($ocNameDisplay) ?>">
                        <?php if ($ocId): ?>
                            <a href="<?php echo url("oc/{$ocId}/admin") ?>" target="_blank"><?php echo htmlspecialchars($ocNameDisplay) ?></a>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td class="text-col" data-label="コメント"><?php echo htmlspecialchars($log['text'] ?? ($log['flag'] === null ? '(完全削除済み)' : '')) ?></td>
                    <td data-label="詳細">
                        <a class="btn-detail" href="<?php echo url('admin/log/admin-action/detail') ?>?id=<?php echo $log['id'] ?>" target="_blank">View</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

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
</body>

</html>
