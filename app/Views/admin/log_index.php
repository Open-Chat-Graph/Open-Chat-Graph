<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Log Viewer</title>
    <style>
        body { font-family: sans-serif; margin: 20px; background: #f5f5f5; }
        h1 { color: #333; }
        .log-list { list-style: none; padding: 0; max-width: 600px; }
        .log-item { background: #fff; margin: 10px 0; padding: 15px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .log-item a { text-decoration: none; color: #1a73e8; font-weight: bold; font-size: 18px; }
        .log-item a:hover { text-decoration: underline; }
        .log-meta { color: #666; font-size: 14px; margin-top: 5px; }
        .log-exists { color: green; }
        .log-missing { color: red; }
    </style>
</head>

<body>
    <h1>Log Viewer</h1>

    <ul class="log-list">
        <?php foreach ($logFiles as $key => $file): ?>
        <li class="log-item">
            <?php if ($file['exists']): ?>
                <?php if ($key === 'exception'): ?>
                    <a href="<?php echo url('admin/log/exception') ?>">exception.log</a>
                <?php else: ?>
                    <a href="<?php echo url('admin/log/' . $key) ?>"><?php echo htmlspecialchars($key) ?></a>
                <?php endif; ?>
                <div class="log-meta">
                    <span class="log-exists">Available</span> | Size: <?php echo htmlspecialchars($file['size']) ?>
                </div>
            <?php else: ?>
                <span style="font-weight: bold; font-size: 18px; color: #999;"><?php echo htmlspecialchars($key) ?></span>
                <div class="log-meta">
                    <span class="log-missing">Not Found</span> | <?php echo htmlspecialchars($file['path']) ?>
                </div>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>
</body>

</html>
