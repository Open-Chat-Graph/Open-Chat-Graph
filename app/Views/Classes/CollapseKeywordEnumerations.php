<?php

declare(strict_types=1);

namespace App\Views\Classes;

class CollapseKeywordEnumerations implements CollapseKeywordEnumerationsInterface
{
    public static function collapse(
        string $text,
        int $minItems = 12,
        int $keepFirst = 1,
        int $allowHashtags = 1,
        string $extraText = '',
        bool $returnRemovedOnly = false,
        int $embeddedMinItems = 3
    ): string {
        return (new static)->collapseJa($text, $minItems, $keepFirst, $allowHashtags, $extraText, $returnRemovedOnly, $embeddedMinItems);
    }

    function collapseJa(
        string $text,
        int $minItems = 12,
        int $keepFirst = 1,
        int $allowHashtags = 1,
        string $extraText = '',
        bool $returnRemovedOnly = false,
        int $embeddedMinItems = 3
    ): string {
        $removedParts = [];

        // パラメータの範囲チェックとサニタイズ
        if ($minItems < 1) $minItems = 1;
        if ($minItems > 10000) $minItems = 10000;  // 正規表現の制限を考慮
        if ($keepFirst < 0) $keepFirst = 0;
        if ($keepFirst > 10000) $keepFirst = 10000;
        if ($allowHashtags < 0) $allowHashtags = 0;
        if ($allowHashtags > 1000) $allowHashtags = 1000;
        if ($embeddedMinItems < 2) $embeddedMinItems = 2;
        if ($embeddedMinItems > 20) $embeddedMinItems = 20;

        // 本文（ハッシュタグを除去した文字列）を作る
        $content = preg_replace('/[#＃][^\s#＃]+/u', '', $text);
        // preg_replaceがnullを返した場合の対処
        if ($content === null) {
            $content = $text;
        }
        $contentLower = mb_strtolower($content . $extraText, 'UTF-8');

        $keptHashtags = 0;
        $textAfterHashtagProcess = preg_replace_callback('/[#＃]([^\s#＃]+)/u', function ($m) use (
            &$keptHashtags,
            $allowHashtags,
            $contentLower,
            &$removedParts
        ) {
            $tagText = $m[1];
            // allowHashtags > 0 のときのみ保持の可能性を検討
            if ($allowHashtags > 0 && $keptHashtags < $allowHashtags) {
                // 本文にタグ文字列が含まれていない場合のみ保持対象
                $tagLower = mb_strtolower($tagText, 'UTF-8');
                if (mb_strpos($contentLower, $tagLower) === false) {
                    $keptHashtags++;
                    return $m[0]; // 元のハッシュタグを保持
                }
            }
            // 条件を満たさないものは削除
            $removedParts[] = $m[0];
            return '';
        }, $text);

        // preg_replace_callbackがnullを返した場合の対処
        if ($textAfterHashtagProcess === null) {
            $textAfterHashtagProcess = $text;
        }

        // 文章内埋め込み型キーワード羅列の処理（新規追加）
        // 「など」で終わる説明部分は含めずに、読点区切りのキーワード羅列のみを対象
        // より具体的なパターンで文中のキーワード羅列をキャッチ（引数で調整可能）
        // 最初に一致したパターンを使用（など、から、まで、といった、について、を、の）
        $embeddedMinCount = $embeddedMinItems - 1; // 正規表現用に1を引く
        $embeddedPattern = '/([^。！？\n\r]*[、，,][ 　]*[^、，,。！？；：\n\r]+(?:[、，,][ 　]*[^、，,。！？；：\n\r]+){' . $embeddedMinCount . ',})(?=など|から|まで|といった|について)/u';

        // 埋め込み型キーワード羅列を処理
        $textAfterHashtagProcess = preg_replace_callback($embeddedPattern, function ($m) use ($keepFirst, &$removedParts, $embeddedMinItems) {
            return self::processEmbeddedKeywords($m[0], $keepFirst, $removedParts, 0.7, $embeddedMinItems);
        }, $textAfterHashtagProcess);

        // preg_replace_callbackがnullを返った場合の対処
        if ($textAfterHashtagProcess === null) {
            $textAfterHashtagProcess = $text;
        }

        // 区切り: 半角/全角スペース・読点（、，,）・縦棒（|｜）・改行（\R）
        $sep = '(?:[ 　]*[、 ，,|｜][ 　]*|[ 　]+|[ 　]*\R+[ 　]*)';
        $token = '[^\s、 ，,|｜。！？；：]+';

        // 正規表現パターンの構築（エラー防止のため制限をチェック）
        $minItemsForRegex = min($minItems - 1, 1000); // 正規表現の制限を考慮
        $pattern = '/(?:' . $token . $sep . '){' . $minItemsForRegex . ',}' . $token . '/u';

        // 縦棒区切りパターンの処理（より低い閾値で処理）
        // スペースを含む企業名等も考慮して、パイプ区切りパターンを改善
        // ただし、文章的な内容（助詞を含む、文の構造を持つ）は除外
        // 2個以上のトークンでも企業名羅列として処理
        $pipePattern = '/(?:[^|｜\n\r]+[|｜][ 　]*){1,}[^|｜\n\r]+/u';

        // エラーハンドラーを設定して正規表現エラーをキャッチ
        set_error_handler(function () {
            // 正規表現のコンパイルエラーを無視
            return true;
        }, E_WARNING);

        $result = preg_replace_callback($pipePattern, function ($m) use ($keepFirst, &$removedParts) {
            // まず、マッチした文字列が文章的かどうかを判定
            if (self::isSentenceLike($m[0])) {
                return $m[0]; // 文章と判定されたら保持
            }

            $tokens = preg_split('/[ 　]*[|｜][ 　]*/u', $m[0], -1, PREG_SPLIT_NO_EMPTY);
            // preg_splitがfalseを返した場合の対処
            if ($tokens === false) {
                return $m[0];
            }
            // 各トークンの前後の空白を除去
            $tokens = array_map('trim', $tokens);
            $filtered = array_values(array_filter($tokens, fn($t) => self::isKeywordLike($t)));

            // 縦棒区切りの場合、70%以上がキーワード的かつ単純な羅列（2個以上のトークン）なら除去対象
            // ただし、トークン数が少ない場合（2個）はより厳しい条件（90%以上）を適用
            $threshold = count($tokens) <= 2 ? 0.9 : 0.7;
            if (count($filtered) >= count($tokens) * $threshold) {
                if ($keepFirst <= 0) {
                    $removedParts[] = $m[0];
                    return '';
                }

                // 指定数以下なら改変しない
                if (count($filtered) <= $keepFirst) return $m[0];

                // 先頭 keepFirst 個だけ残して省略
                $removedKeywords = array_slice($filtered, $keepFirst);
                if (!empty($removedKeywords)) {
                    $removedParts[] = implode('｜', $removedKeywords);
                }
                return implode('｜', array_slice($filtered, 0, $keepFirst)) . '…';
            }

            return $m[0];
        }, $textAfterHashtagProcess);

        // preg_replace_callbackがnullを返した場合の対処
        if ($result === null) {
            $result = $textAfterHashtagProcess;
        }

        // 通常のキーワード羅列パターンの処理
        $processed = preg_replace_callback($pattern, function ($m) use ($keepFirst, &$removedParts) {
            // マッチしたテキストが複数の段落を含む場合、段落ごとに処理
            $paragraphs = preg_split('/\n\s*\n/', $m[0]);
            if (count($paragraphs) > 1) {
                $processedParts = [];
                foreach ($paragraphs as $paragraph) {
                    $paragraph = trim($paragraph);
                    if (empty($paragraph)) {
                        $processedParts[] = $paragraph;
                        continue;
                    }

                    // 各段落を個別に判定
                    if (self::isSentenceLike($paragraph)) {
                        $processedParts[] = $paragraph; // 文章的な段落は保持
                    } else {
                        // キーワード羅列として処理
                        $tokens = preg_split('/(?:[ 　、 ，,]+|\R+)/u', $paragraph, -1, PREG_SPLIT_NO_EMPTY);
                        if ($tokens === false) {
                            $processedParts[] = $paragraph;
                            continue;
                        }
                        $filtered = array_values(array_filter($tokens, fn($t) => self::isKeywordLike($t)));

                        // キーワード的なトークンが全体の50%未満なら保持
                        if (count($filtered) < count($tokens) * 0.5) {
                            $processedParts[] = $paragraph;
                            continue;
                        }

                        if (count($filtered) === 0) {
                            $processedParts[] = $paragraph;
                            continue;
                        }

                        // keepFirst=0 なら列挙を削除
                        if ($keepFirst <= 0) {
                            $removedParts[] = $paragraph;
                            $processedParts[] = ''; // 削除
                        } else {
                            // 指定数以下なら改変しない
                            if (count($filtered) <= $keepFirst) {
                                $processedParts[] = $paragraph;
                            } else {
                                // 先頭 keepFirst 個だけ残して省略
                                $removedKeywords = array_slice($filtered, $keepFirst);
                                if (!empty($removedKeywords)) {
                                    $removedParts[] = implode('、', $removedKeywords);
                                }
                                $processedParts[] = implode('、', array_slice($filtered, 0, $keepFirst)) . '…';
                            }
                        }
                    }
                }
                return implode("\n\n", array_filter($processedParts, fn($p) => $p !== ''));
            }

            // 単一段落の場合 - キーワード羅列と文章的な部分を分離して処理
            $matchedText = $m[0];

            // ハッシュタグや文章的な部分が混在している可能性を考慮
            // まず、明らかなキーワード羅列部分を特定
            if (preg_match('/^([^\s、，,#＃]*(?:[、，,][ 　]*[^\s、，,#＃]+)+)(.*)$/u', $matchedText, $parts)) {
                $keywordPart = $parts[1];
                $trailingPart = $parts[2];

                // キーワード部分のみで判定
                if (!self::isSentenceLike($keywordPart)) {
                    // キーワード羅列として処理
                    $tokens = preg_split('/(?:[ 　、 ，,]+|\R+)/u', $keywordPart, -1, PREG_SPLIT_NO_EMPTY);
                    if ($tokens !== false) {
                        $filtered = array_values(array_filter($tokens, fn($t) => self::isKeywordLike($t)));

                        // キーワード的なトークンが50%以上なら処理対象
                        if (count($filtered) >= count($tokens) * 0.5 && count($filtered) > 0) {
                            if ($keepFirst <= 0) {
                                $removedParts[] = $keywordPart;
                                return trim($trailingPart);
                            } else {
                                if (count($filtered) <= $keepFirst) {
                                    return $matchedText; // 改変しない
                                } else {
                                    $removedKeywords = array_slice($filtered, $keepFirst);
                                    if (!empty($removedKeywords)) {
                                        $removedParts[] = implode('、', $removedKeywords);
                                    }
                                    return implode('、', array_slice($filtered, 0, $keepFirst)) . '…' . $trailingPart;
                                }
                            }
                        }
                    }
                }
            }

            // 従来の処理（全体が文章的な場合）
            if (self::isSentenceLike($matchedText)) {
                return $matchedText;
            }

            // 改行は [] に入れず、オルタネーションで扱う
            $tokens = preg_split('/(?:[ 　、 ，,]+|\R+)/u', $m[0], -1, PREG_SPLIT_NO_EMPTY);
            // preg_splitがfalseを返った場合の対処
            if ($tokens === false) {
                return $m[0];
            }
            $filtered = array_values(array_filter($tokens, fn($t) => self::isKeywordLike($t)));

            // キーワード的なトークンが全体の50%未満なら保持
            if (count($filtered) < count($tokens) * 0.5) {
                return $m[0];
            }

            if (count($filtered) === 0) return $m[0];

            // keepFirst=0 なら列挙を削除
            if ($keepFirst <= 0) {
                $removedParts[] = $m[0];
                return '';
            }

            // 指定数以下なら改変しない
            if (count($filtered) <= $keepFirst) return $m[0];

            // 先頭 keepFirst 個だけ残して省略
            $removedKeywords = array_slice($filtered, $keepFirst);
            if (!empty($removedKeywords)) {
                $removedParts[] = implode('、', $removedKeywords);
            }
            return implode('、', array_slice($filtered, 0, $keepFirst)) . '…';
        }, $result);

        // preg_replace_callbackがnullを返した場合（エラー時）は元の文字列を使用
        $result = $processed !== null ? $processed : $result;

        // エラーハンドラーを復元
        restore_error_handler();

        // 除去された部分のみを返す場合
        if ($returnRemovedOnly) {
            return implode(' ', $removedParts);
        }

        // 削除後の体裁を軽く整える（空白・読点周り、連続改行など）
        $temp = preg_replace('/[ \t\x{3000}]+/u', ' ', $result);              // 連続空白→1
        if ($temp !== null) $result = $temp;

        $temp = preg_replace('/[ 　]*(?:[、，,])[ 　]*/u', '、', $result);      // 読点前後の空白整理
        if ($temp !== null) $result = $temp;

        $temp = preg_replace("/(\R){3,}/u", "\n\n", $result);                   // 3行以上の改行→2行
        if ($temp !== null) $result = $temp;

        $temp = preg_replace('/[ \t\x{3000}]+(\R)/u', '$1', $result);           // 行末空白除去
        if ($temp !== null) $result = $temp;

        return trim($result);
    }

