<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($data['name'] ?? 'OpenChat') ?></title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .mdMN01Img img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
        }
        .MdMN04Txt {
            font-size: 24px;
            font-weight: bold;
            margin: 16px 0;
        }
        .MdMN05Txt {
            font-size: 16px;
            color: #666;
            margin: 8px 0;
        }
        .MdMN06Desc {
            font-size: 14px;
            color: #333;
            margin: 16px 0;
            white-space: pre-wrap;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="mdMN01Img">
            <img src="https://obs.line-scdn.net/<?= htmlspecialchars($data['iconImage']['hash'] ?? '') ?>"
                 alt="<?= htmlspecialchars($data['name'] ?? '') ?>">
        </div>
        <div class="MdMN04Txt"><?= htmlspecialchars($data['name'] ?? '') ?></div>
        <div class="MdMN05Txt">メンバー数: <?= number_format($data['memberCount'] ?? 0) ?></div>
        <div class="MdMN06Desc"><?= htmlspecialchars($data['description'] ?? '') ?></div>
    </div>
</body>
</html>
