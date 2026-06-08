<?php

declare(strict_types=1);

namespace App\Services\Alpha;

use App\Models\ApiRepositories\Alpha\AlphaAlertRepository;
use App\Services\OpenChat\Registration\OpenChatFromCrawlerRegistration;

/**
 * 未登録部屋の自動登録キュー(alpha_search_seen_room_ja)を消化するサービス。
 *
 * キーワードウォッチの毎時検索で見つかった「オプチャグラフ未登録」の部屋が
 * キューに溜まっている。これを古い順に取り出し、本体オプチャグラフへ登録する。
 *
 * 品質ゲートは設けず（見つかった部屋は全部登録する方針）、運用安全のために
 *   (a) 1回あたりの件数上限 REGISTER_LIMIT
 *   (b) 取得失敗のリトライ上限 AlphaAlertRepository::SEEN_ROOM_MAX_FAIL
 * だけを設ける。
 *
 * 結果分類（OpenChatFromCrawlerRegistration::registerOpenChatFromCrawler の戻り {message,id}）:
 *   - id が int        … 登録成功 or 既登録（既登録は専用メッセージ）。どちらもキューから削除。
 *   - id が null       … 取得失敗（部屋消失/非公開/ネットワーク等）。fail_count++ し、
 *                        上限到達なら諦めて削除。
 *
 * ja(urlRoot=='') 専用。毎時クロールの末尾(SyncOpenChat::hourlyTask)から1回呼ばれる。
 */
class AlphaSeenRoomRegistrationService
{
    /** 1回(=毎時1サイクル)あたりに登録を試みる最大件数。 */
    public const REGISTER_LIMIT = 30;

    /** registerOpenChatFromCrawler が「既に登録済み」を返すときのメッセージ。 */
    private const ALREADY_REGISTERED_MESSAGE = 'オープンチャットが既に登録されています';

    public function __construct(
        private AlphaAlertRepository $repo,
        private OpenChatFromCrawlerRegistration $registration,
    ) {
    }

    /**
     * キューを古い順に最大 $limit 件処理する。
     *
     * @return array{processed:int, registered:int, alreadyRegistered:int, failed:int, givenUp:int}
     */
    public function run(int $limit = self::REGISTER_LIMIT): array
    {
        $queue = $this->repo->getRegistrationQueue($limit);

        $registered = 0;
        $alreadyRegistered = 0;
        $failed = 0;   // この回で取得に失敗した件数（リトライ余地あり/諦め含む）
        $givenUp = 0;  // 失敗上限に達して削除した件数

        foreach ($queue as $room) {
            $emid = $room['emid'];
            if ($emid === '') {
                $this->repo->deleteSeenRoom($emid);
                continue;
            }

            try {
                $url = $this->buildCoverUrl($emid);
                $result = $this->registration->registerOpenChatFromCrawler($url);

                if (is_int($result['id'])) {
                    // 登録成功 or 既登録 … どちらもキューから外す
                    if (($result['message'] ?? '') === self::ALREADY_REGISTERED_MESSAGE) {
                        $alreadyRegistered++;
                    } else {
                        $registered++;
                    }
                    $this->repo->deleteSeenRoom($emid);
                    continue;
                }

                // id 無し = 取得失敗（無効URL/ネットワーク/収集拒否/メンテ等）
                $failed++;
            } catch (\Throwable $e) {
                // 1部屋の失敗で全体を止めない。取得失敗として計上しリトライに回す。
                $failed++;
            }

            // 取得失敗時: fail_count を増やし、上限到達なら諦めて削除（次回は拾わせない）。
            $this->repo->incrementSeenRoomFail($emid);
            if ($room['fail_count'] + 1 >= AlphaAlertRepository::SEEN_ROOM_MAX_FAIL) {
                $this->repo->deleteSeenRoom($emid);
                $givenUp++;
            }
        }

        return [
            'processed' => count($queue),
            'registered' => $registered,
            'alreadyRegistered' => $alreadyRegistered,
            'failed' => $failed,
            'givenUp' => $givenUp,
        ];
    }

    /**
     * emid から、登録APIの URL マッチパターンが受理する cover URL を組み立てる。
     * ja(urlRoot=='') のパターン: https://openchat.line.me/jp/cover/{emid}
     */
    private function buildCoverUrl(string $emid): string
    {
        return 'https://openchat.line.me/jp/cover/' . $emid;
    }
}
