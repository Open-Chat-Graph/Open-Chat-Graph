<?php
$isJapanese = $type === 'ja-cron';
$pageTitle = $isJapanese ? 'サイト更新ログ詳細' : "Cron Log - {$type}";
?>
<!DOCTYPE html>
<html lang="<?php echo $isJapanese ? 'ja' : 'en' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php if ($_meta): ?>
        <?php echo $_meta ?>
        <meta name="robots" content="noindex, nofollow">
    <?php else: ?>
        <title><?php echo htmlspecialchars($pageTitle) ?></title>
    <?php endif; ?>
    <style>
        body { font-family: sans-serif; margin: 0; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; }
        h1 { color: #333; margin-bottom: 5px; }
        .back-link { margin-bottom: 20px; }
        .back-link a { color: #1a73e8; text-decoration: none; }
        .back-link a:hover { text-decoration: underline; }
        .pagination { margin: 20px 0; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .pagination select { padding: 5px; font-size: 14px; }
        .pagination input[type="number"] { padding: 5px; font-size: 14px; }
        .pagination button { padding: 5px 15px; font-size: 14px; cursor: pointer; }
        .pagination .nav-btn {
            width: 40px; height: 40px;
            display: inline-flex; align-items: center; justify-content: center;
            border: 1px solid #ccc; border-radius: 4px;
            background: #fff; color: #333;
            text-decoration: none; font-size: 18px;
            cursor: pointer;
        }
        .pagination .nav-btn:hover:not(.disabled) { background: #f0f0f0; }
        .pagination .nav-btn.disabled {
            background: #f5f5f5; color: #bbb; border-color: #ddd;
            cursor: not-allowed; pointer-events: none;
        }
        table { width: 100%; border-collapse: collapse; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        th, td { padding: 8px 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f9f9f9; font-weight: bold; position: sticky; top: 0; }
        .date-col { width: 160px; white-space: nowrap; font-family: monospace; }
        .message-col { word-break: break-all; overflow-wrap: anywhere; }
        .source-link { margin-left: 1em; font-family: monospace; font-size: 12px; word-break: break-all; overflow-wrap: anywhere; }
        .source-link a { color: #1a73e8; text-decoration: none; }
        .source-link a:hover { text-decoration: underline; }
        tr:hover { background: #f5f5f5; }

        /* プロセスタグのスタイル */
        .process-tag { display: inline-flex; align-items: center; gap: 4px; margin-right: 8px; }
        .process-tag-time {
            display: inline-block;
            background: #e3f2fd;
            color: #1565c0;
            padding: 2px 8px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 13px;
            font-weight: 500;
        }
        .process-tag-pid {
            display: inline-block;
            background: #f5f5f5;
            color: #757575;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
            font-size: 11px;
        }
        .process-tag-lang {
            display: inline-block;
            background: #fff3e0;
            color: #e65100;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
            font-size: 11px;
            font-weight: 500;
        }

        /* スマホ向けレスポンシブ */
        @media screen and (max-width: 640px) {
            body { margin: 10px; }
            table { font-size: 13px; }
            th, td { padding: 6px 8px; }
            .date-col { width: auto; min-width: 70px; }
            .date-col .date-part { display: block; }
            .source-link { font-size: 11px; }
            .process-tag { display: inline-flex; margin-right: 4px; margin-bottom: 4px; }
            .process-tag-time { font-size: 12px; padding: 2px 6px; }
            .process-tag-pid { font-size: 10px; padding: 1px 4px; }
            .process-tag-lang { font-size: 10px; padding: 1px 4px; }
        }
    </style>
</head>

<body>
<div class="container">
    <div class="back-link">
        <a href="<?php echo url('admin/log') ?>">&larr; <?php echo $isJapanese ? 'ログ一覧に戻る' : 'Back to Log List' ?></a>
    </div>

    <h1><?php echo $isJapanese ? 'サイト更新ログ詳細' : "Cron Log: {$type}" ?></h1>
    <p><?php echo $isJapanese
        ? "ページ {$currentPage} / {$totalPages}（1ページ1000件、新しい順）"
        : "Page {$currentPage} / {$totalPages} (1000 items per page, newest first)"
    ?></p>

    <div class="pagination">
        <?php if ($currentPage <= 1): ?>
            <span class="nav-btn disabled">&larr;</span>
        <?php else: ?>
            <a href="?page=<?php echo $currentPage - 1 ?>" class="nav-btn">&larr;</a>
        <?php endif; ?>

        <form method="get" style="display: flex; align-items: center; gap: 8px; margin: 0;">
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

        <?php if ($currentPage >= $totalPages): ?>
            <span class="nav-btn disabled">&rarr;</span>
        <?php else: ?>
            <a href="?page=<?php echo $currentPage + 1 ?>" class="nav-btn">&rarr;</a>
        <?php endif; ?>
    </div>

    <table>
        <thead>
            <tr>
                <th class="date-col"><?php echo $isJapanese ? '日時' : 'Date' ?></th>
                <th class="message-col"><?php echo $isJapanese ? 'メッセージ' : 'Message' ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)): ?>
                <tr><td colspan="2"><?php echo $isJapanese ? 'ログがありません' : 'No log entries found.' ?></td></tr>
            <?php else: ?>
                <?php foreach ($logs as $log): ?>
                <?php
                    // 日付をY-m-dとH:i:sに分割（スマホ用）
                    $dateParts = explode(' ', $log['date']);
                    $dateYmd = $dateParts[0] ?? '';
                    $dateHis = $dateParts[1] ?? '';
                ?>
                <tr>
                    <td class="date-col">
                        <span class="date-part"><?php echo htmlspecialchars($dateYmd) ?></span>
                        <span class="date-part"><?php echo htmlspecialchars($dateHis) ?></span>
                    </td>
                    <td class="message-col">
                        <?php if (!empty($log['processTag'])): ?>
                            <?php if (!empty($log['processTag']['raw'])): ?>
                                <!-- パースできなかった場合はそのまま表示 -->
                                <span class="process-tag">
                                    <span class="process-tag-time">[<?php echo htmlspecialchars($log['processTag']['raw']) ?>]</span>
                                </span>
                            <?php else: ?>
                                <!-- パース成功：時刻、PID、言語コードの3要素で表示 -->
                                <span class="process-tag">
                                    <?php if (!empty($log['processTag']['time'])): ?>
                                        <span class="process-tag-time"><?php echo htmlspecialchars($log['processTag']['time']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($log['processTag']['pid'])): ?>
                                        <span class="process-tag-pid">PID:<?php echo htmlspecialchars($log['processTag']['pid']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($log['processTag']['lang'])): ?>
                                        <span class="process-tag-lang"><?php echo htmlspecialchars($log['processTag']['lang']) ?></span>
                                    <?php endif; ?>
                                </span>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php echo htmlspecialchars($log['message']) ?>
                        <?php if (!empty($log['githubRef'])): ?>
                            <span class="source-link"><a href="<?php echo htmlspecialchars(buildGitHubUrl($log['githubRef'])) ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($log['githubRef']['label']) ?></a></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="pagination">
        <?php if ($currentPage <= 1): ?>
            <span class="nav-btn disabled">&larr;</span>
        <?php else: ?>
            <a href="?page=<?php echo $currentPage - 1 ?>" class="nav-btn">&larr;</a>
        <?php endif; ?>

        <form method="get" style="display: flex; align-items: center; gap: 8px; margin: 0;">
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

        <?php if ($currentPage >= $totalPages): ?>
            <span class="nav-btn disabled">&rarr;</span>
        <?php else: ?>
            <a href="?page=<?php echo $currentPage + 1 ?>" class="nav-btn">&rarr;</a>
        <?php endif; ?>
    </div>
</div>
</body>

</html>
