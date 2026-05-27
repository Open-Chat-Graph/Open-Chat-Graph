<?php

declare(strict_types=1);

namespace App\Services\Recommend\TagDefinition\Ja;

use App\Services\Recommend\TagDefinition\JaTagMetadata;

/**
 * /recommend/{tag} ページに表示する「タグ固有の紹介文」。
 *
 * 紹介文の実データは Git管理JSON(data/ja.json)の "descriptions" キーで管理する
 * （旧 private const DESCRIPTIONS から移行。挙動完全一致）。
 *
 * 狙い:
 *   検索需要は高いのに掲載順位が伸び悩む(=表示は多いがクリックが取れていない)タグに、
 *   テーマ固有の価値ある説明文を与え、ユーザーの理解とクリック率・検索順位の改善を狙う。
 *
 * 選定:
 *   対象タグは Google Search Console の機会分析(表示回数 × 順位の取りこぼし)で抽出した
 *   「アクセスの望みがある」高需要タグ。
 *
 * 文章の作り方:
 *   各タグについて、テーマをWeb調査して執筆 → SEO/UX観点でレビュー → 指摘を反映して確定、
 *   というサイクルで作成している。内部統計データの機械的な解説ではなく、初見ユーザーに
 *   「何のリストで、なぜ価値があるか」が伝わる文章にしている。
 *
 * 注意:
 *   - キーは RecommendPageList::getValidTag() が返す正規タグ名(getAllTagNames 由来)と
 *     完全一致させること。一致しなければ単に紹介文が出ない(ベースライン文のみ)。
 *   - 日本語(ja)ロケール専用。全タグ共通の価値訴求は recommend_content.php の
 *     ベースライン文が担うため、ここではテーマ固有の内容に絞り、重複させない。
 *   - 純粋な静的テキスト。HTML は持たせない(描画側で h() でエスケープする)。
 */
class RecommendTagDescription
{
    /**
     * 正規タグ名に対応する紹介文を返す。無ければ null。
     */
    public static function get(string $tag): ?string
    {
        return JaTagMetadata::descriptions()[$tag] ?? null;
    }
}
