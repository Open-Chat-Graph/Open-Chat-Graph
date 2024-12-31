<?php

declare(strict_types=1);

namespace App\Services\Recommend;

use App\Config\AppConfig;
use App\Services\OpenChat\Utility\OpenChatServicesUtility;
use App\Models\Repositories\DB;

class RecommendUpdater
{
    const BEFORE_CATEGORY_NAME = [
        "17" => [
            ["Sky 星を紡ぐ子どもたち", ["sky"]],
            ["カーパーキング", ["カーパ"]],
            ["荒野行動", ["荒野"]],
            ["ハイキュー!!FLY HIGH（ハイキューフライハイ／ハイフラ）", ["ハイフラ_OR_ハイキュー"]],
            ["ポケットモンスター（ポケモン）", ["ポケモン大好きチャット"]],
            ["ポケポケ（Pokémon TCG Pocket／ポケモンカード アプリ）", ["ポケポケ"]],
            ["スプラトゥーン", ["スプラ"]],
        ],
        "41" => [
            ["イラスト等の依頼", ["依頼"]],
        ],
        "5" => [
            ["片付け・断捨離", ["片付け", "断捨離", "部屋_AND_掃除"]],
            ["不用品・遺品整理・汚部屋・ゴミ屋敷", ["不用品_AND_回収", "遺品整理", "特殊清掃", "ゴミ屋敷", "汚部屋"]],

        ],
        "8" => [
            ["不用品・遺品整理・汚部屋・ゴミ屋敷", ["不用品_AND_回収", "遺品整理", "特殊清掃", "ゴミ屋敷", "汚部屋"]],
        ],
        "28" => [
            ["子供服 フリマ", ["子供服_AND_フリマ"]],
        ],
        "26" => [
            ["PRODUCE 101 JAPAN THE GIRLS（日プ女子）", ["日プガールズ_OR_日プ女子_OR_ME:I_OR_日プ3_OR_ME：I", "PRODUCE_AND_101_AND_JAPAN_AND_THE_AND_GIRLS"]],
            ["PRODUCE 101 JAPAN（日プ）", ["PRODUCE_AND_101_AND_JAPAN"]],
            ["SEVENTEEN", ["CARAT_OR_セブチ"]],
            "utfbin_Ado",
        ],
        "22" => [
            ["名探偵コナン", ["コナン"]],
            ["ワンピースカード", ["ワンピース_AND_カード", "ONE_AND_PIECE_AND_カード", "ワンピース_AND_販売情報"]],
            ["ONE PIECE（ワンピース）", ["ワンピース", "ONE_AND_PIECE"]],
        ],
        "12" => [
            "スタバ"
        ],
        "33" => [
            "フェス",
            "utfbin_GLAY",
            "utfbin_Ado",
            ["SEVENTEEN", ["CARAT_OR_セブチ"]],
        ],
        "11" => [
            ["翻訳", ["LINE_AND_語", "翻訳", "LINE_AND_通訳"]],
        ],
        "40" => [
            ["FX", ["utfbin_EA_OR_FX"]],
        ]
    ];

