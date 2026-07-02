<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Config\AppConfig;
use App\Models\Repositories\OpenChatPageRepositoryInterface;
use App\Services\OgImage\OcCardImageGenerator;
use App\Services\Security\ConcurrentRequestGuard;
use App\Services\Statistics\StatisticsChartArrayService;
use Shared\MimimalCmsConfig;

/**
 * ルーム個別ページの動的OGP画像（/oc/{id}/card）。
 *
 * 生成はリクエスト時オンデマンド + ファイルキャッシュ（storage/og-card/{lang}/）。
 * 毎時cronには組み込まない（シェアされた部屋だけ生成されれば十分で、全件事前生成は不要）。
 * SNSクローラー向けのエンドポイントなので X-Robots-Tag: noindex を返す。
 *
 * 過負荷対策（本番は php-fcgi が実質2本・オリジンは Cloudflare 限定）:
 *  - キャッシュヒットは readfile だけ（生成コストゼロ）。
 *  - 生成（キャッシュミス）は 1IP×同時1本に制限（ConcurrentRequestGuard）。並列列挙で
 *    全ワーカーを生成に張り付かせるのを防ぎ、溢れた分は即デフォルト画像で返す。
 *  - アイコン取得は curl の総時間上限つき（OcCardImageGenerator 側）でワーカー拘束を厳密化。
 *  - 生成時に確率的GCで古いPNGと孤児tmpを掃除し、ディスク肥大を抑える。
 */
class OcCardImageController
{
    /** キャッシュ有効期間（秒）。毎時クロールなので6時間で十分新鮮 */
    private const CACHE_TTL = 21600;

    /** GC: この日数を超えた古いカードは掃除対象（再共有されれば再生成される） */
    private const GC_MAX_AGE_DAYS = 3;

    /** GC: 生成(キャッシュミス)時にこの確率(1/N)でディレクトリ掃除を実行 */
    private const GC_PROBABILITY = 50;

    /** 孤児 tmp ファイルを掃除する経過秒（生成中断で残った *.tmp を回収） */
    private const GC_TMP_MAX_AGE_SEC = 600;

    function index(
        int $open_chat_id,
        OpenChatPageRepositoryInterface $ocRepo,
        StatisticsChartArrayService $chartService,
        OcCardImageGenerator $generator,
        ConcurrentRequestGuard $guard,
    ) {
        $lang = str_replace('/', '', MimimalCmsConfig::$urlRoot) ?: 'ja';
        $cachePath = AppConfig::OG_CARD_CACHE_DIR . "/{$lang}/{$open_chat_id}.png";

        if (is_file($cachePath) && (time() - filemtime($cachePath)) < self::CACHE_TTL) {
            $this->send($cachePath);
        }

        // 生成は 1IP×同時1本に制限。並列で来た2本目以降は生成せず即デフォルト画像を返す
        // （SNSクローラーやスクレイパーが多数idを並列列挙してもワーカーを食い潰さない）。
        if (!$guard->tryAcquire('og-card', getIP())) {
            $this->sendFallback();
        }

        $oc = $ocRepo->getOpenChatById($open_chat_id);
        if (!$oc) {
            return false;
        }

        // 古いキャッシュの確率的GC。クローラーが全部屋分を舐めてもディスクが無限に膨らまないよう、
        // 生成のたびに 1/GC_PROBABILITY の確率で古いファイルと孤児tmpを削除する
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
            // 日付軸はリクエスト範囲の末尾まで null 埋めされるため、末尾の実データ位置から差分を取る
            $lastIdx = null;
            for ($i = count($series) - 1; $i >= 0; $i--) {
                if ($series[$i] !== null) {
                    $lastIdx = $i;
                    break;
                }
            }
            if ($lastIdx !== null) {
                $weekAgo = $series[max(0, $lastIdx - 7)];
                if ($weekAgo !== null) {
                    $diffWeek = (int)$series[$lastIdx] - (int)$weekAgo;
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
            $this->sendFallback();
        }

        $this->send($cachePath);
    }

    /**
     * GC_MAX_AGE_DAYS を超えた古いカードPNGと、生成中断で残った孤児tmpを削除する。
     * 削除系はこのアプリのハンドラで警告→例外になるため個別に握りつぶす（掃除失敗で500にしない）。
     */
    private function gcOldCards(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $now = time();
        $pngThreshold = $now - self::GC_MAX_AGE_DAYS * 86400;
        $tmpThreshold = $now - self::GC_TMP_MAX_AGE_SEC;
        foreach (glob($dir . '/*') ?: [] as $file) {
            try {
                $threshold = str_ends_with($file, '.tmp') ? $tmpThreshold : $pngThreshold;
                if (filemtime($file) < $threshold) {
                    unlink($file);
                }
            } catch (\Throwable $e) {
                // 競合で既に消えている等は無視
            }
        }
    }

    /** デフォルトOGP画像を送って終了する（生成不可・混雑時のフォールバック） */
    private function sendFallback(): void
    {
        $fallback = AppConfig::ROOT_PATH . 'public/' . AppConfig::DEFAULT_OGP_IMAGE_FILE_PATH;
        if (is_file($fallback)) {
            $this->send($fallback);
        }
        exit;
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
