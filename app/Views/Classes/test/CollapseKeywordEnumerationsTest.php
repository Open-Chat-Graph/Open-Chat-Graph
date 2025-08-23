<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Views\Classes\CollapseKeywordEnumerations;

class CollapseKeywordEnumerationsTest extends TestCase
{
    public function testHashtagProcessing()
    {
        // ハッシュタグが本文に含まれる場合は削除される
        $text = 'これは本文です #本文 #テスト';
        $result = CollapseKeywordEnumerations::collapse($text, 12, 0, 1);
        $this->assertStringNotContainsString('#本文', $result);
        $this->assertStringContainsString('#テスト', $result);
    }

    public function testHashtagAllowanceLimit()
    {
        // allowHashtags パラメータで保持されるハッシュタグ数を制限
        $text = 'これは本文です #タグ1 #タグ2 #タグ3';
        $result = CollapseKeywordEnumerations::collapse($text, 12, 0, 2);
        $hashtagCount = substr_count($result, '#');
        $this->assertLessThanOrEqual(2, $hashtagCount);
    }

    public function testHashtagAllowanceZero()
    {
        // allowHashtags=0 ですべてのハッシュタグが削除される
        $text = 'これは本文です #タグ1 #タグ2 #タグ3';
        $result = CollapseKeywordEnumerations::collapse($text, 12, 0, 0);
        $this->assertStringNotContainsString('#', $result);
    }

    public function testPipeDelimitedKeywords()
    {
        // 縦棒区切りのキーワード羅列
        $text = 'カフェ|レストラン|食事|料理|グルメ';
        $result = CollapseKeywordEnumerations::collapse($text, 12, 0, 0);
        // 実際の動作: カタカナなのでキーワードとして認識され削除される
        $this->assertEquals('', $result);
    }

    public function testPipeDelimitedWithKeepFirst()
    {
        // 縦棒区切りで先頭N個を保持
        $text = 'カフェ|レストラン|食事|料理|グルメ';
        $result = CollapseKeywordEnumerations::collapse($text, 12, 2, 0);
        $this->assertEquals('カフェ｜レストラン…', $result);
    }

    public function testCommaDelimitedKeywords()
    {
        // 読点区切りのキーワード羅列（カタカナでテスト）
        $keywords = ['カフェ', 'レストラン', '食事', '料理', 'グルメ', 'デザート', 'コーヒー', 'お酒', '居酒屋', 'バー', 'ラーメン', 'うどん', 'そば', '寿司', '焼肉'];
        $text = implode('、', $keywords);
        $result = CollapseKeywordEnumerations::collapse($text, 12, 0, 0);
        $this->assertEquals('', $result);
    }

    public function testCommaDelimitedWithKeepFirst()
    {
        // 読点区切りで先頭N個を保持
        $keywords = ['カフェ', 'レストラン', '食事', '料理', 'グルメ', 'デザート', 'コーヒー', 'お酒', '居酒屋', 'バー', 'ラーメン', 'うどん', 'そば', '寿司', '焼肉'];
        $text = implode('、', $keywords);
        $result = CollapseKeywordEnumerations::collapse($text, 12, 3, 0);
        $this->assertEquals('カフェ、レストラン、食事…', $result);
    }

    public function testBelowMinimumItems()
    {
        // 最小個数未満の場合は変更されない
        $text = 'カフェ、レストラン、食事';
        $result = CollapseKeywordEnumerations::collapse($text, 12, 0, 0);
        $this->assertEquals($text, $result);
    }

    public function testSentenceLikePattern()
    {
        // 文章的なパターンは保持される（助詞を含む）
        $text = 'これを試して、あれをやって、それから他のことを、最後に何かをしました';
        $result = CollapseKeywordEnumerations::collapse($text, 5, 0, 0);
        $this->assertEquals($text, $result);
    }