    const NAME_STRONG_TAG = [
        "カブアンド",
        ["パソコン・ガジェット", ["自作PC_OR_ゲーミングPC_OR_デジタルガジェット", "スマホ_AND_ガジェット", "イヤホン_AND_ガジェット"]],
        ["かまいたち", ["MUSiC_AND_KAMMER", "かまいたち"]],
        "下ネタ_OR_下あり_OR_下○タ_OR_下系",
        "ネッ友_OR_ネ友",
        "MBTI_OR_ISTJ_OR_ISFJ_OR_INFJ_OR_INTJ_OR_ISTP_OR_ISFP_OR_INFP_OR_INTP_OR_ESTP_OR_ESFP_OR_ENFP_OR_ENTP_OR_ESTJ_OR_ESFJ_OR_ENFJ_OR_ENTJ",
        ["LGBT", ["ゲイ_AND_バイ", "同性愛_OR_LGBT_OR_ゲイ学生_OR_Xジェンダー_OR_トランスジェンダー_OR_セクマイ_OR_ノンセク_OR_レズビアン"]],
        "IT資格_OR_基本情報技術者_OR_応用情報技術者_OR_ITパスポート_OR_情報処理試験_OR_ITストラテジ",
        ["大学新入生同士の情報交換", ["新入生同士の情報交換_OR_年度入学_OR_度新入生_OR_新入生同士"]],
        ["大学 新入生", ["春から_AND_大学", "新入生_AND_大学"]],
        ["就活生情報・選考対策・企業研究", ["就活情報_OR_就活生向け情報_OR_就活生情報_OR_選考対策・企業研究_OR_就活選考対策_OR_就活対策グループ_OR_選考対策グループ_OR_就活テスト対策グループ_OR_志望者向けグループ_OR_業界志望者向け_OR_就活の情報_OR_就活会議_OR_就活生向け_OR_就活対策用_OR_就活生の情報交換_OR_unistyle_OR_就活の情報共有", "大学生_AND_就活_AND_卒"]],
        ["生成AI・ChatGPT", ["ChatGPT_OR_チャットGPT_OR_LLM_OR_Claude_OR_Copilot", "Gemini_AND_AI"]],
        ["画像生成AI・AIイラスト", ["AIイラスト_OR_ばりぐっどくん_OR_AI絵画_OR_AI絵師", "画像_AND_生成_AND_AI"]],
        ["ポイ活", ["ポイ活_AND_ポイカツ"]],
        ["WEBデザイナー・デザイン", ["WEBデザイナー_OR_WEBデザイン"]],
        "フリーランス",
        "猫ミーム",
        ["鉄道", ["電車_OR_鉄道_OR_撮り鉄_OR_プラレール_OR_列車_OR_乗り鉄_OR_近鉄_OR_スジ鉄"]],
        ["全国 雑談", ["全国_AND_オプチャ", "全国_AND_雑談"]],
        ["ケアマネージャー（ケアマネ）", ["ケアマネージャー_OR_ケアマネ"]],
        "BLTトレードシステムサポートオプチャ",
        "Crypto_AND_Academy",
        ["モンスターストライク（モンスト）", ["モンスト"]],
        ["地雷系", ["地雷系_OR_量産型_OR_ぴえん系", "地雷_AND_量産"]],
        ["片目界隈・自撮り界隈", ["片目界隈_OR_自撮り界隈"]],
        ["偽カップル（偽カプ）", ["偽カップル_OR_偽カプ"]],
        ["もこう（馬場豊）", ["馬場豊_OR_もこう_OR_ライバロリ_OR_原田直希_OR_おにや"]],
        "加藤純一_OR_衛門",
        "スピリチュアル",
        "ナイチンゲールダンス",
        "占い師",
        "占い_OR_霊視_OR_占術",
        ["ボイメで歌（歌リレー）", ["ボイメ_AND_歌", "歌リレー", "歌王国"]],
        "ヒカマニ_OR_ヒカキンマニア",
        "LAST WAR_OR_lastwar",
        "日常組",
        "防災_OR_災害",
        "マジックカード",
        "イケボ",
        "独り言",
        "ミニ四駆",
        "ガンプラ",
        "ガンダム",
        ["イナズマイレブン（イナイレ）", ["イナズマイレブン_OR_イナイレ"]],
        "吃音",
        "カラオケ",
        "セミナー",
        ["モンスターハンター（モンハン）", ["モンハン_OR_モンスターハンター"]],
        ["アイドリッシュセブン（アイナナ）", ["アイドリッシュセブン_OR_アイナナ"]],
        "SNS",
        ["Instagram（インスタ）", ["インスタ_OR_Instagram"]],
        ["妖怪ウォッチ ぷにぷに", ["ぷにぷに"]],
        "メンヘラ",
        ["夢絵・夢関連", ["夢絵_OR_夢関連"]],
        "いれいす_OR_いれりす",
        ["カラフルピーチ（からぴち）", ["カラフルピーチ_OR_からぴち_OR_カラピチ_OR_からピチ"]],
        "すたぽら",
        ["東京リベンジャーズ（東リベ）", ["東京リベンジャーズ_OR_東リベ_OR_東リべ"]],
        ["莉犬くん", ["莉犬"]],
        "すとぷり",
        "シクフォニ",
        ["原神なりきり", ["原神_AND_なりきり", "原神_AND_也", "原神_AND_nrkr"]],
        "原神",
        "ポーランドボール",
        ["AMPTAKxCOLORS（アンプタックカラーズ）", ["AMPTAK_OR_アンプタック"]],
        ["ZB1（ゼロベースワン／ゼベワン）", ["ZEROBASEONE_OR_ゼベワン_OR_ゼロベースワン_OR_ZB1"]],
        "コレコレ_OR_コレリス",
        "推しの子",
        "ちろぴの",
        ["プロセカなりきり", ["プロセカ_AND_なりきり", "プロセカ_AND_也", "プロセカ_AND_nrkr", "ぷせゆる", "プセゆる"]],
        ["プロジェクトセカイ（プロセカ）", ["プロセカ"]],
        ["あんスタなりきり", ["あんスタ_AND_CHAOS", "あんスタ_AND_カオス", "あんスタ_AND_なりきり", "あんスタ_AND_也", "enst_AND_なりきり", "enst_AND_也", "あんスタ_AND_nrkr", "あんスタ_AND_ゆるなり", "あんすた_AND_ゆるなり", "あんスタ_AND_固定"]],
        ["あんさんぶるスターズ!（あんスタ）", ["あんスタ_OR_あんさんぶるスターズ_OR_enst"]],
        ["マッシュルなりきり", ["マッシュル_AND_なりきり", "マッシュル_AND_也", "マッシュル_AND_nrkr"]],
        "マッシュル",
        ["にじさんじなりきり", ["にじさんじ_AND_なりきり", "にじさんじ_AND_也", "にじさんじ_AND_nrkr", "2434_AND_也", "🌈🕒_AND_也"]],
        "にじさんじ",
        ["ハイキューなりきり", ["ハイキュー_AND_なりきり", "ハイキュー_AND_緩", "ハイキュー_AND_也", "ハイキュー_AND_nrkr", "HQ_AND_なりきり", "HQ_AND_也", "HQ_AND_nrkr", "ＨＱ_AND_也"]],
        ["ハイキュー!!", ["ハイキュー"]],
        ["呪術廻戦なりきり", ["呪術廻戦_AND_なりきり", "呪術廻戦_AND_也", "呪術廻戦_AND_nrkr"]],
        "呪術廻戦",
        ["文ストなりきり", ["文スト_AND_なりきり", "文スト_AND_也", "文スト_AND_nrkr", "文スト_AND_ゆるなり", "文スト_AND_固定", "文ｽﾄ_AND_なりきり"]],
        ["文豪ストレイドッグス（文スト）", ["文豪ストレイドッグス_OR_文スト"]],
        ["転生したらスライムだった件（転スラ）", ["転生したらスライムだった件_OR_転スラ"]],
        ["ヒプノシスマイク（ヒプマイ）", ["ヒプノシスマイク_OR_ヒプマイ"]],
        ["鬼滅の刃なりきり", ["鬼滅_AND_なりきり", "鬼滅_AND_也", "鬼滅_AND_nrkr"]],
        "鬼滅の刃",
        "ホロライブ",
        "カーパーキング",
        ["名探偵コナン", ["名探偵コナン"]],
        ["ヒロアカなりきり", ["ヒロアカ_AND_なりきり", "ヒロアカ_AND_也", "ヒロアカ_AND_nrkr"]],
        ["僕のヒーローアカデミア（ヒロアカ）", ["僕のヒーローアカデミア_OR_ヒロアカ"]],
        ["東方Project", ["東方"]],
        "対荒らし_OR_対荒_OR_白夜総会_OR_ヤブ医者_OR_拓也集落_OR_植民地",
        ["ツイステなりきり", ["ツイステ_AND_なりきり", "ツイステ_AND_也", "ツイステ_AND_nrkr"]],
        ["ツイステッドワンダーランド", ["ツイステッドワンダーランド_OR_ツイステ"]],
        ["ブルーアーカイブ（ブルアカ）", ["ブルーアーカイブ_OR_ブルアカ"]],
        ["めろんぱーかー（めろぱか）", ["めろんぱーかー_OR_めろぱか_OR_めろりす"]],
        ["ヒューマンバグ大学なりきり", ["バグ大_AND_なりきり", "バグ大_AND_也", "バグ大_AND_nrkr"]],
        ["ヒューマンバグ大学", ["バグ大"]],
        "レスバ_OR_喧嘩",
        "女性限定",
        "男性限定",
        "男子限定",
        "女子限定",
        ["小学生・中学生・高校生限定", ["小学生・中学生・高校生限定_OR_小中高生限定_OR_小学生、中学生、高校生限定_OR_小・中・高生限定_OR_小・中・高校生限定", "小学生〜高校生_AND_限定"]],
        "中学生・高校生限定_OR_中高生限定_OR_中学生、高校生限定_OR_中・高生限定",
        "小学生・中学生限定_OR_小中学生限定_OR_小学生、中学生限定_OR_小・中学生限定",
        "中学生限定_OR_中学生だけ",
        "高校生限定",
        "学生限定",
        "ちいかわ",
        "あのちゃん",
        "アオのハコ",
        ["葬送のフリーレン", ["フリーレン"]],
        "スジ公開",
        "ブルーロック",
        ["ラブライブ！", ["ラブライブ_OR_ラブライバー"]],
        ["SEKAI NO OWARI（セカオワ）", ["SEKAI NO OWARI_OR_セカオワ"]],
        "不登校",
        "発達障害_OR_ADHD_OR_自閉症_OR_カサンドラ_OR_軽度知的障害_OR_アスペルガー_OR_双極性障害",
        "うつ病_OR_鬱病",
        "精神疾患_OR_精神障害",
        "知的障害_OR_境界知能",
        ["著作権（知的財産権）", ["知的財産_OR_著作権_OR_知財_OR_肖像権"]],
        "太鼓の達人",
        "28卒",
        "27卒",
        "26卒",
        "25卒",
        "24卒",
        "23卒",
        "大学生",
        ["パチンコ・スロット（パチスロ）", ["パチスロ_OR_パチンコ_OR_スロット"]],
        ["競馬予想", ["競馬"]],
        ["競艇予想", ["競艇"]],
        "失恋_OR_復縁",
        ["歌い手のトークルーム", ["歌い手"]],
        ["YouTuber", ["YouTube_AND_宣伝"]],
        "実況者",
        ["STPRなりきり", ["STPR_AND_なりきり", "STPR_AND_也", "STPR_AND_nrkr"]],
        "K也_OR_🇰🇷 也_OR_𝐊 也_OR_k也",
        ["なりきり", ["なりきり_OR_ぜんゆる_OR_nrkr_OR_#也_OR_D也_OR_ゆるなり_OR_緩也_OR_夢也_OR_夢 也_OR_歌い手也_OR_実況者也_OR_全伽羅_OR_全緩_OR_全ゆる_OR_全夢", "全_AND_緩_AND_也", "全_AND_也"]],
        ["オリキャラ BL", ["BL_AND_折", "BL_AND_オリキャラ", "薔薇_AND_オリキャラ", "折_AND_薔_AND_薇"]],
        ["オリキャラ恋愛", ["オリキャラ恋愛_OR_折恋_OR_折 恋", "オリキャラ_AND_恋愛", "折伽羅_AND_恋愛"]],
        "声優",
        "愚痴",
        "毒親",
        "即承認",
        ["ナイトワーク（夜職）", ["夜職_OR_ナイトワーク_OR_水商売_OR_ホステス_OR_キャバ嬢"]],
        "生活音",
        "SHEIN",
        "TEMU",
        "メルカリ",
        "ふるさと納税",
        "コストコ",
        ["ボイストレーニング（ボイトレ）", ["ボイトレ_OR_ボイストレーニング"]],
        ["トレーディングカード（トレカ）", ["トレカ_OR_トレーディングカード"]],
        ["ポケモンカード（ポケカ）", ["ポケモンカード_OR_ポケカ_OR_ダイキ様"]],
        ["ワンピースカード", ["ワンピースカード"]],
        ["オプチャ サポート", ["Admins_AND_公式"]],
        ["恋愛相談", ["恋愛_AND_相談"]],
        ["WePlay（ウィプレー）", ["WePlay_OR_ウィプレー"]],
        ["KAUCHE（カウシェ）", ["カウシェ"]],
        ["スタバ", ["スタバ_AND_無料", "スタバ_AND_配布", "スタバ_AND_ギフト"]],
        "新歓",
        "新入生",
        "大学",
    ];

