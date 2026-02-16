<!DOCTYPE html>
<html lang="ja">
<?php viewComponent('policy_head', compact('_css', '_meta')) ?>
<script src="https://cdn.tailwindcss.com/3.4.17"></script>
<script>
    tailwind.config = {
        corePlugins: {
            preflight: false
        }
    }
</script>
<style>
    .terms *,
    .terms {
        box-sizing: border-box;
    }
</style>

<?php
/** トレンド値に応じた色クラスを返す */
function trendColorClass(int $value, string $positiveClass = 'text-emerald-600', string $negativeClass = 'text-rose-600'): string
{
    return $value >= 0 ? $positiveClass : $negativeClass;
}
?>

<body>
    <div class="body">
        <?php viewComponent('site_header') ?>
        <main style="overflow: hidden;">
            <article class="terms">
                <h1 style="letter-spacing: 0px; line-height: 2;">オープンチャット全体統計</h1>
                <p>オプチャグラフに登録されている全オープンチャットの統計データです。</p>
                <p class="text-gray-500 text-sm">※
                    オプチャグラフには、LINE公式サイトのランキングに掲載されたことのあるルームのみが登録されています。すべてのオープンチャットが対象ではありません。</p>
                <p class="text-gray-400 text-xs"><?php echo date('Y年n月j日 G:i', strtotime($updatedAt)) ?> 時点</p>

                <!-- 概要カード -->
                <div class="grid grid-cols-3 gap-2 sm:gap-3 mt-6 mb-10">
                    <div class="rounded-xl bg-blue-50 border border-blue-100 p-3 sm:p-5">
                        <div class="text-xs sm:text-sm font-semibold text-blue-400 mb-1">総ルーム数</div>
                        <div class="text-lg sm:text-2xl font-bold text-gray-800">
                            <?php echo number_format($totalRooms) ?></div>
                        <div class="text-[10px] sm:text-xs text-gray-400 mt-1">
                            <?php echo $trackingStartDate ? date('Y/n/j', strtotime($trackingStartDate)) . '〜' : '' ?>累計
                        </div>
                    </div>
                    <div class="rounded-xl bg-violet-50 border border-violet-100 p-3 sm:p-5">
                        <div class="text-xs sm:text-sm font-semibold text-violet-400 mb-1">総参加者数</div>
                        <div class="text-lg sm:text-2xl font-bold text-gray-800">
                            <?php echo number_format($totalMembers) ?></div>
                        <div class="text-[10px] sm:text-xs text-gray-400 mt-1">全ルーム合計</div>
                    </div>
                    <div class="rounded-xl bg-teal-50 border border-teal-100 p-3 sm:p-5">
                        <div class="text-xs sm:text-sm font-semibold text-teal-400 mb-1">中央値</div>
                        <div class="text-lg sm:text-2xl font-bold text-gray-800">
                            <?php echo number_format($overallMedian) ?></div>
                        <div class="text-[10px] sm:text-xs text-gray-400 mt-1">1部屋あたり参加者数</div>
                    </div>
                </div>

                <!-- 直近1ヶ月の変動 -->
                <h2>直近1ヶ月の変動</h2>
                <p class="text-gray-500 text-xs mb-3">直近1ヶ月間のルーム・参加者数の増減</p>
                <?php
                $b = $memberTrendBreakdown;
                $d = $disappearedBreakdown;
                $netMemberChange = $b['increased'] + $b['decreased'] + $b['lost'] + $b['gained'];
                $deletedTotal = $d['closed_rooms'] + $d['delisted_rooms'];
                ?>
                <div class="rounded-xl bg-white shadow-sm border border-gray-200 p-4 sm:p-5 mb-10">
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs sm:text-sm text-gray-600">新規登録ルーム数</span>
                            <span
                                class="text-sm sm:text-base font-bold text-gray-800"><?php echo number_format($newRoomsMonthly) ?>部屋</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs sm:text-sm text-gray-600">参加者数の純増数</span>
                            <span
                                class="text-sm sm:text-base font-bold <?php echo trendColorClass($netMemberChange) ?>"><?php echo ($netMemberChange >= 0 ? '+' : '') . number_format($netMemberChange) ?></span>
                        </div>
                        <div class="pl-4 space-y-1">
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-gray-400">既存ルームの増加</span>
                                <span
                                    class="text-xs font-semibold text-emerald-600">+<?php echo number_format($b['increased']) ?></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-gray-400">新規ルーム分</span>
                                <span
                                    class="text-xs font-semibold text-emerald-600">+<?php echo number_format($b['gained']) ?></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-gray-400">既存ルームの減少</span>
                                <span
                                    class="text-xs font-semibold text-rose-600"><?php echo number_format($b['decreased']) ?></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-gray-400">消滅ルーム分</span>
                                <span
                                    class="text-xs font-semibold text-rose-600"><?php echo number_format($b['lost']) ?></span>
                            </div>
                        </div>
                        <div class="flex items-center justify-between border-t border-gray-100 pt-3">
                            <span class="text-xs sm:text-sm text-gray-600">削除されたルーム数</span>
                            <span
                                class="text-sm sm:text-base font-bold text-rose-600"><?php echo number_format($deletedTotal) ?>部屋
                                <span
                                    class="text-xs font-normal text-rose-400">(-<?php echo number_format($d['closed_members'] + $d['delisted_members']) ?>人)</span></span>
                        </div>
                        <div class="pl-4 space-y-1">
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-gray-400">閉鎖ルーム数</span>
                                <span
                                    class="text-xs font-semibold text-gray-500"><?php echo number_format($d['closed_rooms']) ?>部屋
                                    <span
                                        class="font-normal text-gray-400">(-<?php echo number_format($d['closed_members']) ?>人)</span></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span
                                    class="text-xs font-semibold text-gray-500"><?php echo number_format($d['delisted_rooms']) ?>部屋
                                    <span
                                        class="font-normal text-gray-400">(-<?php echo number_format($d['delisted_members']) ?>人)</span></span>
                                <span class="text-xs text-gray-400">LINE公式サイト掲載終了</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 参加者分布グラフ -->
                <h2>参加者数の分布</h2>
                <p class="text-gray-500 text-xs mb-3">人数帯別のルーム数と合計参加者数</p>
                <div class="rounded-xl bg-white shadow-sm border border-gray-200 p-3 sm:p-5 mb-4">
                    <canvas id="distribution-room-chart" height="300"></canvas>
                </div>
                <div class="rounded-xl bg-white shadow-sm border border-gray-200 p-3 sm:p-5 mb-10">
                    <canvas id="distribution-member-chart" height="300"></canvas>
                </div>
                <script type="application/json" id="distribution-data">
                    <?php echo json_encode($memberDistribution, JSON_HEX_TAG) ?>
                </script>

                <!-- カテゴリー別統計 -->
                <h2>カテゴリー別統計</h2>
                <p class="text-gray-500 text-xs mb-3">現時点で登録中のルームのカテゴリー別内訳（毎時更新）</p>
                <div class="rounded-xl bg-white shadow-sm border border-gray-200 overflow-hidden mb-8">
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse">
                            <thead>
                                <tr class="bg-slate-600">
                                    <th
                                        class="py-2.5 sm:py-3 px-3 sm:px-4 text-left text-xs font-bold text-white tracking-wider">
                                        カテゴリー</th>
                                    <th
                                        class="py-2.5 sm:py-3 px-2 sm:px-4 text-right text-xs font-bold text-white tracking-wider">
                                        ルーム数</th>
                                    <th
                                        class="py-2.5 sm:py-3 px-2 sm:px-4 text-right text-xs font-bold text-white tracking-wider">
                                        参加者数</th>
                                    <th
                                        class="py-2.5 sm:py-3 px-2 sm:px-4 text-right text-xs font-bold text-white tracking-wider whitespace-nowrap">
                                        1ヶ月増減</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categoryStats as $i => $cat): ?>
                                    <tr
                                        class="<?php echo $i % 2 === 0 ? 'bg-white' : 'bg-slate-50' ?> hover:bg-blue-50 transition-colors">
                                        <td
                                            class="py-2.5 sm:py-3 px-3 sm:px-4 text-xs sm:text-sm font-medium text-gray-800 border-b border-gray-100">
                                            <?php echo htmlspecialchars(getCategoryName((int)$cat['category']) ?: '未分類') ?>
                                        </td>
                                        <td
                                            class="py-2.5 sm:py-3 px-2 sm:px-4 text-xs sm:text-sm font-semibold text-gray-700 text-right tabular-nums border-b border-gray-100">
                                            <?php echo number_format($cat['room_count']) ?></td>
                                        <td
                                            class="py-2.5 sm:py-3 px-2 sm:px-4 text-xs sm:text-sm font-semibold text-gray-700 text-right tabular-nums border-b border-gray-100">
                                            <?php echo number_format($cat['total_members']) ?></td>
                                        <td
                                            class="py-2.5 sm:py-3 px-2 sm:px-4 text-xs sm:text-sm font-semibold <?php echo trendColorClass($cat['monthly_trend']) ?> text-right tabular-nums border-b border-gray-100">
                                            <?php echo ($cat['monthly_trend'] >= 0 ? '+' : '') . number_format($cat['monthly_trend']) ?>
                                        </td>
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

    <script type="module" crossorigin src="/<?php echo getFilePath('js/all-room-stats', 'index-*.js') ?>"></script>
</body>

</html>