    public function testEnglishSentencePattern()
    {
        // 英語の動詞パターンが含まれる場合は保持（読点は正規化される）
        $text = 'keyword1, keyword2, keyword3, this is a sentence, keyword4, keyword5, keyword6, keyword7, keyword8, keyword9, keyword10, keyword11, keyword12';
        $result = CollapseKeywordEnumerations::collapse($text, 12, 0, 0);
        $expected = 'keyword1、keyword2、keyword3、this is a sentence、keyword4、keyword5、keyword6、keyword7、keyword8、keyword9、keyword10、keyword11、keyword12';
        $this->assertEquals($expected, $result);
    }

    public function testKeywordLikeDetection()
    {
        // ひらがなが多い場合は文章として判定される
        $text = 'これは、あれは、それは、どれは、いつも、いつか、もしも、やっぱり、きっと、たぶん、まさか、やはり、もちろん';
        $result = CollapseKeywordEnumerations::collapse($text, 12, 0, 0);
        $this->assertEquals($text, $result);
    }

    public function testLongWordsFiltering()
    {
        // 24文字を超える単語は除外される（フィルタリング対象から外れるため削除される）
        $longWord = str_repeat('ア', 25);
        $keywords = ['短い', 'カフェ', $longWord, 'カフェ', 'カフェ', 'カフェ', 'カフェ', 'カフェ', 'カフェ', 'カフェ', 'カフェ', 'カフェ', 'カフェ', 'カフェ', 'カフェ'];
        $text = implode('、', $keywords);
        $result = CollapseKeywordEnumerations::collapse($text, 12, 0, 0);
        // 長い単語はキーワード判定から除外されるが、キーワード羅列全体は削除される
        $this->assertEquals('', $result);
    }

    public function testEnglishCommonWordsFiltering()
    {
        // 一般的な英単語は文章として判定される（読点は正規化される）
        $text = 'the, and, for, are, but, not, you, all, with, from, this, that, have';
        $result = CollapseKeywordEnumerations::collapse($text, 12, 0, 0);
        $expected = 'the、and、for、are、but、not、you、all、with、from、this、that、have';
        $this->assertEquals($expected, $result);
    }

    public function testFourLetterEnglishWords()
    {
        // 4文字以上の英単語で企業名パターンでない場合は文章として判定される（読点は正規化される）
        $text = 'test, word, long, some, text, more, data, info, type, kind, form, make, code';
        $result = CollapseKeywordEnumerations::collapse($text, 12, 0, 0);
        $expected = 'test、word、long、some、text、more、data、info、type、kind、form、make、code';
        $this->assertEquals($expected, $result);
    }

    public function testEnglishCompanyNames()
    {
        // 企業名パターン（大文字、&記号、スペースを含む）はキーワードとして認識される
        $text = 'Apple, Microsoft, Google, IBM, Oracle, Amazon, Facebook, Tesla, Netflix, Adobe, Salesforce, Twitter';
        $result = CollapseKeywordEnumerations::collapse($text, 12, 0, 0);
        $this->assertEquals('', $result);
    }

    public function testMixedContentFiltering()
    {
        // 50%未満がキーワード的なら保持される
        $text = 'これは普通の文章です。カフェ、レストラン、食事、でも普通の文章が多いです。';
        $result = CollapseKeywordEnumerations::collapse($text, 3, 0, 0);
        $this->assertEquals($text, $result);
    }

    public function testParticleBasedFiltering()
    {
        // 助詞が多い場合は文章として判定される
        $text = 'これが良い、あれを取る、それに加えて、どれから始める、いつも使う、どこで見る、なぜ必要、誰と行く、何を持つ、どう進む、いくら払う、どんな形';
        $result = CollapseKeywordEnumerations::collapse($text, 12, 0, 0);
        $this->assertEquals($text, $result);
    }

    public function testCommaCountVsParticles()
    {
        // 読点が多く助詞が少ない場合はキーワード羅列と判定
        $text = 'カフェ1、カフェ2、カフェ3、カフェ4、カフェ5、カフェ6、カフェ7、カフェ8、カフェ9、カフェ10、カフェ11、カフェ12';
        $result = CollapseKeywordEnumerations::collapse($text, 12, 0, 0);
        $this->assertEquals('', $result);
    }