    private static function isKeywordLike(string $t): bool
    {
        // 文字列が不正な場合は早期リターン
        if (!mb_check_encoding($t, 'UTF-8')) return false;

        // 記号を個別に除去（エンコーディング問題を回避）
        $cleaned = preg_replace('/^[()（）\[\]【】「」『』.,、。!！?？;；:：・\/／\-+＋_＿&＆]+|[()（）\[\]【】「」『』.,、。!！?？;；:：・\/／\-+＋_＿&＆]+$/u', '', $t);
        // preg_replaceがnullを返した場合の対処
        if ($cleaned === null) {
            $cleaned = $t;
        }
        $t = $cleaned;
        if ($t === '' || mb_strlen($t, 'UTF-8') > 24) return false;

        $letters = preg_match_all('/[\p{L}\p{N}]/u', $t);
        // preg_match_allがfalseを返した場合の対処
        if ($letters === false || $letters <= 0) return false;

        // アルファベットの数をカウント（英文判定用）
        $alphabet = preg_match_all('/[a-zA-Z]/u', $t);
        if ($alphabet === false) $alphabet = 0;

        // 英文の判定: アルファベットが50%以上の場合
        if ($alphabet / max(1, $letters) >= 0.5) {
            // 一般的な英単語（冠詞、前置詞、助動詞など）は文章の一部と判定
            $commonWords = [
                'the',
                'and',
                'for',
                'are',
                'but',
                'not',
                'you',
                'all',
                'with',
                'from',
                'this',
                'that',
                'have',
                'been',
                'will',
                'can',
                'may',
                'should',
                'would',
                'could',
                'about',
                'into'
            ];
            if (in_array(strtolower($t), $commonWords)) {
                return false;
            }

            // 企業名らしいパターン（大文字を含む、&記号、複数単語など）はキーワードとして扱う
            if (preg_match('/[A-Z]/', $t) || preg_match('/&/', $t) || preg_match('/\s+/', $t)) {
                return true;
            }

            // 英文の場合、4文字以上の単語は通常の文章と判定（ただし上記企業名パターンを除く）
            if (mb_strlen($t, 'UTF-8') >= 4) {
                return false;
            }
        }

        $hiragana = preg_match_all('/\p{Hiragana}/u', $t);
        if ($hiragana === false) $hiragana = 0;

        return ($hiragana / max(1, $letters)) <= 0.3;
    }

