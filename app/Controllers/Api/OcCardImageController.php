<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\Repositories\OpenChatPageRepositoryInterface;
use App\Services\OgImage\OcCardImageGenerator;
use App\Services\OgImage\OgCardHttpResponder;
use App\Services\Security\ConcurrentRequestGuard;
use App\Services\Statistics\StatisticsChartArrayService;

/**
 * ルーム個別ページの動的OGP画像（/oc/{id}/card）と検索用1:1サムネイル（/oc/{id}/thumb）。
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
    function index(
        int $open_chat_id,
        OpenChatPageRepositoryInterface $ocRepo,
        StatisticsChartArrayService $chartService,
        OcCardImageGenerator $generator,
        ConcurrentRequestGuard $guard,
        OgCardHttpResponder $responder,
    ) {
        // 生成は 1IP×同時1本に制限。並列で来た2本目以降は生成せず即デフォルト画像を返す。
        if (!$guard->tryAcquire('og-card', getIP())) {
            $responder->sendDefault();
        }

        $oc = $ocRepo->getOpenChatById($open_chat_id);
        if (!$oc) {
            return false;
        }

        // 直近1週間のメンバー数系列（先週の同じ曜日→今日の8点。ページの「1週間」統計と同じ観測窓）。
        // 無い部屋は数値のみのカードになる
        $series = [];
        $dates = [];
        $diffWeek = null;
        $dto = $chartService->buildStatisticsChartArray(
            $open_chat_id,
            null,
            date('Y-m-d', time() - 7 * 86400),
            date('Y-m-d'),
        );
        if ($dto && $dto->member) {
            $series = $dto->member;
            $dates = $dto->date;
            // 日付軸はリクエスト範囲の末尾まで null 埋めされるため、末尾の実データ位置から差分を取る
            $lastIdx = null;
            for ($i = count($series) - 1; $i >= 0; $i--) {
                if ($series[$i] !== null) {
                    $lastIdx = $i;
                    break;
                }
            }
            if ($lastIdx !== null) {
                // ちょうど1週間前（同じ曜日）の点があるときだけ増減を出す。窓が8点しか無いので、
                // 旧実装の max(0, ...) の丸めだと「最終データが数日前の部屋」で1週間未満の増減を
                // 「/ 1週間」と表示してしまう（旧30日窓ではクランプが実質発火しなかった）
                $weekAgoIdx = $lastIdx - 7;
                $weekAgo = $weekAgoIdx >= 0 ? $series[$weekAgoIdx] : null;
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
            $dates,
        );

        if ($png === null) {
            // 生成不可の環境ではデフォルトOGP画像で代替（リンク切れカードを出さない）
            $responder->sendDefault();
        }

        $responder->sendPng($png);
    }

    /**
     * 検索用 1:1 サムネイル（/oc/{id}/thumb・meta name="thumbnail" 用）。
     * 以前は LINE CDN の画像URL直リンクだったものを自前生成に置き換える。
     * キャッシュ・オリジン保護は /oc/{id}/card と同じ方針（エッジキャッシュ＋1IP同時1本）。
     */
    function thumb(
        int $open_chat_id,
        OpenChatPageRepositoryInterface $ocRepo,
        OcCardImageGenerator $generator,
        ConcurrentRequestGuard $guard,
        OgCardHttpResponder $responder,
    ) {
        // card とはスコープを分ける。クローラーはページの og:image(/card) と thumbnail(/thumb) を
        // 同一IPから並列に取りに来るため、同一スコープだと片方が常にデフォルト画像へ落ちる
        if (!$guard->tryAcquire('og-thumb', getIP())) {
            $responder->sendDefault(square: true);
        }

        $oc = $ocRepo->getOpenChatById($open_chat_id);
        if (!$oc) {
            return false;
        }

        $png = $generator->renderThumbPng((string)$oc['name'], imgPreviewUrl($oc['img_url']));

        if ($png === null) {
            $responder->sendDefault(square: true);
        }

        // 検索サムネイル用なので noindex を付けない（付けると検索側が採用しない恐れ）
        $responder->sendPng($png, noindex: false);
    }
}