    const DESC_STRONG_TAG = [
        "カブアンド",
        ["パソコン・ガジェット", ["自作PC_OR_ゲーミングPC_OR_デジタルガジェット", "スマホ_AND_ガジェット", "イヤホン_AND_ガジェット"]],
        ["生成AI・ChatGPT", ["ChatGPT_OR_チャットGPT_OR_LLM_OR_Claude_OR_Copilot", "Gemini_AND_AI"]],
        ["画像生成AI・AIイラスト", ["AIイラスト_OR_ばりぐっどくん_OR_AI絵画_OR_AI絵師", "画像_AND_生成_AND_AI"]],
        ["オプチャ サポート", ["LINE株式会社オープンチャット事務局"]],
        ["全国 雑談", ["#都内_AND_#田舎", "000102030405"]],
        "unistyle",
        "jobhunt",
        "ポイ活",
        ["LGBT", ["ゲイ_AND_バイ", "同性愛_OR_LGBT_OR_ゲイ学生_OR_Xジェンダー_OR_トランスジェンダー_OR_セクマイ_OR_ノンセク_OR_レズビアン"]],
        ["PRODUCE 101 JAPAN THE GIRLS（日プ女子）", ["日プガールズ_OR_日プ女子_OR_ME:I_OR_日プ3_OR_ME：I", "PRODUCE_AND_101_AND_JAPAN_AND_THE_AND_GIRLS"]],
        ["PRODUCE 101 JAPAN（日プ）", ["PRODUCE_AND_101_AND_JAPAN"]],
        ["地雷系", ["地雷系_OR_量産型_OR_ぴえん系", "地雷_AND_量産"]],
        ["片目界隈・自撮り界隈", ["片目界隈_OR_自撮り界隈"]],
        "失恋_OR_復縁",
        "不登校",
        "占い_OR_霊視_OR_占術",
        "占い師",
        "毒親",
        "発達障害_OR_ADHD_OR_自閉症_OR_カサンドラ_OR_軽度知的障害_OR_アスペルガー_OR_双極性障害",
        "うつ病_OR_鬱病",
        "知的障害_OR_境界知能",
        "精神疾患_OR_精神障害",
        "障害者",
        "ネッ友_OR_ネ友",
        "K也_OR_🇰🇷 也_OR_𝐊 也_OR_K也",
        "MBTI_OR_ISTJ_OR_ISFJ_OR_INFJ_OR_INTJ_OR_ISTP_OR_ISFP_OR_INFP_OR_INTP_OR_ESTP_OR_ESFP_OR_ENFP_OR_ENTP_OR_ESTJ_OR_ESFJ_OR_ENFJ_OR_ENTJ",
        ["偽カップル（偽カプ）", ["偽カップル_OR_偽カプ"]],
        "女性限定",
        "男性限定",
        "男子限定",
        "女子限定",
        ["小学生・中学生・高校生限定", ["小学生・中学生・高校生限定_OR_小中高生限定_OR_小学生、中学生、高校生限定_OR_小・中・高生限定_OR_小・中・高校生限定", "小学生〜高校生_AND_限定"]],
        "中学生・高校生限定_OR_中高生限定_OR_中学生、高校生限定_OR_中・高生限定",
        "小学生・中学生限定_OR_小中学生限定_OR_小学生、中学生限定_OR_小・中学生限定",
    ];

