<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Config\AppConfig;
use App\Models\Repositories\OpenChatPageRepositoryInterface;
use App\Services\OgImage\OcCardImageGenerator;
use App\Services\Statistics\StatisticsChartArrayService;
use Shared\MimimalCmsConfig;

/**
 * ルーム個別ページの動的OGP画像（/oc/{id}/card）。
 *
 * 生成はリクエスト時オンデマンド + ファイルキャッシュ（storage/og-card/{lang}/）。
 * 毎時cronには組み込まない（シェアされた部屋だけ生成されれば十分で、全件事前生成は不要）。
 * SNSクローラー向けのエンドポイントなので X-Robots-Tag: noindex を返す。
 */
class OcCardImageController
{
    /** キャッシュ有効期間（秒）。毎時クロールなので6時間で十分新鮮 */
    private const CACHE_TTL = 21600;

    /** GC: この日数を超えた古いカードは掃除対象（再共有されれば再生成される） */
    private const GC_MAX_AGE_DAYS = 7;

    /** GC: 生成(キャッシュミス)時にこの確率(1/N)でディレクトリ掃除を実行 */
    private const GC_PROBABILITY = 50;

    function index(
        int $open_chat_id,
        OpenChatPageRepositoryInterface $ocRepo,
        StatisticsChartArrayService $chartService,
        OcCardImageGenerator $generator,
    ) {
        $lang = str_replace('/', '', MimimalCmsConfig::$urlRoot) ?: 'ja';
        $cachePath = AppConfig::OG_CARD_CACHE_DIR . "/{$lang}/{$open_chat_id}.png";

        if (is_file($cachePath) && (time() - filemtime($cachePath)) < self::CACHE_TTL) {
            $this->send($cachePath);
        }

        $oc = $ocRepo->getOpenChatById($open_chat_id);
        if (!$oc) {
            return false;
        }

        // 古いキャッシュの確率的GC。クローラーが全部屋分を舐めてもディスクが無限に膨らまないよう、
        // 生成のたびに 1/GC_PROBABILITY の確率で GC_MAX_AGE_DAYS 超のファイルを削除する
        if (random_int(1, self::GC_PROBABILITY) === 1) {
            $this->gcOldCards(dirname($cachePath));
        }

        // 直近30日のメンバー数系列（無い部屋は数値のみのカードになる）
        $series = [];
        $diffWeek = null;
        $dto = $chartService->buildStatisticsChartArray(
            $open_chat_id,
            null,
            date('Y-m-d', time() - 29 * 86400),
            date('Y-m-d'),
        );
        if ($dto && $dto->member) {
            $series = $dto->member;
            $n = count($series);
            if ($n >= 2) {
                $last = $series[$n - 1];
                $weekAgo = $series[max(0, $n - 8)];
                if ($last !== null && $weekAgo !== null) {
                    $diffWeek = $last - $weekAgo;
                }
            }
        }

        $ok = $generator->generate(
            $cachePath,
            (int)$oc['member'],
            $diffWeek,
            $series,
            imgPreviewUrl($oc['img_url']),
        );

        if (!$ok || !is_file($cachePath)) {
            // 生成失敗時はデフォルトOGP画像で代替（リンク切れカードを出さない）
            $fallback = AppConfig::ROOT_PATH . 'public/' . AppConfig::DEFAULT_OGP_IMAGE_FILE_PATH;
            if (is_file($fallback)) {
                $this->send($fallback);
            }
            return false;
        }

        $this->send($cachePath);
    }

    /** GC_MAX_AGE_DAYS を超えた古いカードPNGを削除する */
    private function gcOldCards(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $threshold = time() - self::GC_MAX_AGE_DAYS * 86400;
        foreach (glob($dir . '/*.png') ?: [] as $file) {
            if (@filemtime($file) < $threshold) {
                @unlink($file);
            }
        }
    }

    /** PNG をキャッシュヘッダー付きで送出して終了する */
    private function send(string $path): void
    {
        header('Content-Type: image/png');
        header('Cache-Control: public, max-age=21600');
        header('X-Robots-Tag: noindex');
        readfile($path);
        exit;
    }
}
