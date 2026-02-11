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
                <div class="grid grid-cols-2 gap-2 sm:gap-3 mt-6 mb-10">
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
                </div>

                <!-- 新規参加者数 -->
                <h2>新規参加者数の推移</h2>
                <p class="text-gray-500 text-xs mb-3">毎時更新。各期間内にメンバー数が増加したルームの増加数合計</p>
                <div class="grid grid-cols-3 gap-2 sm:gap-3 mb-10">
                    <div class="rounded-xl bg-emerald-50 border border-emerald-200 p-3 sm:p-4 text-center">
                        <div class="text-xs sm:text-sm font-semibold text-emerald-500 mb-1">1時間</div>
                        <div class="text-base sm:text-xl font-bold text-emerald-600">+<?php echo number_format($hourlyIncrease) ?></div>
                    </div>
                    <div class="rounded-xl bg-emerald-50 border border-emerald-200 p-3 sm:p-4 text-center">
                        <div class="text-xs sm:text-sm font-semibold text-emerald-500 mb-1">24時間</div>
                        <div class="text-base sm:text-xl font-bold text-emerald-600">+<?php echo number_format($dailyIncrease) ?></div>
                    </div>
                    <div class="rounded-xl bg-emerald-50 border border-emerald-200 p-3 sm:p-4 text-center">
                        <div class="text-xs sm:text-sm font-semibold text-emerald-500 mb-1">1週間</div>
                        <div class="text-base sm:text-xl font-bold text-emerald-600">+<?php echo number_format($weeklyIncrease) ?></div>
                    </div>
                </div>

                <!-- 新規登録ルーム数 -->
                <h2>新規登録ルーム数</h2>
                <p class="text-gray-500 text-xs mb-3"><?php echo $trackingStartDate ? date('Y年n月j日', strtotime($trackingStartDate)) . '以降のデータに基づく、' : '' ?>各期間内に新しく登録されたルームの数</p>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 sm:gap-3 mb-10">
                    <div class="rounded-xl bg-amber-50 border border-amber-200 p-3 sm:p-4 text-center">
                        <div class="text-xs font-semibold text-amber-500 mb-1">1ヶ月</div>
                        <div class="text-base sm:text-lg font-bold text-amber-600"><?php echo number_format($newRoomsMonthly) ?></div>
                    </div>
                    <div class="rounded-xl bg-amber-50 border border-amber-200 p-3 sm:p-4 text-center">
                        <div class="text-xs font-semibold text-amber-500 mb-1">1週間</div>
                        <div class="text-base sm:text-lg font-bold text-amber-600"><?php echo number_format($newRoomsWeekly) ?></div>
                    </div>
                    <div class="rounded-xl bg-amber-50 border border-amber-200 p-3 sm:p-4 text-center">
                        <div class="text-xs font-semibold text-amber-500 mb-1">24時間</div>
                        <div class="text-base sm:text-lg font-bold text-amber-600"><?php echo number_format($newRoomsDaily) ?></div>
                    </div>
                    <div class="rounded-xl bg-amber-50 border border-amber-200 p-3 sm:p-4 text-center">
                        <div class="text-xs font-semibold text-amber-500 mb-1">1時間</div>
                        <div class="text-base sm:text-lg font-bold text-amber-600"><?php echo number_format($newRoomsHourly) ?></div>
                    </div>
                </div>

                <!-- 削除されたルーム数 -->
                <h2>削除されたルーム数</h2>
                <p class="text-gray-500 text-xs mb-3"><?php echo $earliestDeletedDate ? date('Y年n月j日', strtotime($earliestDeletedDate)) . '以降に' : '' ?>オプチャグラフから削除されたルームの数</p>
                <div class="grid grid-cols-2 gap-2 sm:gap-3 mb-2">
                    <div class="rounded-xl bg-rose-50 border border-rose-200 p-3 sm:p-4 text-center">
                        <div class="text-xs font-semibold text-rose-400 mb-1">全期間</div>
                        <div class="text-base sm:text-xl font-bold text-rose-600"><?php echo number_format($deletedRoomsTotal) ?></div>
                    </div>
                    <div class="rounded-xl bg-rose-50 border border-rose-200 p-3 sm:p-4 text-center">
                        <div class="text-xs font-semibold text-rose-400 mb-1">1ヶ月</div>
                        <div class="text-base sm:text-xl font-bold text-rose-600"><?php echo number_format($deletedRoomsMonthly) ?></div>
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-2 sm:gap-3 mb-10">
                    <div class="rounded-xl bg-rose-50 border border-rose-200 p-3 sm:p-4 text-center">
                        <div class="text-xs font-semibold text-rose-400 mb-1">1週間</div>
                        <div class="text-sm sm:text-lg font-bold text-rose-600"><?php echo number_format($deletedRoomsWeekly) ?></div>
                    </div>
                    <div class="rounded-xl bg-rose-50 border border-rose-200 p-3 sm:p-4 text-center">
                        <div class="text-xs font-semibold text-rose-400 mb-1">24時間</div>
                        <div class="text-sm sm:text-lg font-bold text-rose-600"><?php echo number_format($deletedRoomsDaily) ?></div>
                    </div>
                    <div class="rounded-xl bg-rose-50 border border-rose-200 p-3 sm:p-4 text-center">
                        <div class="text-xs font-semibold text-rose-400 mb-1">1時間</div>
                        <div class="text-sm sm:text-lg font-bold text-rose-600"><?php echo number_format($deletedRoomsHourly) ?></div>
                    </div>
                </div>

                <!-- カテゴリー別統計 -->
                <h2>カテゴリー別統計</h2>
                <p class="text-gray-500 text-xs mb-3">現時点で登録中のルームのカテゴリー別内訳（毎時更新）</p>
                <div class="rounded-xl bg-white shadow-sm border border-gray-200 overflow-hidden mb-8">
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="bg-slate-600">
                                <th class="py-2.5 sm:py-3 px-3 sm:px-4 text-left text-xs font-bold text-white tracking-wider">カテゴリー</th>
                                <th class="py-2.5 sm:py-3 px-3 sm:px-4 text-right text-xs font-bold text-white tracking-wider">ルーム数</th>
                                <th class="py-2.5 sm:py-3 px-3 sm:px-4 text-right text-xs font-bold text-white tracking-wider">参加者数</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categoryStats as $i => $cat): ?>
                                <tr class="<?php echo $i % 2 === 0 ? 'bg-white' : 'bg-slate-50' ?> hover:bg-blue-50 transition-colors">
                                    <td class="py-2.5 sm:py-3 px-3 sm:px-4 text-xs sm:text-sm font-medium text-gray-800 border-b border-gray-100"><?php echo htmlspecialchars(getCategoryName((int)$cat['category']) ?: '未分類') ?></td>
                                    <td class="py-2.5 sm:py-3 px-3 sm:px-4 text-xs sm:text-sm font-semibold text-gray-700 text-right tabular-nums border-b border-gray-100"><?php echo number_format((int)$cat['room_count']) ?></td>
                                    <td class="py-2.5 sm:py-3 px-3 sm:px-4 text-xs sm:text-sm font-semibold text-gray-700 text-right tabular-nums border-b border-gray-100"><?php echo number_format((int)$cat['total_members']) ?></td>
                                </tr>
                            <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            </article>
        </main>

        <?php viewComponent('footer_inner') ?>
    </div>

    <?php \App\Views\Ads\GoogleAdsense::loadAdsTag() ?>
    <?php echo $_breadcrumbsSchema ?>
</body>

</html>