    const AFTER_DESC_STRONG_TAG = [
        ["クーポン・無料配布", ["クーポン"]],
        ["KAUCHE（カウシェ）", ["カウシェ"]],
        "ライブトーク",
        "メルカリ",
        "ふるさと納税",
        ["なりきり", ["なりきり_OR_ぜんゆる_OR_nrkr_OR_#也_OR_D也_OR_ゆるなり_OR_緩也_OR_夢也_OR_夢 也_OR_歌い手也_OR_実況者也_OR_全伽羅_OR_全緩_OR_全ゆる_OR_全夢", "全_AND_緩_AND_也", "全_AND_也"]],
        ["原神なりきり", ["原神_AND_なりきり", "原神_AND_也", "原神_AND_nrkr"]],
        ["呪術廻戦なりきり", ["呪術廻戦_AND_なりきり", "呪術廻戦_AND_也", "呪術廻戦_AND_nrkr"]],
        ["文ストなりきり", ["文スト_AND_なりきり", "文スト_AND_也", "文スト_AND_nrkr", "文スト_AND_ゆるなり", "文スト_AND_固定", "文ｽﾄ_AND_なりきり"]],
        ["にじさんじなりきり", ["にじさんじ_AND_なりきり", "にじさんじ_AND_也", "にじさんじ_AND_nrkr", "2434_AND_也", "🌈🕒_AND_也"]],
        ["ハイキューなりきり", ["ハイキュー_AND_なりきり", "ハイキュー_AND_緩", "ハイキュー_AND_也", "ハイキュー_AND_nrkr", "HQ_AND_なりきり", "HQ_AND_也", "HQ_AND_nrkr", "ＨＱ_AND_也"]],
        ["ヒロアカなりきり", ["ヒロアカ_AND_なりきり", "ヒロアカ_AND_也", "ヒロアカ_AND_nrkr"]],
        ["プロセカなりきり", ["プロセカ_AND_なりきり", "プロセカ_AND_也", "プロセカ_AND_nrkr", "ぷせゆる", "プセゆる"]],
        ["ツイステなりきり", ["ツイステ_AND_なりきり", "ツイステ_AND_也", "ツイステ_AND_nrkr"]],
        ["あんスタなりきり", ["あんスタ_AND_なりきり", "あんスタ_AND_也", "enst_AND_なりきり", "enst_AND_也", "あんスタ_AND_nrkr", "あんスタ_AND_ゆるなり", "あんすた_AND_ゆるなり", "あんスタ_AND_固定"]],
    ];

