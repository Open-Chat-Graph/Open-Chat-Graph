<!DOCTYPE html>
<html lang="ja">
<?php viewComponent('policy_head', compact('_css', '_meta')) ?>

<body>
    <div class="body">
        <?php viewComponent('site_header') ?>
        <main style="overflow: hidden;">
            <article class="terms">
                <h1 style="letter-spacing: 0px; line-height: 2;">オープンチャット全体統計</h1>
                <p>オプチャグラフに登録されている全オープンチャットの統計データです。</p>

                <h2>概要</h2>
                <table style="width: 100%; border-collapse: collapse; margin-bottom: 1.5em;">
                    <tbody>
                        <tr>
                            <td style="padding: 8px 12px; border-bottom: 1px solid #e0e0e0; font-weight: bold;">総ルーム数</td>
                            <td style="padding: 8px 12px; border-bottom: 1px solid #e0e0e0; text-align: right;"><?php echo number_format($totalRooms) ?></td>
                        </tr>
                        <tr>
                            <td colspan="2" style="padding: 4px 12px; border-bottom: 1px solid #e0e0e0; color: #666; font-size: 0.85em;">
                                <?php echo $trackingStartDate ? date('Y年n月j日', strtotime($trackingStartDate)) . '以降に' : '' ?>登録されたルームの累計数
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 12px; border-bottom: 1px solid #e0e0e0; font-weight: bold;">総参加者数</td>
                            <td style="padding: 8px 12px; border-bottom: 1px solid #e0e0e0; text-align: right;"><?php echo number_format($totalMembers) ?></td>
                        </tr>
                        <tr>
                            <td colspan="2" style="padding: 4px 12px; border-bottom: 1px solid #e0e0e0; color: #666; font-size: 0.85em;">
                                現在の全ルームのメンバー数合計
                            </td>
                        </tr>
                    </tbody>
                </table>

                <h2>新規参加者数の推移</h2>
                <table style="width: 100%; border-collapse: collapse; margin-bottom: 1.5em;">
                    <tbody>
                        <tr>
                            <td style="padding: 8px 12px; border-bottom: 1px solid #e0e0e0; font-weight: bold;">直近1時間</td>
                            <td style="padding: 8px 12px; border-bottom: 1px solid #e0e0e0; text-align: right; color: #1b813e;">+<?php echo number_format($hourlyIncrease) ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 12px; border-bottom: 1px solid #e0e0e0; font-weight: bold;">直近24時間</td>
                            <td style="padding: 8px 12px; border-bottom: 1px solid #e0e0e0; text-align: right; color: #1b813e;">+<?php echo number_format($dailyIncrease) ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 12px; border-bottom: 1px solid #e0e0e0; font-weight: bold;">直近1週間</td>
                            <td style="padding: 8px 12px; border-bottom: 1px solid #e0e0e0; text-align: right; color: #1b813e;">+<?php echo number_format($weeklyIncrease) ?></td>
                        </tr>
                    </tbody>
                </table>

                <h2>削除されたルーム数</h2>
                <p style="color: #666; font-size: 0.85em; margin-bottom: 0.5em;"><?php echo $trackingStartDate ? date('Y年n月j日', strtotime($trackingStartDate)) . '以降に' : '' ?>オプチャグラフから削除されたルームの数</p>
                <table style="width: 100%; border-collapse: collapse; margin-bottom: 1.5em;">
                    <tbody>
                        <tr>
                            <td style="padding: 8px 12px; border-bottom: 1px solid #e0e0e0; font-weight: bold;">全期間</td>
                            <td style="padding: 8px 12px; border-bottom: 1px solid #e0e0e0; text-align: right;"><?php echo number_format($deletedRoomsTotal) ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 12px; border-bottom: 1px solid #e0e0e0; font-weight: bold;">直近1時間</td>
                            <td style="padding: 8px 12px; border-bottom: 1px solid #e0e0e0; text-align: right;"><?php echo number_format($deletedRoomsHourly) ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 12px; border-bottom: 1px solid #e0e0e0; font-weight: bold;">直近24時間</td>
                            <td style="padding: 8px 12px; border-bottom: 1px solid #e0e0e0; text-align: right;"><?php echo number_format($deletedRoomsDaily) ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 12px; border-bottom: 1px solid #e0e0e0; font-weight: bold;">直近1週間</td>
                            <td style="padding: 8px 12px; border-bottom: 1px solid #e0e0e0; text-align: right;"><?php echo number_format($deletedRoomsWeekly) ?></td>
                        </tr>
                    </tbody>
                </table>

                <h2>カテゴリー別統計</h2>
                <p style="color: #666; font-size: 0.85em; margin-bottom: 0.5em;">現在登録中のルームのカテゴリー別内訳</p>
                <table style="width: 100%; border-collapse: collapse; margin-bottom: 1.5em;">
                    <thead>
                        <tr style="background: #f5f5f5;">
                            <th style="padding: 8px 12px; border-bottom: 2px solid #ccc; text-align: left;">カテゴリー</th>
                            <th style="padding: 8px 12px; border-bottom: 2px solid #ccc; text-align: right;">ルーム数</th>
                            <th style="padding: 8px 12px; border-bottom: 2px solid #ccc; text-align: right;">参加者数</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categoryStats as $cat): ?>
                            <tr>
                                <td style="padding: 8px 12px; border-bottom: 1px solid #e0e0e0;"><?php echo htmlspecialchars(getCategoryName((int)$cat['category']) ?: '未分類') ?></td>
                                <td style="padding: 8px 12px; border-bottom: 1px solid #e0e0e0; text-align: right;"><?php echo number_format((int)$cat['room_count']) ?></td>
                                <td style="padding: 8px 12px; border-bottom: 1px solid #e0e0e0; text-align: right;"><?php echo number_format((int)$cat['total_members']) ?></td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </article>
        </main>

        <?php viewComponent('footer_inner') ?>
    </div>

    <?php \App\Views\Ads\GoogleAdsense::loadAdsTag() ?>
    <?php echo $_breadcrumbsSchema ?>
</body>

</html>