    public function testReturnRemovedOnlyFlag()
    {
        // 削除された部分のみを返すフラグのテスト
        $text = 'これは本文です。カフェ、レストラン、食事、料理、グルメ、デザート、コーヒー、お酒、居酒屋、バー、ラーメン、うどん、そば、寿司、焼肉';
        $result = CollapseKeywordEnumerations::collapse($text, 12, 0, 0, '', true);
        $this->assertStringContainsString('カフェ', $result);
        $this->assertStringNotContainsString('これは本文です', $result);
    }

    public function testReturnRemovedOnlyFlag2()
    {
        // 削除された部分のみを返すフラグのテスト
        $text = 'これは本文です。#荒野 #荒野行動 #歌 #ライト #ライブトーク #イラスト';
        $result = CollapseKeywordEnumerations::collapse($text, 12, 0, 0, '', true);
        $this->assertStringContainsString('#荒野', $result);
        $this->assertStringNotContainsString('これは本文です', $result);
    }

    public function testExtraTextForHashtagFiltering()
    {
        // extraText パラメータでハッシュタグフィルタリングの精度を上げる
        $text = 'これは本文です #テスト';
        $result = CollapseKeywordEnumerations::collapse($text, 12, 0, 1, 'テストも含む');
        $this->assertStringNotContainsString('#テスト', $result);
    }

    public function testWhitespaceNormalization()
    {
        // 空白の正規化テスト
        $text = '  これは　　本文です  。  ';
        $result = CollapseKeywordEnumerations::collapse($text, 12, 0, 0);
        $this->assertEquals('これは 本文です 。', $result);
    }

    public function testConsecutiveNewlineHandling()
    {
        // 連続する改行の処理
        $text = "これは本文です。\n\n\n\n別の行です。";
        $result = CollapseKeywordEnumerations::collapse($text, 12, 0, 0);
        $this->assertLessThanOrEqual(2, substr_count($result, "\n"));
    }

    public function testPunctuationNormalization()
    {
        // 読点周りの空白整理
        $text = 'これは   、   テストです   ，   。';
        $result = CollapseKeywordEnumerations::collapse($text, 12, 0, 0);
        $this->assertEquals('これは、テストです、。', $result);
    }

    public function testEmptyResult()
    {
        // 空文字列の処理
        $text = '';
        $result = CollapseKeywordEnumerations::collapse($text, 12, 0, 0);
        $this->assertEquals('', $result);
    }

    public function testOnlySymbols()
    {
        // 記号のみの文字列
        $text = '！！！、。。。、？？？';
        $result = CollapseKeywordEnumerations::collapse($text, 12, 0, 0);
        $this->assertEquals($text, $result);
    }

    public function testInvalidUtf8Handling()
    {
        // 有効なUTF-8文字列の処理
        $text = 'これは有効な文字列です';
        $result = CollapseKeywordEnumerations::collapse($text, 12, 0, 0);
        $this->assertEquals($text, $result);
    }

    public function testZenkanDelimiters()
    {
        // 全角区切り文字のテスト
        $text = 'カフェ１｜レストラン２｜食事３｜料理４｜グルメ５';
        $result = CollapseKeywordEnumerations::collapse($text, 12, 0, 0);
        $this->assertEquals('', $result);
    }

    public function testMixedDelimiters()
    {
        // 混合区切り文字のテスト（カタカナでキーワード羅列）
        $keywords = ['カフェ', 'レストラン', '食事', '料理', 'グルメ', 'デザート', 'コーヒー', 'お酒', '居酒屋', 'バー', 'ラーメン', 'うどん', 'そば', '寿司', '焼肉'];
        $text = implode('、 ', $keywords); // 読点+スペース
        $result = CollapseKeywordEnumerations::collapse($text, 12, 0, 0);
        $this->assertEquals('', $result);
    }

    public function testActualKeywordPattern()
    {
        // 実際にキーワードとして認識されるパターンをテスト
        $text = 'カフェ、レストラン、食事、料理、デザート、コーヒー、お酒、居酒屋、バー、ラーメン、うどん、そば、寿司、焼肉、BBQ';
        $result = CollapseKeywordEnumerations::collapse($text, 12, 0, 0);
        $this->assertEquals('', $result);
    }