    public array $tags;
    protected string $start;
    protected string $end;

    function __construct()
    {
        $this->start = file_get_contents(getStorageFilePath(AppConfig::STORAGE_FILES['tagUpdatedAtDatetime'])) ?: '';
    }

    function replace(string|array $word, string $column): string
    {
        $rep = function ($str) use ($column) {
            $utfbin = mb_strpos($str, 'utfbin_') !== false;
            $collation = preg_match('/[\xF0-\xF7][\x80-\xBF][\x80-\xBF][\x80-\xBF]/', $str) || $utfbin ? 'utf8mb4_bin' : 'utf8mb4_general_ci';

            $like = "{$column} COLLATE {$collation} LIKE";
            if ($utfbin) $str = str_replace('utfbin_', '', $str);
            $str = str_replace('_AND_', "%' AND {$like} '%", $str);
            $str = str_replace('_OR_', "%' OR {$like} '%", $str);
            return "{$like} '%{$str}%'";
        };

        if (is_array($word)) {
            return "(" . implode(") OR (", array_map(fn($str) => $rep($str), $word[1])) . ")";
        }

        return $rep($word);
    }

    /** @return string[] */
    protected function getReplacedTags(string $column): array
    {
        $tags = array_merge(
            self::NAME_STRONG_TAG,
            array_merge(...json_decode(
                file_get_contents(getStorageFilePath(AppConfig::STORAGE_FILES['openChatSubCategoriesTag'])),
                true
            ))
        );

        $this->tags = array_map(fn($el) => is_array($el) ? $el[0] : $el, $tags);

        return array_map(fn($str) => $this->replace($str, $column), $tags);
    }

