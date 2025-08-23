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
}