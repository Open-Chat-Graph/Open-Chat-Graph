<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Services\OgImage\OgCardHttpResponder;
use App\Services\OgImage\RecommendCardImageGenerator;
use App\Services\Recommend\RecommendPageList;
use App\Services\Security\ConcurrentRequestGuard;

/**
 * テーマ別ランキングページの動的OGP画像（/recommend/{tag}/card）と検索用1:1サムネイル（/recommend/{tag}/thumb）。
 *
 * リクエストのたびに生成して PNG をそのまま返す（オリジンにはファイルキャッシュを持たない）。
 * 実キャッシュは Cloudflare のエッジに任せる（強い Cache-Control を返す）。SNSクローラー向けなので
 * X-Robots-Tag: noindex。
 *
 * オリジン保護（本番は php-fcgi が実質2本）:
 *  - 生成は 1IP×同時1本に制限（ConcurrentRequestGuard）。スコープは /oc/{id}/card と共通の
 *    'og-card'＝1つのIPがOGP生成系エンドポイント全体で複数ワーカーを同時に食えないようにする。
 *    溢れた分は即デフォルト画像で返す。
 *  - アイコン取得（最大5件）は FileDownloader の timeout/max_duration でワーカー拘束を厳密化。
 */
class RecommendCardImageController
{
    function index(
        string $tag,
        RecommendPageList $recommendPageList,
        RecommendCardImageGenerator $generator,
        ConcurrentRequestGuard $guard,
        OgCardHttpResponder $responder,
    ) {
        // 生成は 1IP×同時1本に制限。並列で来た2本目以降は生成せず即デフォルト画像を返す。
        if (!$guard->tryAcquire('og-card', getIP())) {
            $responder->sendDefault();
        }

        // ページ本体と同じ基準でタグを解決（大文字小文字を無視）。存在しないタグは 404
        $tag = $recommendPageList->getValidTag($tag);
        if (!$tag) {
            return false;
        }

        $recommend = $recommendPageList->getListDto($tag);
        if (!$recommend || !$recommend->getCount()) {
            // 掲載部屋が無いタグはカードを作らずデフォルトOGP画像で代替
            $responder->sendDefault();
        }

        // ページの表示順そのまま、上位を背景のミニカードに使う
        $list = $recommend->getList(false, RecommendCardImageGenerator::MAX_ROOMS);
        $rooms = array_map(fn(array $row) => [
            'name' => (string)$row['name'],
            'member' => (int)$row['member'],
            'iconUrl' => imgPreviewUrl($row['img_url']),
        ], $list);

        $png = $generator->renderPng($tag, $rooms);

        if ($png === null) {
            // 生成不可の環境ではデフォルトOGP画像で代替（リンク切れカードを出さない）
            $responder->sendDefault();
        }

        $responder->sendPng($png);
    }

    /**
     * 検索用 1:1 サムネイル（/recommend/{tag}/thumb・meta name="thumbnail" 用）。
     * 以前は1位の部屋アイコン（LINE CDN直リンク）だったものを自前生成に置き換える。
     * キャッシュ・オリジン保護は card と同じ方針（エッジキャッシュ＋1IP同時1本）。
     */
    function thumb(
        string $tag,
        RecommendPageList $recommendPageList,
        RecommendCardImageGenerator $generator,
        ConcurrentRequestGuard $guard,
        OgCardHttpResponder $responder,
    ) {
        // card とはスコープを分ける。クローラーはページの og:image(/card) と thumbnail(/thumb) を
        // 同一IPから並列に取りに来るため、同一スコープだと片方が常にデフォルト画像へ落ちる
        if (!$guard->tryAcquire('og-thumb', getIP())) {
            $responder->sendDefault(square: true);
        }

        $tag = $recommendPageList->getValidTag($tag);
        if (!$tag) {
            return false;
        }

        $png = $generator->renderThumbPng($tag);

        if ($png === null) {
            $responder->sendDefault(square: true);
        }

        // 検索サムネイル用なので noindex を付けない（付けると検索側が採用しない恐れ）
        $responder->sendPng($png, noindex: false);
    }
}