    function formatTag(string $tag): string
    {
        $listName = mb_strstr($tag, '_OR_', true) ?: $tag;
        $listName = str_replace('_AND_', ' ', $listName);
        $listName = str_replace('utfbin_', '', $listName);
        return $listName;
    }

    protected function updateName(string $column = 'oc.name', string $table = 'recommend')
    {
        $tags = $this->getReplacedTags($column);

        foreach ($tags as $key => $search) {
            $tag = $this->formatTag($this->tags[$key]);
            DB::execute(
                "INSERT INTO
                    {$table}
                SELECT
                    oc.id,
                    '{$tag}'
                FROM
                    (
                        SELECT
                            oc.*
                        FROM
                            open_chat AS oc
                            LEFT JOIN {$table} AS t ON t.id = oc.id
                        WHERE
                            t.id IS NULL
                            AND oc.updated_at BETWEEN :start
                            AND :end
                    ) AS oc
                WHERE
                    {$search}",
                ['start' => $this->start, 'end' => $this->end]
            );
        }
    }

    /** @return array{ string:string[] }  */
    protected function getReplacedTagsDesc(string $column): array
    {
        $this->tags = json_decode((file_get_contents(getStorageFilePath(AppConfig::STORAGE_FILES['openChatSubCategoriesTag']))), true);

        return [
            array_map(fn($a) => array_map(fn($str) => $this->replace($str, $column), $a), $this->tags),
            array_map(fn($str) => $this->replace($str, $column), self::DESC_STRONG_TAG),
            array_map(fn($str) => $this->replace($str, $column), self::AFTER_DESC_STRONG_TAG)
        ];
    }