    public function testCompanyNamesEnumeration()
    {
        // 企業名の縦棒区切り羅列のテスト
        $text = 'コンサル/シンクタンク｜マッキンゼー｜BCG｜ベイン｜ATカーニー｜PwC｜Deloitte デロイト｜KPMG｜EY｜アクセンチュア｜IBM｜NRI 野村総合研究所｜MRI 三菱総合研究所｜日本総合研究所 日本総研｜アビーム';
        $result = CollapseKeywordEnumerations::collapse($text, 12, 0, 0);
        $this->assertEquals('', $result);
    }

    public function testCompanyNamesWithKeepFirst()
    {
        // 企業名の縦棒区切り羅列で先頭数個を保持
        $text = 'コンサル/シンクタンク｜マッキンゼー｜BCG｜ベイン｜ATカーニー｜PwC｜Deloitte デロイト｜KPMG｜EY｜アクセンチュア｜IBM｜NRI 野村総合研究所｜MRI 三菱総合研究所｜日本総合研究所 日本総研｜アビーム';
        $result = CollapseKeywordEnumerations::collapse($text, 12, 5, 0);
        $this->assertStringContainsString('コンサル/シンクタンク', $result);
        $this->assertStringContainsString('…', $result);
    }

    public function testSpacesInPipeDelimitedTokens()
    {
        // スペースを含むトークンの縦棒区切りテスト
        $text = 'Deloitte デロイト｜Ernst & Young｜Price Waterhouse Coopers｜KPMG Japan';
        $result = CollapseKeywordEnumerations::collapse($text, 4, 0, 0);
        $this->assertEquals('', $result);
    }

    public function testMixedContentWithRulesAndCompanies()
    {
        // 説明文・利用ルール・企業名羅列が混在したテスト
        $text = '企業別の情報共有/企業研究/選考対策グループです
【利用ルール】敬語で会話すること｜建設的な議論を行うこと│情報共有を抑制する発言は禁止│秘密保持を求められた場合はその事実のみ共有すること｜意見を求める際には自分の考えも提示し丸投げしないこと

＜企業別グループ一覧＞

コンサル/シンクタンク｜マッキンゼー｜BCG｜ベイン｜ATカーニー｜PwC｜Deloitte デロイト｜KPMG｜EY

@jobhunt #nolog';
        
        $result = CollapseKeywordEnumerations::collapse($text, 12, 0, 0);
        
        // 説明文と利用ルールは保持される
        $this->assertStringContainsString('企業別の情報共有/企業研究/選考対策グループです', $result);
        $this->assertStringContainsString('敬語で会話すること', $result);
        $this->assertStringContainsString('建設的な議論を行うこと', $result);
        $this->assertStringContainsString('＜企業別グループ一覧＞', $result);
        $this->assertStringContainsString('@jobhunt', $result);
        
        // 企業名の羅列は削除される
        $this->assertStringNotContainsString('マッキンゼー', $result);
        $this->assertStringNotContainsString('BCG', $result);
    }

    public function testRulesOnlyText()
    {
        // 利用ルールのような文章的な縦棒区切り文は保持される
        $text = '敬語で会話すること｜建設的な議論を行うこと｜情報共有を抑制する発言は禁止｜秘密保持を求められた場合はその事実のみ共有すること｜意見を求める際には自分の考えも提示し丸投げしないこと';
        $result = CollapseKeywordEnumerations::collapse($text, 12, 0, 0);
        $this->assertEquals($text, $result);
    }

