<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cron Log - <?php echo htmlspecialchars($type) ?></title>
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
        .date-col { width: 160px; white-space: nowrap; font-family: monospace; }
        .message-col { word-break: break-all; }
        tr:hover { background: #f5f5f5; }
    </style>
</head>

<body>
    <div class="back-link">
        <a href="<?php echo url('admin/log') ?>">&larr; Back to Log List</a>
    </div>

    <h1>Cron Log: <?php echo htmlspecialchars($type) ?></h1>
    <p>Page <?php echo $currentPage ?> / <?php echo $totalPages ?> (1000 items per page, newest first)</p>

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
                <th class="date-col">Date</th>
                <th class="message-col">Message</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)): ?>
                <tr><td colspan="2">No log entries found.</td></tr>
            <?php else: ?>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td class="date-col"><?php echo htmlspecialchars($log['date']) ?></td>
                    <td class="message-col"><?php echo htmlspecialchars($log['message']) ?></td>
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
