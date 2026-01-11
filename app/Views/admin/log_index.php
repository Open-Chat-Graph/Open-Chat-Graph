<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php echo $_meta ?>
    <meta name="robots" content="noindex, nofollow">
    <style>
        body { font-family: sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; }
        h1, h2, h3 { color: #333; }
        h2 { margin-top: 40px; border-bottom: 2px solid #ddd; padding-bottom: 10px; }

        .log-list { list-style: none; padding: 0; }
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
<?php
$logDisplayNames = [
    'ja-cron' => 'Japan',
    'th-cron' => 'Thai',
    'tw-cron' => 'Taiwan',
    'exception' => 'エラーログ（管理者専用）',
];
?>
<div class="container">
    <h1>サイト更新ログ</h1>

    <ul class="log-list">
        <?php foreach ($logFiles as $key => $file): ?>
        <li class="log-item">
            <?php if ($file['exists']): ?>
                <?php if ($key === 'exception'): ?>
                    <a href="<?php echo url('admin/log/exception') ?>"><?php echo $logDisplayNames[$key] ?></a>
                <?php else: ?>
                    <a href="<?php echo url('admin/log/' . $key) ?>"><?php echo $logDisplayNames[$key] ?? $key ?></a>
                <?php endif; ?>
                <div class="log-meta">
                    <span class="log-exists">✓ 閲覧可能</span> | 最終更新: <?php echo htmlspecialchars($file['lastModified']) ?>
                </div>
            <?php else: ?>
                <span style="font-weight: bold; font-size: 18px; color: #999;"><?php echo $logDisplayNames[$key] ?? $key ?></span>
                <div class="log-meta">
                    <span class="log-missing">ファイルなし</span>
                </div>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>

    <h2>データ更新処理の流れ</h2>

    <p style="color: #666; margin-bottom: 20px;">
        オープンチャットのランキングデータは、LINE公式サイトから毎時30分に自動取得されます。<br>
        23:30には追加で日次更新処理が実行され、ランキング外のオープンチャットもクローリングします。
    </p>

    <div class="flow-note">
        <strong>同時実行の制御について:</strong>
        日次処理と毎時処理を同時に実行するとデータの不整合が発生する可能性があるため、日次処理の途中で次の毎時処理の時刻（00:30〜）になった場合は、実行中の日次処理を一旦中断し、毎時処理を優先して実行します。その後、日次処理の続きが再開されます。
    </div>

    <div class="flow-note">
        <strong>バックグラウンド処理について:</strong>
        以下の処理は、メイン処理とは別プロセスで並列実行されます：
        <ul style="margin: 8px 0; padding-left: 20px;">
            <li>ランキングDB反映処理（最新24時間のランキング・人数推移履歴をDBに保存）</li>
            <li>おすすめタグ静的データ生成（完了後にCDNキャッシュ削除を実行）</li>
            <li>アーカイブ用DBインポート処理（日本のみ）</li>
        </ul>
        これにより、次の毎時処理が始まるギリギリまで時間を有効活用できます。古いバックグラウンドプロセスが実行中の場合、新しいプロセスが自動的に古いプロセスを終了させます。
    </div>

    <!-- 毎時処理 -->
    <div class="flow-section">
        <h3>毎時処理（毎時30分）</h3>
        <p>LINE公式サイトからランキングデータを取得し、データベースを更新します。</p>

        <ul class="flow-list">
            <li class="highlight">
                <strong>処理開始</strong>
                <span class="log-sample">"【毎時処理】開始"</span>
                <?php echo githubLink('app/Services/Cron/SyncOpenChat.php', 98) ?>
            </li>
            <li>
                ランキングDB反映処理をバックグラウンドで開始
                <?php echo githubLink('app/Services/RankingPosition/Persistence/RankingPositionHourPersistence.php', 75) ?>
            </li>
            <li class="sub">ランキングデータのストレージ保存を待機し、最新24時間のランキング・人数推移履歴をDBに反映（バックグラウンド実行）</li>
            <li>
                LINE公式APIからランキングデータを取得（全24カテゴリ × 急上昇・ランキング）
                <?php echo githubLink('app/Services/OpenChat/OpenChatApiDbMerger.php', 54) ?>
            </li>
            <li class="sub">各オープンチャットの最新情報を取得・DB保存</li>
            <li>
                バックグラウンドDB反映の完了を待機
                <?php echo githubLink('app/Services/RankingPosition/Persistence/RankingPositionHourPersistence.php', 95) ?>
            </li>
            <li>
                各ルームのカバー画像を取得・更新
                <?php echo githubLink('app/Services/Cron/SyncOpenChat.php', 122) ?>
            </li>
            <li>
                メンバー数カラムの更新
                <?php echo githubLink('app/Services/Cron/SyncOpenChat.php', 123) ?>
            </li>
            <li>
                メンバーランキングの更新
                <?php echo githubLink('app/Services/UpdateHourlyMemberRankingService.php', 28) ?>
            </li>
            <li class="sub">おすすめタグ静的データ生成をバックグラウンドで開始（完了後にCDNキャッシュ削除）</li>
            <li>
                参加URLの一括取得
                <?php echo githubLink('app/Services/Cron/SyncOpenChat.php', 126) ?>
            </li>
            <li>
                ランキングBAN情報の更新
                <?php echo githubLink('app/Services/Cron/SyncOpenChat.php', 139) ?>
            </li>
            <li>
                おすすめ情報テーブルの更新（日次処理中はスキップ）
                <?php echo githubLink('app/Services/Cron/SyncOpenChat.php', 140) ?>
            </li>
            <li>
                アーカイブ用DBインポート処理をバックグラウンドで開始（日本のみ）
                <?php echo githubLink('app/Services/Cron/SyncOpenChat.php', 150) ?>
            </li>
            <li class="sub">過去のランキングデータをアーカイブ用DBにインポート（バックグラウンド実行）</li>
            <li class="highlight">
                <strong>処理完了</strong>
                <span class="log-sample">"【毎時処理】完了"</span>
                <?php echo githubLink('app/Services/Cron/SyncOpenChat.php', 115) ?>
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
                <?php echo githubLink('app/Services/Cron/SyncOpenChat.php', 168) ?>
            </li>
            <li>
                まず毎時処理を実行（上記の流れ）
                <?php echo githubLink('app/Services/Cron/SyncOpenChat.php', 173) ?>
            </li>
            <li>
                毎時データを日次データに集約
                <?php echo githubLink('app/Services/RankingPosition/RankingPositionDailyUpdater.php', 28) ?>
            </li>
            <li>
                クローリング対象のオープンチャットを抽出（メンバー数変動あり）
                <?php echo githubLink('app/Services/DailyUpdateCronService.php', 37) ?>
            </li>
            <li>
                ランキング外オープンチャットのクローリング
                <span class="log-sample">"ランキング外オープンチャットのクローリング開始: 残り{件数}件"</span>
                <?php echo githubLink('app/Services/DailyUpdateCronService.php', 77) ?>
            </li>
            <li class="sub">各オープンチャットの最新情報を取得・保存</li>
            <li>
                サブカテゴリ情報の同期
                <?php echo githubLink('app/Services/DailyUpdateCronService.php', 91) ?>
            </li>
            <li>
                日次ランキング更新
                <?php echo githubLink('app/Services/DailyUpdateCronService.php', 95) ?>
            </li>
            <li>
                クローリングで更新された各ルームのカバー画像を取得・更新
                <?php echo githubLink('app/Services/Cron/SyncOpenChat.php', 184) ?>
            </li>
            <li>
                活動のある本日更新対象ルームを抽出するフィルターキャッシュを保存
                <?php echo githubLink('app/Services/UpdateHourlyMemberRankingService.php', 37) ?>
            </li>
            <li>
                CDNキャッシュ削除
                <?php echo githubLink('app/Services/Cron/SyncOpenChat.php', 185) ?>
            </li>
            <li class="highlight">
                <strong>処理完了</strong>
                <span class="log-sample">"【日次処理】完了"</span>
                <?php echo githubLink('app/Services/Cron/SyncOpenChat.php', 188) ?>
            </li>
        </ul>

        <div class="flow-note">
            <strong>処理時間の目安:</strong> 日次処理は通常1〜2時間で完了します。ランキング外オープンチャットの件数により変動します。
        </div>
    </div>

</div>
</body>

</html>
