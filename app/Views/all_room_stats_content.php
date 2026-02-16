<!DOCTYPE html>
<html lang="ja">
<?php viewComponent('policy_head', compact('_css', '_meta')) ?>
<script src="https://cdn.tailwindcss.com/3.4.17"></script>
<script>
    tailwind.config = { corePlugins: { preflight: false } }
</script>
<style>
    .terms *, .terms { box-sizing: border-box; }
</style>

<body>
    <div class="body">
        <?php viewComponent('site_header') ?>
        <main style="overflow: hidden;">
            <article class="terms">
                <h1 style="letter-spacing: 0px; line-height: 2;">オープンチャット全体統計</h1>
                <p>オプチャグラフに登録されている全オープンチャットの統計データです。</p>
                <p class="text-gray-500 text-sm">※ オプチャグラフには、LINE公式サイトのランキングに掲載されたことのあるルームのみが登録されています。すべてのオープンチャットが対象ではありません。</p>
                <p class="text-gray-400 text-xs"><?php echo date('Y年n月j日 G:i', strtotime($updatedAt)) ?> 時点</p>

                <!-- 概要カード -->
                <div class="grid grid-cols-3 gap-2 sm:gap-3 mt-6 mb-10">
                    <div class="rounded-xl bg-blue-50 border border-blue-100 p-3 sm:p-5">
                        <div class="text-xs sm:text-sm font-semibold text-blue-400 mb-1">総ルーム数</div>
                        <div class="text-lg sm:text-2xl font-bold text-gray-800"><?php echo number_format($totalRooms) ?></div>
                        <div class="text-[10px] sm:text-xs text-gray-400 mt-1"><?php echo $trackingStartDate ? date('Y/n/j', strtotime($trackingStartDate)) . '〜' : '' ?>累計</div>
                    </div>
                    <div class="rounded-xl bg-violet-50 border border-violet-100 p-3 sm:p-5">
                        <div class="text-xs sm:text-sm font-semibold text-violet-400 mb-1">総参加者数</div>
                        <div class="text-lg sm:text-2xl font-bold text-gray-800"><?php echo number_format($totalMembers) ?></div>
                        <div class="text-[10px] sm:text-xs text-gray-400 mt-1">全ルーム合計</div>
                    </div>
                    <div class="rounded-xl bg-teal-50 border border-teal-100 p-3 sm:p-5">
                        <div class="text-xs sm:text-sm font-semibold text-teal-400 mb-1">中央値</div>
                        <div class="text-lg sm:text-2xl font-bold text-gray-800"><?php echo number_format($overallMedian) ?></div>
                        <div class="text-[10px] sm:text-xs text-gray-400 mt-1">1部屋あたり参加者数</div>
                    </div>
                </div>

                <!-- 新規参加者数（1ヶ月のみ） -->
                <h2>新規参加者数の推移</h2>
                <p class="text-gray-500 text-xs mb-3">日次更新。直近1ヶ月間の全ルーム合計メンバー数の増減数</p>
                <div class="mb-10">
                    <div class="rounded-xl <?php echo $monthlyTrend >= 0 ? 'bg-emerald-50 border-emerald-200' : 'bg-rose-50 border-rose-200' ?> border p-3 sm:p-4 text-center">
                        <div class="text-xs sm:text-sm font-semibold <?php echo $monthlyTrend >= 0 ? 'text-emerald-500' : 'text-rose-400' ?> mb-1">1ヶ月</div>
                        <div class="text-base sm:text-xl font-bold <?php echo $monthlyTrend >= 0 ? 'text-emerald-600' : 'text-rose-600' ?>"><?php echo ($monthlyTrend >= 0 ? '+' : '') . number_format($monthlyTrend) ?></div>
                    </div>
                </div>

                <!-- 新規登録ルーム数（1ヶ月のみ） -->
                <h2>新規登録ルーム数</h2>
                <p class="text-gray-500 text-xs mb-3">直近1ヶ月間に新しく登録されたルームの数</p>
                <div class="mb-10">
                    <div class="rounded-xl bg-amber-50 border border-amber-200 p-3 sm:p-4 text-center">
                        <div class="text-xs font-semibold text-amber-500 mb-1">1ヶ月</div>
                        <div class="text-base sm:text-lg font-bold text-amber-600"><?php echo number_format($newRoomsMonthly) ?></div>
                    </div>
                </div>

                <!-- 閉鎖されたルーム数（1ヶ月のみ） -->
                <h2>閉鎖されたルーム数</h2>
                <p class="text-gray-500 text-xs mb-3">直近1ヶ月間にオプチャグラフに登録後、オープンチャット上で利用できなくなったルームの数</p>
                <div class="mb-10">
                    <div class="rounded-xl bg-rose-50 border border-rose-200 p-3 sm:p-4 text-center">
                        <div class="text-xs font-semibold text-rose-400 mb-1">1ヶ月</div>
                        <div class="text-base sm:text-xl font-bold text-rose-600"><?php echo number_format($deletedRoomsMonthly) ?></div>
                        <?php if ($deletedMembersMonthly > 0): ?>
                        <div class="text-[10px] sm:text-xs text-rose-400 mt-1">-<?php echo number_format($deletedMembersMonthly) ?>人</div>
                        <?php endif ?>
                        <?php if ($delistedMonthly['rooms'] > 0): ?>
                        <div class="border-t border-rose-200 mt-2 pt-2">
                            <div class="text-[10px] sm:text-xs text-gray-400">掲載終了: <?php echo number_format($delistedMonthly['rooms']) ?>ルーム</div>
                            <?php if ($delistedMonthly['members'] > 0): ?>
                            <div class="text-[10px] sm:text-xs text-gray-400">-<?php echo number_format($delistedMonthly['members']) ?>人</div>
                            <?php endif ?>
                        </div>
                        <?php endif ?>
                    </div>
                </div>

                <!-- 参加者分布グラフ -->
                <h2>参加者数の分布</h2>
                <p class="text-gray-500 text-xs mb-3">人数帯別のルーム数と合計参加者数</p>
                <div class="rounded-xl bg-white shadow-sm border border-gray-200 p-3 sm:p-5 mb-10">
                    <canvas id="distribution-chart" height="300"></canvas>
                </div>
                <script type="application/json" id="distribution-data"><?php echo json_encode($memberDistribution, JSON_HEX_TAG) ?></script>

                <!-- カテゴリー別統計 -->
                <h2>カテゴリー別統計</h2>
                <p class="text-gray-500 text-xs mb-3">現時点で登録中のルームのカテゴリー別内訳（毎時更新）</p>
                <div class="rounded-xl bg-white shadow-sm border border-gray-200 overflow-hidden mb-8">
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse">
                            <thead>
                                <tr class="bg-slate-600">
                                    <th class="py-2.5 sm:py-3 px-3 sm:px-4 text-left text-xs font-bold text-white tracking-wider">カテゴリー</th>
                                    <th class="py-2.5 sm:py-3 px-2 sm:px-4 text-right text-xs font-bold text-white tracking-wider">ルーム数</th>
                                    <th class="py-2.5 sm:py-3 px-2 sm:px-4 text-right text-xs font-bold text-white tracking-wider">参加者数</th>
                                    <th class="py-2.5 sm:py-3 px-2 sm:px-4 text-right text-xs font-bold text-white tracking-wider">中央値</th>
                                    <th class="py-2.5 sm:py-3 px-2 sm:px-4 text-right text-xs font-bold text-white tracking-wider whitespace-nowrap">1ヶ月増減</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categoryStats as $i => $cat): ?>
                                    <tr class="<?php echo $i % 2 === 0 ? 'bg-white' : 'bg-slate-50' ?> hover:bg-blue-50 transition-colors">
                                        <td class="py-2.5 sm:py-3 px-3 sm:px-4 text-xs sm:text-sm font-medium text-gray-800 border-b border-gray-100"><?php echo htmlspecialchars(getCategoryName((int)$cat['category']) ?: '未分類') ?></td>
                                        <td class="py-2.5 sm:py-3 px-2 sm:px-4 text-xs sm:text-sm font-semibold text-gray-700 text-right tabular-nums border-b border-gray-100"><?php echo number_format($cat['room_count']) ?></td>
                                        <td class="py-2.5 sm:py-3 px-2 sm:px-4 text-xs sm:text-sm font-semibold text-gray-700 text-right tabular-nums border-b border-gray-100"><?php echo number_format($cat['total_members']) ?></td>
                                        <td class="py-2.5 sm:py-3 px-2 sm:px-4 text-xs sm:text-sm font-semibold text-gray-700 text-right tabular-nums border-b border-gray-100"><?php echo number_format($cat['median']) ?></td>
                                        <?php
                                            $trend = $cat['monthly_trend'];
                                            $trendColor = $trend >= 0 ? 'text-emerald-600' : 'text-rose-600';
                                            $trendSign = $trend >= 0 ? '+' : '';
                                        ?>
                                        <td class="py-2.5 sm:py-3 px-2 sm:px-4 text-xs sm:text-sm font-semibold <?php echo $trendColor ?> text-right tabular-nums border-b border-gray-100"><?php echo $trendSign . number_format($trend) ?></td>
                                    </tr>
                                <?php endforeach ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </article>
        </main>

        <?php viewComponent('footer_inner') ?>
    </div>

    <?php \App\Views\Ads\GoogleAdsense::loadAdsTag() ?>
    <?php echo $_breadcrumbsSchema ?>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>
    <script>
    (function() {
        var data = JSON.parse(document.getElementById('distribution-data').textContent);
        var labels = data.map(function(d) { return d.band_label; });
        var totalMembers = data.map(function(d) { return d.total_members; });
        var roomCounts = data.map(function(d) { return d.room_count; });
        var colors = [
            'rgba(59, 130, 246, 0.7)',
            'rgba(99, 102, 241, 0.7)',
            'rgba(139, 92, 246, 0.7)',
            'rgba(236, 72, 153, 0.7)',
            'rgba(245, 158, 11, 0.7)',
            'rgba(16, 185, 129, 0.7)',
            'rgba(239, 68, 68, 0.7)'
        ];
        var borderColors = [
            'rgba(59, 130, 246, 1)',
            'rgba(99, 102, 241, 1)',
            'rgba(139, 92, 246, 1)',
            'rgba(236, 72, 153, 1)',
            'rgba(245, 158, 11, 1)',
            'rgba(16, 185, 129, 1)',
            'rgba(239, 68, 68, 1)'
        ];

        new Chart(document.getElementById('distribution-chart'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: '合計参加者数',
                    data: totalMembers,
                    backgroundColor: colors,
                    borderColor: borderColors,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            title: function(items) { return items[0].label; },
                            label: function(item) {
                                var idx = item.dataIndex;
                                return [
                                    'ルーム数: ' + roomCounts[idx].toLocaleString(),
                                    '合計参加者数: ' + item.raw.toLocaleString()
                                ];
                            }
                        }
                    },
                    datalabels: {
                        anchor: 'end',
                        align: 'top',
                        formatter: function(value, ctx) {
                            return roomCounts[ctx.dataIndex].toLocaleString() + '室';
                        },
                        font: { size: 11, weight: 'bold' },
                        color: '#374151'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(v) { return v.toLocaleString(); }
                        },
                        title: { display: true, text: '合計参加者数' }
                    },
                    x: {
                        ticks: { font: { size: 11 } }
                    }
                }
            },
            plugins: [ChartDataLabels]
        });
    })();
    </script>
</body>

</html>
