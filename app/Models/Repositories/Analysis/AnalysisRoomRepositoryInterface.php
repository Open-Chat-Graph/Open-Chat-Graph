<?php

declare(strict_types=1);

namespace App\Models\Repositories\Analysis;

/**
 * 詳細成長分析（/analysis）の open_chat（MySQL）側アクセス。
 * 「現在存在する部屋」の母集合・カテゴリ・現在メンバー数・キーワード照合・表示用ハイドレートを担う。
 */
interface AnalysisRoomRepositoryInterface
{
    /** open_chat の最大 id（チャンク分割の上限） */
    public function getMaxOpenChatId(): int;

    /**
     * open_chat_id ∈ [lo, hi) の現存ルームのカテゴリと現在メンバー数。
     * 「現在存在する部屋」の判定（statistics にしか無い消滅部屋を除外）にも使う。
     *
     * @return array<int, array{category:int, member:int}>
     */
    public function getRoomsInRange(int $lo, int $hi): array;

    /**
     * 部屋名にキーワードを含む open_chat_id の一覧（結果取得時のキーワード絞り込み用）。
     *
     * @return int[]
     */
    public function findIdsByKeyword(string $keyword): array;

    /**
     * 表示用フィールドを id 群について取得（最終スライスのハイドレート用）。
     *
     * @param int[] $ids
     * @return array<int, array{name:string, desc:string, member:int, img:string, emblem:int, joinMethodType:int, category:int}>
     */
    public function hydrate(array $ids): array;
}
