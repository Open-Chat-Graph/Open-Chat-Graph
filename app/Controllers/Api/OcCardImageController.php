<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Config\AppConfig;
use App\Models\Repositories\OpenChatPageRepositoryInterface;
use App\Services\OgImage\OcCardImageGenerator;
use App\Services\Security\ConcurrentRequestGuard;
use App\Services\Statistics\StatisticsChartArrayService;

/**
 * ルーム個別ページの動的OGP画像（/oc/{id}/card）。
 *
 * リクエストのたびに生成して PNG をそのまま返す（オリジンにはファイルキャッシュを持たない）。
 * 実キャッシュは Cloudflare のエッジに任せる（強い Cache-Control を返す）。SNSクローラー向けなので
 * X-Robots-Tag: noindex。
 *
 * オリジン保護（本番は php-fcgi が実質2本）:
 *  - 生成は 1IP×同時1本に制限（ConcurrentRequestGuard）。CFキャッシュをクエリ改変等で
 *    バイパスして多数idを並列生成させても、1IPが複数ワーカーを同時に食えないようにする。
 *    溢れた分は即デフォルト画像で返す。
 *  - アイコン取得は FileDownloader の timeout/max_duration でワーカー拘束を厳密化。
 */
class OcCardImageController
{
    /** エッジ／ブラウザのキャッシュ秒数。og:image は日付クエリ(?d=Ymd)で日次ローテするので長めでよい */
    private const CACHE_MAX_AGE = 43200; // 12h

    function index(
        int $open_chat_id,
        OpenChatPageRepositoryInterface $ocRepo,
        StatisticsChartArrayService $chartService,
        OcCardImageGenerator $generator,
        ConcurrentRequestGuard $guard,
    ) {
        // 生成は 1IP×同時1本に制限。並列で来た2本目以降は生成せず即デフォルト画像を返す。
        if (!$guard->tryAcquire('og-card', getIP())) {
            $this->sendDefault();
        }

        $oc = $ocRepo->getOpenChatById($open_chat_id);
        if (!$oc) {
            return false;
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

        $png = $generator->renderPng(
            (string)$oc['name'],
            (int)$oc['member'],
            $diffWeek,
            $series,
            imgPreviewUrl($oc['img_url']),
        );

        if ($png === null) {
            // 生成不可の環境ではデフォルトOGP画像で代替（リンク切れカードを出さない）
            $this->sendDefault();
        }

        $this->sendPng($png);
    }

    /** 生成したPNGバイト列を、エッジがキャッシュできるヘッダー付きで送出して終了する */
    private function sendPng(string $bytes): void
    {
        header('Content-Type: image/png');
        header('Cache-Control: public, max-age=' . self::CACHE_MAX_AGE);
        header('X-Robots-Tag: noindex');
        echo $bytes;
        exit;
    }

    /** デフォルトOGP画像を送って終了する（生成不可・混雑時のフォールバック） */
    private function sendDefault(): void
    {
        $fallback = AppConfig::ROOT_PATH . 'public/' . AppConfig::DEFAULT_OGP_IMAGE_FILE_PATH;
        if (is_file($fallback)) {
            header('Content-Type: image/png');
            // フォールバックもエッジ(CF)にはキャッシュさせる。ただし混雑・一時失敗で出たものが
            // 長時間ピンされないよう、本来のカード(12h)より短いTTLにして早めに再生成へ戻す。
            header('Cache-Control: public, max-age=600');
            header('X-Robots-Tag: noindex');
            readfile($fallback);
        }
        exit;
    }
}