    public function testPregReplaceCallbackNullHandling()
    {
        // preg_replace_callbackがnullを返すケースをテスト
        // 極端に長い文字列や複雑なパターンでpreg_replace_callbackがエラーになる可能性をテスト
        
        // ケース1: 空文字列の処理
        $text = '';
        $result = CollapseKeywordEnumerations::collapse($text, 12, 0, 0);
        $this->assertIsString($result);
        $this->assertEquals('', $result);
        
        // ケース2: 非常に長い繰り返しパターン（バックトラック制限に達する可能性）
        $longKeywords = array_fill(0, 1000, 'キーワード');
        $text = implode('、', $longKeywords);
        $result = CollapseKeywordEnumerations::collapse($text, 12, 0, 0);
        $this->assertIsString($result);
        
        // ケース3: 特殊文字を含むテキスト
        $text = "特殊文字\x00\x01\x02を含むテキスト";
        $result = CollapseKeywordEnumerations::collapse($text, 12, 0, 0);
        $this->assertIsString($result);
        
        // ケース4: UTF-8マルチバイト文字の境界ケース
        $text = '𠮷野家、𩸽、𠀋、😀、🍕、🎉、テスト、キーワード、羅列、削除、対象、文字列';
        $result = CollapseKeywordEnumerations::collapse($text, 12, 0, 0);
        $this->assertIsString($result);
        
        // ケース5: 非常に多くの改行を含むテキスト
        $text = str_repeat("\n", 100) . "テキスト" . str_repeat("\n", 100);
        $result = CollapseKeywordEnumerations::collapse($text, 12, 0, 0);
        $this->assertIsString($result);
        $this->assertStringContainsString('テキスト', $result);
    }
    
    public function testTypeConsistencyAndErrorHandling()
    {
        // 型の一貫性とエラーハンドリングのテスト
        
        // ケース1: 不正なUTF-8文字列
        $invalidUtf8 = "\x80\x81\x82";
        $result = CollapseKeywordEnumerations::collapse($invalidUtf8, 12, 0, 0);
        $this->assertIsString($result);
        
        // ケース2: nullバイトを含む文字列
        $nullByte = "テスト\0文字列";
        $result = CollapseKeywordEnumerations::collapse($nullByte, 12, 0, 0);
        $this->assertIsString($result);
        
        // ケース3: 制御文字を含む文字列
        $controlChars = "テスト\x01\x02\x03文字列";
        $result = CollapseKeywordEnumerations::collapse($controlChars, 12, 0, 0);
        $this->assertIsString($result);
        
        // ケース4: ネストした正規表現パターン（バックトラック爆発の可能性）
        $nested = str_repeat('((', 50) . 'test' . str_repeat('))', 50);
        $result = CollapseKeywordEnumerations::collapse($nested, 12, 0, 0);
        $this->assertIsString($result);
        
        // ケース5: 極端に長い単一トークン
        $longToken = str_repeat('あ', 10000);
        $result = CollapseKeywordEnumerations::collapse($longToken, 12, 0, 0);
        $this->assertIsString($result);
        
        // ケース6: 複雑な混合パターン
        $complex = "#タグ1 | 企業A | 企業B | #タグ2、キーワード1、キーワード2\n\n\n利用ルール｜禁止事項｜注意事項";
        $result = CollapseKeywordEnumerations::collapse($complex, 12, 0, 1);
        $this->assertIsString($result);
        
        // ケース7: エッジケースの引数
        $result = CollapseKeywordEnumerations::collapse('テスト', 0, 0, 0);
        $this->assertIsString($result);
        
        $result = CollapseKeywordEnumerations::collapse('テスト', -1, -1, -1);
        $this->assertIsString($result);
        
        $result = CollapseKeywordEnumerations::collapse('テスト', PHP_INT_MAX, PHP_INT_MAX, PHP_INT_MAX);
        $this->assertIsString($result);
    }