    protected function updateDescription(string $column = 'oc.description', string $table = 'recommend')
    {
        [$tags, $strongTags, $afterStrongTags] = $this->getReplacedTagsDesc($column);

        $excute = function ($table, $tag, $search, $category) {
            $tag = $this->formatTag($tag);
            DB::execute(
                "INSERT INTO
                    {$table}
                SELECT
                    oc.id,
                    '{$tag}'
                FROM
                    (
                        SELECT
                            oc.*
                        FROM
                            open_chat AS oc
                            LEFT JOIN {$table} AS t ON t.id = oc.id
                        WHERE
                            t.id IS NULL
                            AND oc.category = {$category}
                            AND oc.updated_at BETWEEN :start
                            AND :end
                    ) AS oc
                WHERE
                    {$search}",
                ['start' => $this->start, 'end' => $this->end]
            );
        };

        foreach ($tags as $category => $array) {
            foreach ($strongTags as $key => $search) {
                $tag = self::DESC_STRONG_TAG[$key];
                $tag = is_array($tag) ? $tag[0] : $tag;
                $excute($table, $tag, $search, $category);
            }

            foreach ($array as $key => $search) {
                $tag = $this->tags[$category][$key];
                $tag = is_array($tag) ? $tag[0] : $tag;
                $excute($table, $tag, $search, $category);
            }

            foreach ($afterStrongTags as $key => $search) {
                $tag = self::AFTER_DESC_STRONG_TAG[$key];
                $tag = is_array($tag) ? $tag[0] : $tag;
                $excute($table, $tag, $search, $category);
            }
        }
    }

    protected function updateBeforeCategory(string $column = 'oc.name', string $table = 'recommend')
    {
        $strongTags = array_map(fn($a) => array_map(fn($str) => $this->replace($str, $column), $a), self::BEFORE_CATEGORY_NAME);

        $excute = function ($table, $tag, $search, $category) {
            $tag = $this->formatTag($tag);
            DB::execute(
                "INSERT INTO
                    {$table}
                SELECT
                    oc.id,
                    '{$tag}'
                FROM
                    (
                        SELECT
                            oc.*
                        FROM
                            open_chat AS oc
                            LEFT JOIN {$table} AS t ON t.id = oc.id
                        WHERE
                            t.id IS NULL
                            AND oc.category = {$category}
                            AND oc.updated_at BETWEEN :start
                            AND :end
                    ) AS oc
                WHERE
                    {$search}",
                ['start' => $this->start, 'end' => $this->end]
            );
        };

        foreach ($strongTags as $category => $array) {
            foreach ($array as $key => $search) {
                $tag = self::BEFORE_CATEGORY_NAME[$category][$key];
                $tag = is_array($tag) ? $tag[0] : $tag;
                $excute($table, $tag, $search, $category);
            }
        }
    }

    protected function updateName2(string $column = 'oc.name', string $table = 'oc_tag2')
    {
        $tags = $this->getReplacedTags($column);

        foreach ($tags as $key => $search) {
            $tag = $this->formatTag($this->tags[$key]);
            DB::execute(
                "INSERT INTO
                    {$table}
                SELECT
                    oc.id,
                    '{$tag}'
                FROM
                    (
                        SELECT
                            oc.*
                        FROM
                            open_chat AS oc
                            LEFT JOIN {$table} AS t ON t.id = oc.id
                            LEFT JOIN oc_tag AS t2 ON t2.id = oc.id
                        WHERE
                            t.id IS NULL
                            AND NOT t2.tag = '{$tag}'
                            AND oc.updated_at BETWEEN :start
                            AND :end
                    ) AS oc
                WHERE
                    ({$search})",
                ['start' => $this->start, 'end' => $this->end]
            );
        }
    }

