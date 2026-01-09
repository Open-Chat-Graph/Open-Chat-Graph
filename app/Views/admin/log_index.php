<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>データ更新ログ | オプチャグラフ</title>
    <meta name="description" content="オプチャグラフのデータ更新処理（LINE公式サイトからのランキングデータ取得）の実行状況をリアルタイムで確認できます。">
    <meta name="robots" content="noindex, nofollow">

    <!-- OGP -->
    <meta property="og:title" content="データ更新ログ | オプチャグラフ">
    <meta property="og:description" content="オプチャグラフのデータ更新処理の実行状況をリアルタイムで確認できます。">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://openchat-review.me/admin/log">
    <meta property="og:image" content="https://openchat-review.me/assets/ogp-log.png">
    <meta property="og:site_name" content="オプチャグラフ">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="データ更新ログ | オプチャグラフ">
    <meta name="twitter:description" content="オプチャグラフのデータ更新処理の実行状況をリアルタイムで確認できます。">
    <style>
        body { font-family: sans-serif; margin: 20px; background: #f5f5f5; }
        h1, h2, h3 { color: #333; }
        h2 { margin-top: 40px; border-bottom: 2px solid #ddd; padding-bottom: 10px; }

        .log-list { list-style: none; padding: 0; max-width: 600px; }
        .log-item { background: #fff; margin: 10px 0; padding: 15px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .log-item a { text-decoration: none; color: #1a73e8; font-weight: bold; font-size: 18px; }
        .log-item a:hover { text-decoration: underline; }
        .log-meta { color: #666; font-size: 14px; margin-top: 5px; }
        .log-exists { color: green; }
        .log-missing { color: red; }

        /* フロー説明用スタイル */
        .flow-section { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .flow-section h3 { margin: 0 0 15px 0; color: #1a73e8; font-size: 18px; border-bottom: 2px solid #1a73e8; padding-bottom: 8px; }
        .flow-section p { margin: 10px 0; color: #555; font-size: 14px; line-height: 1.6; }

        .flow-list { list-style: none; padding: 0; margin: 15px 0; }
        .flow-list li { position: relative; padding: 12px 15px 12px 30px; margin: 8px 0; background: #f8f9fa; border-left: 4px solid #1a73e8; border-radius: 0 4px 4px 0; font-size: 14px; line-height: 1.5; }
        .flow-list li::before { content: '→'; position: absolute; left: 10px; color: #1a73e8; font-weight: bold; }
        .flow-list li a { color: #1565c0; font-size: 12px; text-decoration: none; margin-left: 8px; }
        .flow-list li a:hover { text-decoration: underline; }

        .flow-list li.highlight { background: #e3f2fd; border-left-color: #1976d2; }
        .flow-list li.sub { margin-left: 20px; background: #fff; border-left-color: #90caf9; font-size: 13px; }
        .flow-list li.sub::before { content: '•'; color: #90caf9; }

        .flow-note { background: #fff3e0; border: 1px solid #ffb74d; padding: 12px 15px; border-radius: 4px; margin: 15px 0; font-size: 13px; color: #e65100; }
        .flow-note strong { color: #bf360c; }

        .log-sample { font-family: monospace; background: #e8f5e9; padding: 2px 6px; border-radius: 3px; font-size: 12px; color: #2e7d32; }

        @media screen and (max-width: 640px) {
            body { margin: 10px; }
            .flow-section { padding: 15px; }
            .flow-section h3 { font-size: 16px; }
            .flow-list li { padding: 10px 12px 10px 25px; font-size: 13px; }
            .flow-list li::before { left: 8px; }
            .flow-list li a { display: block; margin: 5px 0 0 0; }
            .flow-list li.sub { margin-left: 10px; }
        }
    </style>
</head>

<body>
    <h1>データ更新ログ</h1>

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

    <h2>Cron処理の流れ</h2>

    <p style="color: #666; margin-bottom: 20px;">
        オープンチャットのランキングデータは、LINE公式サイトから毎時30分に自動取得されます。<br>
        23:30には追加で日次更新処理が実行され、ランキング外のオープンチャットもクローリングします。
    </p>

    <div class="flow-note">
        <strong>同時実行の制御について:</strong>
        日次処理と毎時処理を同時に実行するとデータの不整合が発生する可能性があるため、日次処理の途中で次の毎時処理の時刻（00:30〜）になった場合は、実行中の日次処理を一旦中断し、毎時処理を優先して実行します。その後、日次処理の続きが再開されます。
    </div>

    <!-- 毎時処理 -->
    <div class="flow-section">
        <h3>毎時処理（毎時30分）</h3>
        <p>LINE公式サイトからランキングデータを取得し、データベースを更新します。</p>

        <ul class="flow-list">
            <li class="highlight">
                <strong>処理開始</strong>
                <span class="log-sample">"【毎時処理】開始"</span>
                <?php echo githubLink('app/Services/Cron/SyncOpenChat.php', 115) ?>
            </li>
            <li>
                LINE公式APIからランキングデータを取得（全24カテゴリ × 急上昇・メンバー数）
                <?php echo githubLink('app/Services/OpenChat/OpenChatApiDbMerger.php', 54) ?>
            </li>
            <li class="sub">各カテゴリのデータをダウンロード・保存</li>
            <li>
                取得したランキングデータをDBに保存
                <?php echo githubLink('app/Services/RankingPosition/Persistence/RankingPositionHourPersistence.php', 26) ?>
            </li>
            <li>
                画像の更新（サムネイル取得）
                <?php echo githubLink('app/Services/Cron/SyncOpenChat.php', 119) ?>
            </li>
            <li>
                メンバー数カラムの更新
                <?php echo githubLink('app/Services/Cron/SyncOpenChat.php', 120) ?>
            </li>
            <li>
                メンバーランキングの更新
                <?php echo githubLink('app/Services/Cron/SyncOpenChat.php', 128) ?>
            </li>
            <li>
                CDNキャッシュの削除
                <?php echo githubLink('app/Services/Cron/SyncOpenChat.php', 129) ?>
            </li>
            <li>
                参加URLの一括取得
                <?php echo githubLink('app/Services/Cron/SyncOpenChat.php', 142) ?>
            </li>
            <li>
                ランキングBAN情報の更新
                <?php echo githubLink('app/Services/Cron/SyncOpenChat.php', 143) ?>
            </li>
            <li>
                おすすめ情報の更新
                <?php echo githubLink('app/Services/Cron/SyncOpenChat.php', 151) ?>
            </li>
            <li class="highlight">
                <strong>処理完了</strong>
                <span class="log-sample">"【毎時処理】完了"</span>
                <?php echo githubLink('app/Services/Cron/SyncOpenChat.php', 127) ?>
            </li>
        </ul>
    </div>

    <!-- 日次処理 -->
    <div class="flow-section">
        <h3>日次処理（毎日23:30）</h3>
        <p>毎時処理に加えて、ランキング外のオープンチャットもクローリングし、全データを更新します。</p>

        <ul class="flow-list">
            <li class="highlight">
                <strong>処理開始</strong>
                <span class="log-sample">"【日次処理】開始"</span>
                <?php echo githubLink('app/Services/Cron/SyncOpenChat.php', 185) ?>
            </li>
            <li>
                まず毎時処理を実行（上記の流れ）
            </li>
            <li>
                毎時データを日次データに集約
                <?php echo githubLink('app/Services/RankingPosition/RankingPositionDailyUpdater.php', 28) ?>
            </li>
            <li>
                クローリング対象のオープンチャットを抽出（メンバー数変動あり）
                <?php echo githubLink('app/Services/DailyUpdateCronService.php', 42) ?>
            </li>
            <li>
                ランキング外オープンチャットのクローリング
                <span class="log-sample">"ランキング外オープンチャットのクローリング開始: {件数}件"</span>
                <?php echo githubLink('app/Services/DailyUpdateCronService.php', 81) ?>
            </li>
            <li class="sub">各オープンチャットの最新情報を取得・保存</li>
            <li>
                サブカテゴリ情報の同期
                <?php echo githubLink('app/Services/DailyUpdateCronService.php', 101) ?>
            </li>
            <li>
                全画像の更新
                <?php echo githubLink('app/Services/Cron/SyncOpenChat.php', 185) ?>
            </li>
            <li>
                フィルターキャッシュの保存
                <?php echo githubLink('app/Services/Cron/SyncOpenChat.php', 189) ?>
            </li>
            <li class="highlight">
                <strong>処理完了</strong>
                <span class="log-sample">"【日次処理】完了"</span>
                <?php echo githubLink('app/Services/Cron/SyncOpenChat.php', 210) ?>
            </li>
        </ul>

        <div class="flow-note">
            <strong>処理時間の目安:</strong> 日次処理は通常1〜2時間で完了します。ランキング外オープンチャットの件数により変動します。
        </div>
    </div>

</body>

</html>