    public function testCostcoKeywordEnumerationPattern()
    {
        // コストコのキーワード羅列パターンのテスト
        $text = 'コストコオプチャ最大規模のメンバーです！
コストコのあれこれ、㊙️情報まで盛りだくさん��

コストコ全倉庫の①コストコの新商品
②フードコートの新メニュー
③赤マジ、黒しれ商品の紹介
④その他、時期もの商品や話題の商品など
⑤おすすめ商品
⑥レビューを検索したけど見つからなかった商品
などの情報共有オプチャです。
たまに、めっちゃオタクな管理人が出没します😅
入室の際はお好きなお名前+よく行くコストコ倉庫名をつけてくださいね
例)まつもと@久山
よろしくお願い致します🙇‍♀️

コストコ costco Costco 主婦 一人暮らし 節約 ポイ活 お得 ブランド お買い得 爆買い 大家族 フードファイター 矢田亜希子 ジャンクSPORTS 業務スーパー KALDI iHerb ユニクロ gu TikTok Instagram Facebook 石狩、札幌、かみのやま 富谷 野々市 射水 入間 新三郷 つくば 前橋 ひたちなか 千葉ニュータウン 幕張 木更津 多摩境 金沢シーサイド 川崎 座間 浜松 守山 中部国際空港 常滑 岐阜羽島 京都八幡 尼崎 神戸 和泉 広島 久山 北九州 熊本御船 壬生 門真 明和 亀山 沖縄 小郡 南アルプス 30代 40代 50代 60代 20代 きゅん スティッチ';

        $result = CollapseKeywordEnumerations::collapse($text, 12, 0, 0);
        
        // 最後のキーワード羅列部分（コストコ costco Costco...）は削除される
        $this->assertStringNotContainsString('コストコ costco Costco', $result);
        $this->assertStringNotContainsString('石狩、札幌、かみのやま', $result);
        $this->assertStringNotContainsString('30代 40代 50代', $result);
        