    private static function isSentenceLike(string $matchedText): bool
    {
        // 英文の文章パターンをチェック（動詞、前置詞句など）
        if (preg_match('/\b(is|are|was|were|have|has|had|will|would|can|could|should)\b/i', $matchedText)) {
            return true;
        }

        // 文章的な表現パターン（利用ルールなど）
        if (preg_match('/こと[｜|]|すること[｜|]|[でです][す。｜|]|を[行う|求める|尊重]/u', $matchedText)) {
            return true;
        }

        // 説明的な文章（「～です」「～こと」「～場合」など）
        if (preg_match('/です[｜|]|こと[｜|]|場合[｜|]|こと[｜|]/u', $matchedText)) {
            return true;
        }

        // 単純な読点区切りのキーワード羅列は文章ではない
        // 読点が多く、助詞が少ない場合はキーワード羅列と判定
        $commaCount = substr_count($matchedText, '、') + substr_count($matchedText, '，') + substr_count($matchedText, ',');
        $pipeCount = substr_count($matchedText, '｜') + substr_count($matchedText, '|');
        $particleMatches = preg_match_all('/[がをにへでとやからまで]/u', $matchedText);
        // preg_match_allがfalseを返した場合の対処
        if ($particleMatches === false) $particleMatches = 0;

        // 縦棒が多く、助詞が少ない場合はキーワード羅列
        if ($pipeCount >= 5 && $particleMatches <= $pipeCount / 3) {
            return false;
        }

        // 読点が5個以上あり、助詞が読点の半分以下の場合はキーワード羅列
        if ($commaCount >= 5 && $particleMatches <= $commaCount / 2) {
            return false;
        }

        // スペース区切りの長いキーワード羅列の場合
        // 助詞密度が低く、スペースで区切られた多数の項目がある場合はキーワード羅列と判定
        $textLength = mb_strlen($matchedText, 'UTF-8');
        $spaceCount = substr_count($matchedText, ' ') + substr_count($matchedText, '　');
        $particleDensity = $particleMatches / max(1, $textLength);

        // スペース区切りで長いテキスト（100文字以上）かつ助詞密度が低い場合（0.02未満）はキーワード羅列
        if ($textLength >= 100 && $spaceCount >= 10 && $particleDensity < 0.02) {
            return false;
        }

        // 日本語の助詞をチェック（ただし読点が多い場合は除外）
        if ($particleMatches > 0 && $commaCount < 5) {
            return true;
        }

        return false;
    }