    protected function updateDescription2(string $column = 'oc.description', string $table = 'oc_tag2')
    {
        [$tags, $strongTags, $afterStrongTags] = $this->getReplacedTagsDesc($column);

        $excute = function ($table, $tag, $search, $category) {
            $tag = $this->formatTag($tag);
            DB::execute(
                "INSERT INTO
                    {$table}
                SELECT
                    oc.id,
                    '{$tag}'
                FROM
                    (
                        SELECT
                            oc.*
                        FROM
                            open_chat AS oc
                            LEFT JOIN {$table} AS t ON t.id = oc.id
                            LEFT JOIN oc_tag AS t2 ON t2.id = oc.id
                        WHERE
                            t.id IS NULL
                            AND NOT t2.tag = '{$tag}'
                            AND oc.category = {$category}
                            AND oc.updated_at BETWEEN :start
                            AND :end
                    ) AS oc
                WHERE
                    ({$search})",
                ['start' => $this->start, 'end' => $this->end]
            );
        };

        foreach ($tags as $category => $array) {
            foreach ($strongTags as $key => $search) {
                $tag = self::DESC_STRONG_TAG[$key];
                $tag = is_array($tag) ? $tag[0] : $tag;
                $excute($table, $tag, $search, $category);
            }

            foreach ($array as $key => $search) {
                $tag = $this->tags[$category][$key];
                $tag = is_array($tag) ? $tag[0] : $tag;
                $excute($table, $tag, $search, $category);
            }

            foreach ($afterStrongTags as $key => $search) {
                $tag = self::AFTER_DESC_STRONG_TAG[$key];
                $tag = is_array($tag) ? $tag[0] : $tag;
                $excute($table, $tag, $search, $category);
            }
        }
    }

    function updateRecommendTables(bool $betweenUpdateTime = true)
    {
        $this->start = $betweenUpdateTime ? $this->start : '2023-10-16 00:00:00';
        $this->end = $betweenUpdateTime ? OpenChatServicesUtility::getModifiedCronTime(strtotime('+1hour'))->format('Y-m-d H:i:s') : '2033-10-16 00:00:00';

        $deleteRecommend = fn(string $table) => DB::execute(
            "DELETE FROM
                {$table}
            WHERE
                id IN (
                    SELECT
                        oc.id
                    FROM
                        open_chat AS oc
                        LEFT JOIN modify_recommend AS mr ON mr.id = oc.id
                    WHERE
                        mr.id IS NULL
                        AND oc.updated_at BETWEEN :start
                        AND :end
                )",
            ['start' => $this->start, 'end' => $this->end]
        );

        $delete = fn(string $table) => DB::execute(
            "DELETE FROM
                {$table}
            WHERE
                id IN (
                    SELECT
                        oc.id
                    FROM
                        open_chat AS oc
                    WHERE
                        oc.updated_at BETWEEN :start
                        AND :end
                )",
            ['start' => $this->start, 'end' => $this->end]
        );

        clearstatcache();

        DB::transaction(function () use ($deleteRecommend) {
            $deleteRecommend('recommend');
            $this->updateBeforeCategory();
            $this->updateName();
            $this->updateDescription('oc.name', 'recommend');
            $this->updateDescription();
            $this->modifyRecommendTags();
        });

        DB::transaction(function () use ($delete) {
            $delete('oc_tag');
            $this->updateBeforeCategory('oc.name', 'oc_tag');
            $this->updateBeforeCategory(table: 'oc_tag');
            $this->updateDescription('oc.name', 'oc_tag');
            $this->updateDescription(table: 'oc_tag');
            $this->updateName(table: 'oc_tag');
        });

        DB::transaction(function () use ($delete) {
            $delete('oc_tag2');
            $this->updateDescription2('oc.name');
            $this->updateDescription2();
            $this->updateName2();
            $this->updateName2('oc.description');
        });

        safeFileRewrite(getStorageFilePath(AppConfig::STORAGE_FILES['tagUpdatedAtDatetime']), (new \DateTime)->format('Y-m-d H:i:s'));
    }

    private function modifyRecommendTags()
    {
        DB::execute("UPDATE recommend AS t1 JOIN modify_recommend AS t2 ON t1.id = t2.id SET t1.tag = t2.tag");
    }

    function getAllTagNames(): array
    {
        $tags = array_merge(
            array_merge(...self::BEFORE_CATEGORY_NAME),
            self::NAME_STRONG_TAG,
            self::DESC_STRONG_TAG,
            self::AFTER_DESC_STRONG_TAG,
            array_merge(...json_decode(
                file_get_contents(getStorageFilePath(AppConfig::STORAGE_FILES['openChatSubCategoriesTag'])),
                true
            ))
        );

        $tags = array_map(fn($el) => is_array($el) ? $el[0] : $el, $tags);
        $tags = array_map(fn($el) => $this->formatTag($el), $tags);
        return array_unique($tags);
    }
}