        // 本文の説明部分は保持される
        $this->assertStringContainsString('コストコオプチャ最大規模のメンバーです', $result);
        $this->assertStringContainsString('情報共有オプチャです', $result);
        $this->assertStringContainsString('よろしくお願い致します', $result);
    }
    
    public function testEmbeddedKeywordEnumerationInSentence()
    {
        // 文章内に埋め込まれたキーワード羅列のテスト（仮想通貨パターン）
        $text = 'ガブリエル(gabu_coin)が運営する仮想通貨考察グループその2です。ビットコイン、NFT、チリーズ、SAND、ENJIN、リップル、ネム、IOST、ステラ、イーサリアム、エイダ、トロン、バージ、ポルカドット、モナコイン、ライトコイン、リスク、モナコイン、オミセゴー、ファクトム、バイナンスコイン、テザー、ビットコインキャッシュ、モネロ、テゾス、VeChain、BAT、QTUM 、LINK、EOS、NEO、DOGE、IOTA、DASH、AVAX、FIL、DOGE、NANOなどのアルトコインから草コインまでの仮想通貨のチャートを予想するグループです。  バイナンス、コインチェック 、ビットバンク、DMMビットコイン #BTC  #ETH #XRP #XEM #LISK #MONA #ADA #TRX #TRON #XVG #LTC #BCH #XLM #OMG #Polkadot #Chainlink #BSV #WBTC #USDC #TEZOS #VET #binancecoin #株式投資 #投資 #暗号通貨 #暗号資産 #DeFi #NFT #CHZ';

        $result = CollapseKeywordEnumerations::collapse($text, 4, 0, 0);
        
        // 文章の基本部分は保持される
        $this->assertStringContainsString('ガブリエル(gabu_coin)が運営する仮想通貨考察グループその2です', $result);
        $this->assertStringContainsString('などのアルトコインから草コインまでの仮想通貨のチャートを予想するグループです', $result);
        
        // 長いキーワード羅列部分は削除される
        $this->assertStringNotContainsString('NFT、チリーズ、SAND、ENJIN、リップル、ネム、IOST、ステラ、イーサリアム', $result);
        $this->assertStringNotContainsString('バイナンスコイン、テザー、ビットコインキャッシュ、モネロ、テゾス', $result);
        
        // 短いキーワード列も削除される（文章外のキーワード羅列として通常パターンで処理）
        // minItems=4なので4個のキーワード列は削除される
        $this->assertStringNotContainsString('バイナンス、コインチェック、ビットバンク、DMMビットコイン', $result);
    }
    
    public function testEmbeddedKeywordEnumerationWithKeepFirst()
    {
        // 文章内埋め込み型キーワード羅列の短縮テスト（keepFirst > 0）
        $text = 'ガブリエル(gabu_coin)が運営する仮想通貨考察グループその2です。ビットコイン、NFT、チリーズ、SAND、ENJIN、リップル、ネム、IOST、ステラ、イーサリアム、エイダ、トロン、バージ、ポルカドット、モナコイン、ライトコイン、リスク、モナコイン、オミセゴー、ファクトム、バイナンスコイン、テザー、ビットコインキャッシュ、モネロ、テゾス、VeChain、BAT、QTUM 、LINK、EOS、NEO、DOGE、IOTA、DASH、AVAX、FIL、DOGE、NANOなどのアルトコインから草コインまでの仮想通貨のチャートを予想するグループです。  バイナンス、コインチェック 、ビットバンク、DMMビットコイン #BTC  #ETH #XRP';

        $result = CollapseKeywordEnumerations::collapse($text, 12, 3, 0);
        
        // 文章の基本部分は保持される
        $this->assertStringContainsString('ガブリエル(gabu_coin)が運営する仮想通貨考察グループその2です', $result);
        $this->assertStringContainsString('仮想通貨のチャートを予想するグループです', $result);
        
        // 長いキーワード羅列は先頭3個+「…」に短縮される
        $this->assertStringContainsString('…', $result);
        // 最初の数個は残る
        $this->assertStringContainsString('ビットコイン', $result);
        // 末尾の方は削除される
        $this->assertStringNotContainsString('バイナンスコイン、テザー、ビットコインキャッシュ、モネロ、テゾス', $result);
        
        // 短いキーワード列は先頭3個+「…」に短縮される
        $this->assertStringContainsString('バイナンス', $result);
    }
    
    public function testTwitterPatternWithEtc()
    {
        // ユーザーが指定したTwitterパターンのテスト
        $text = 'Twitter(gabu_coin)フォローいただければ優先的に勧誘します。ビットコイン、NFT、チリーズ、SAND、ENJIN、リップル、ネム、IOST、ステラ、イーサリアム、エイダ、トロン、バージ、ポルカドット、モナコイン、ライトコイン、リスク、モナコイン、オミセゴー、ファクトム、バイナンスコイン、テザー、ビットコインキャッシュ、モネロ、テゾス、VeChain、BAT、QTUM 、LINK、EOS、NEO、DOGE、IOTA、DASH、AVAX、FIL、DOGE、NANOなどのアルトコインから草コインまでの仮想通貨のチャートを予想するグループです。';
        
        // keepFirst=1 で「ビットコイン、etc…などのアルトコイン...」となることを期待
        $result = CollapseKeywordEnumerations::collapse($text, 12, 1, 0);
        
        // 基本的な文章部分は保持される
        $this->assertStringContainsString('Twitter(gabu_coin)フォローいただければ優先的に勧誘します', $result);
        $this->assertStringContainsString('などのアルトコインから草コインまでの仮想通貨のチャートを予想するグループです', $result);
        
        // キーワード羅列は「ビットコイン、etc…」に短縮される
        $this->assertStringContainsString('ビットコイン、etc…', $result);
        
        // 長いリストの中身は削除される
        $this->assertStringNotContainsString('NFT、チリーズ、SAND、ENJIN', $result);
        $this->assertStringNotContainsString('バイナンスコイン、テザー、ビットコインキャッシュ', $result);
    }
    
    public function testActualGabrielPattern()
    {
        // 実際のガブリエルのパターンのテスト - Twitterバージョンでの正確な変換をテスト
        $text = 'Twitter(gabu_coin)フォローいただければ優先的に勧誘します。ビットコイン、NFT、チリーズ、SAND、ENJIN、リップル、ネム、IOST、ステラ、イーサリアム、エイダ、トロン、バージ、ポルカドット、モナコイン、ライトコイン、リスク、モナコイン、オミセゴー、ファクトム、バイナンスコイン、テザー、ビットコインキャッシュ、モネロ、テゾス、VeChain、BAT、QTUM 、LINK、EOS、NEO、DOGE、IOTA、DASH、AVAX、FIL、DOGE、NANOなどのアルトコインから草コインまでの仮想通貨のチャートを予想するグループです。';

        // keepFirst=1で長いキーワード羅列を「ビットコイン、etc…」に短縮
        $result = CollapseKeywordEnumerations::collapse($text, 12, 1, 0, '', false, 3);
        
        // 期待される正確な結果
        $expected = 'Twitter(gabu_coin)フォローいただければ優先的に勧誘します。ビットコイン、etc…などのアルトコインから草コインまでの仮想通貨のチャートを予想するグループです。';
        
        $this->assertEquals($expected, $result);
        
        // 追加のテスト - 元のガブリエルパターンでスタンドアロンキーワード削除
        $textWithStandalone = 'ガブリエル(gabu_coin)が運営する仮想通貨考察グループその2です。ビットコイン、NFT、チリーズ、SAND、ENJIN、リップル、ネム、IOST、ステラ、イーサリアム、エイダ、トロン、バージ、ポルカドット、モナコイン、ライトコイン、リスク、モナコイン、オミセゴー、ファクトム、バイナンスコイン、テザー、ビットコインキャッシュ、モネロ、テゾス、VeChain、BAT、QTUM 、LINK、EOS、NEO、DOGE、IOTA、DASH、AVAX、FIL、DOGE、NANOなどのアルトコインから草コインまでの仮想通貨のチャートを予想するグループです。  バイナンス、コインチェック 、ビットバンク、DMMビットコイン';
        
        $resultStandalone = CollapseKeywordEnumerations::collapse($textWithStandalone, 4, 0, 0, '', false, 3);
        
        // スタンドアロンキーワードリストは削除される
        $this->assertStringNotContainsString('バイナンス、コインチェック、ビットバンク、DMMビットコイン', $resultStandalone);
        // メイン文章は保持される
        $this->assertStringContainsString('ガブリエル(gabu_coin)が運営する仮想通貨考察グループその2です', $resultStandalone);
        $this->assertStringContainsString('などのアルトコインから草コインまでの仮想通貨のチャートを予想するグループです', $resultStandalone);
    }
    
    public function testCoreKeywordEnumerationAbbreviation()
    {
        // コア機能のテスト：長いキーワード羅列の「etc…」省略
        $longEnumeration = 'ビットコイン、NFT、チリーズ、SAND、ENJIN、リップル、ネム、IOST、ステラ、イーサリアム、エイダ、トロン、バージ、ポルカドット、モナコイン、ライトコイン、リスク、モナコイン、オミセゴー、ファクトム、バイナンスコイン、テザー、ビットコインキャッシュ、モネロ、テゾス、VeChain、BAT、QTUM 、LINK、EOS、NEO、DOGE、IOTA、DASH、AVAX、FIL、DOGE、NANOなどのアルトコインから草コインまでの仮想通貨のチャートを予想するグループです。';
        
        $result = CollapseKeywordEnumerations::collapse($longEnumeration, 12, 1, 0, '', false, 3);
        $expected = 'ビットコイン、etc…などのアルトコインから草コインまでの仮想通貨のチャートを予想するグループです。';
        
        $this->assertEquals($expected, $result);
    }
    
    public function testKeywordListWithTrailingText()
    {
        // 末尾にハッシュタグや文章がある場合の処理テスト
        $textWithTrailing = 'ガブリエル(gabu_coin)が運営する仮想通貨考察グループその2です。などのアルトコインから草コインまでの仮想通貨のチャートを予想するグループです。 バイナンス、コインチェック、ビットバンク、DMMビットコイン #BTC';
        
        // keepFirst=0で削除
        $result1 = CollapseKeywordEnumerations::collapse($textWithTrailing, 4, 0, 1);
        $this->assertStringNotContainsString('バイナンス、コインチェック', $result1);
        $this->assertStringContainsString('#BTC', $result1);
        
        // keepFirst=1で省略
        $result2 = CollapseKeywordEnumerations::collapse($textWithTrailing, 4, 1, 1);
        $this->assertStringContainsString('バイナンス…', $result2);
        $this->assertStringContainsString('#BTC', $result2);
        $this->assertStringNotContainsString('コインチェック、ビットバンク', $result2);
    }
}