    /**
     * 埋め込み型キーワード羅列の処理
     */
    private static function processEmbeddedKeywords(string $matchedText, int $keepFirst, array &$removedParts, float $threshold, int $embeddedMinItems = 3): string
    {
        // 「など」以降の文章的表現がある場合、「など」より前の部分のみを処理対象とする
        $processingText = $matchedText;
        if (preg_match('/(.*?)(?:など|から|まで|といった|について|として|による|によって|に対して|の中|を含む)/u', $matchedText, $matches)) {
            $processingText = $matches[1];
        }

        // 読点で分割してトークンを取得
        $tokens = preg_split('/[ 　]*[、，,][ 　]*/u', $processingText, -1, PREG_SPLIT_NO_EMPTY);
        if ($tokens === false) {
            return $matchedText; // エラーの場合は元のまま
        }

        // 各トークンをクリーンアップ
        $tokens = array_map('trim', $tokens);
        $tokens = array_filter($tokens, fn($t) => !empty($t));

        // 最初の要素が空の場合（読点で始まる場合）を除去
        if (count($tokens) > 0 && empty($tokens[0])) {
            $tokens = array_slice($tokens, 1);
        }

        // 最低限のトークン数をチェック
        if (count($tokens) < $embeddedMinItems) {
            return $matchedText;
        }

        // キーワード的なトークンをフィルタリング
        $filtered = array_values(array_filter($tokens, fn($t) => self::isKeywordLike($t)));

        // キーワード的なトークンが指定された閾値以上でない場合は保持
        if (count($filtered) < count($tokens) * $threshold) {
            return $matchedText;
        }

        // keepFirst=0 なら全て削除（ただし「など」以降は保持）
        if ($keepFirst <= 0) {
            $cleanedText = trim($processingText, '、，, 　');
            if (!empty($cleanedText)) {
                $removedParts[] = $cleanedText;
            }

            // 「など」以降の部分を取得
            $suffixText = '';
            if (preg_match('/(など.*)/u', $matchedText, $suffixMatches)) {
                $suffixText = $suffixMatches[1];
            }

            return $suffixText;
        }

        // 指定数以下なら改変しない
        if (count($filtered) <= $keepFirst) {
            return $matchedText;
        }

        // 先頭 keepFirst 個だけ残して「etc…」で省略
        $removedKeywords = array_slice($filtered, $keepFirst);
        if (!empty($removedKeywords)) {
            $removedParts[] = implode('、', $removedKeywords);
        }

        // 「など」以降の部分を取得
        $suffixText = '';
        if (preg_match('/(など.*)/u', $matchedText, $suffixMatches)) {
            $suffixText = $suffixMatches[1];
        }

        // 元のテキストが読点で始まっているかチェック
        $startsWithComma = preg_match('/^[、，,]/', $matchedText);
        $startsWithPeriod = preg_match('/^[。！？]/', $matchedText);

        $result = '';
        if ($startsWithPeriod) {
            // 句点で始まっている場合は句点を除いて処理し、後で句点を追加
            $result = implode('、', array_slice($filtered, 0, $keepFirst)) . '、etc…';
        } elseif ($startsWithComma) {
            $result = '、' . implode('、', array_slice($filtered, 0, $keepFirst)) . '、etc…';
        } else {
            $result = implode('、', array_slice($filtered, 0, $keepFirst)) . '、etc…';
        }

        // 「など」以降の文章を追加
        return $result . $suffixText;
    }
}
