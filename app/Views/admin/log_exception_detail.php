<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exception Detail #<?php echo $index ?></title>
    <style>
        body { font-family: sans-serif; margin: 20px; background: #f5f5f5; }
        h1 { color: #333; margin-bottom: 5px; }
        .back-link { margin-bottom: 20px; }
        .back-link a { color: #1a73e8; text-decoration: none; }
        .back-link a:hover { text-decoration: underline; }
        .log-content {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            border-radius: 4px;
            overflow-x: auto;
            font-family: 'Consolas', 'Monaco', monospace;
            font-size: 13px;
            line-height: 1.5;
            white-space: pre-wrap;
            word-break: break-all;
        }
    </style>
</head>

<body>
    <div class="back-link">
        <a href="javascript:history.back()">&larr; Back</a> |
        <a href="<?php echo url('admin/log/exception') ?>">Exception Log List</a>
    </div>

    <h1>Exception Detail #<?php echo $index ?></h1>

    <div class="log-content"><?php echo htmlspecialchars($entry) ?></div>
</body>

</html>
