<?php
declare(strict_types=1);

// 大量データ生成のためメモリ制限を上げる
ini_set('memory_limit', '512M');

/**
 * LINE公式API モックサーバー（リアル挙動版）
 *
 * JSONベースで本物のAPIと同じ挙動をシミュレート:
 * - データ件数（環境変数で制御可能）
 *   - MOCK_RANKING_COUNT: ランキング件数（デフォルト: 10000）
 *   - MOCK_RISING_COUNT: 急上昇件数（デフォルト: 1000）
 * - ルーム出現パターン（EMIDベースで固定、クローリング対象判定テスト用）
 *   - 60%: 通常ルーム（常に出現、メンバー数変動あり）
 *   - 30%: 断続的ルーム（2～7日に1回出現）
 *     → ランキング/急上昇に出ていない期間も詳細APIで情報更新あり
 *     → 「情報更新あるがランキングから消えた＝アクティビティなし」判定可能
 *   - 10%: 削除済みルーム（ランキング/急上昇には過去1回のみ出現）
 *     - 80%（全体の8%）: 通常の削除済み（詳細API参照可能、内容固定、メンバー数固定）
 *     - 10%（全体の1%）: 完全削除（詳細APIも招待ページも404で完全に抹消）
 *     - 10%（全体の1%）: 更新ありの削除済み（詳細API参照可能、たまに内容が変わる）
 * - 20%: タイトル・説明文・画像変化
 * - 50%: メンバー数増減（1時間で数名～100名）
 * - EMIDは固定（同じルームは同じEMIDを保持）
 * - カテゴリ別件数は均等分布（最大3倍の差）
 * - 多言語対応（日本語/繁体字中国語/タイ語）
 *   - リクエストヘッダー x-lal で言語判定
 *   - 言語別のカテゴリID・データファイル
 * - レスポンス速度調整（時間帯により変動、HTMLページのみ即応答）
 *   - ランキング/急上昇API: 20-45分相当（10万件取得時）
 *   - スクエア詳細API: 100-300ms/件（時間帯により変動）
 *   環境変数: MOCK_DELAY_ENABLED, MOCK_DELAY_MS, MOCK_DELAY_PER_ITEM_US
 */

// データ件数（環境変数から取得、デフォルト値を設定）
$rankingCount = (int)($_ENV['MOCK_RANKING_COUNT'] ?? 10000);
$risingCount = (int)($_ENV['MOCK_RISING_COUNT'] ?? 1000);

// データファイル（言語別・件数別）
// 件数をファイル名に含めることで、環境変数を変更したら自動的に新しいデータが生成される
$rankingDataFile = "/app/data/ranking_{$rankingCount}_%s.json";
$risingDataFile = "/app/data/rising_{$risingCount}_%s.json";

// 時間ベースのシード値（10分ごとに変化）
$crawlCycle = (int)(time() / 600); // 600秒 = 10分

// リクエストヘッダーから言語を判定
function getLanguageFromHeaders(): string
{
    $headers = getallheaders();
    if (isset($headers['x-lal'])) {
        $lang = strtolower($headers['x-lal']);
        return in_array($lang, ['tw', 'th']) ? $lang : 'ja';
    }
    return 'ja';
}

$language = getLanguageFromHeaders();

// レスポンス速度調整（環境変数で制御）
// MOCK_DELAY_ENABLED: 遅延モード有効化（1で有効、0または未設定で無効）
// MOCK_DELAY_MS: リクエスト全体の基本遅延時間（ミリ秒）※手動設定時のみ
// MOCK_DELAY_PER_ITEM_US: 返却アイテム1件あたりの遅延時間（マイクロ秒）※手動設定時のみ
$delayEnabled = (int)($_ENV['MOCK_DELAY_ENABLED'] ?? 0);
$baseDelayMs = (int)($_ENV['MOCK_DELAY_MS'] ?? 0);
$perItemDelayUs = (int)($_ENV['MOCK_DELAY_PER_ITEM_US'] ?? 0);

// 遅延モードが有効な場合、時間帯に応じた遅延を自動計算
if ($delayEnabled && $baseDelayMs === 0 && $perItemDelayUs === 0) {
    $currentHour = (int)date('G'); // 0-23

    // 時間帯による遅延設定
    if ($currentHour >= 0 && $currentHour < 6) {
        // 深夜（0-6時）: 最も遅い
        // ランキング/急上昇: 2.0-2.2倍（40-45分）
        $multiplier = mt_rand(200, 220) / 100;
        // スクエア詳細: 250-300ms/件（約3.6件/秒）
        $detailDelayMs = mt_rand(250, 300);
    } elseif ($currentHour >= 6 && $currentHour < 9) {
        // 早朝（6-9時）: やや速い
        // ランキング/急上昇: 1.2-1.4倍（24-28分）
        $multiplier = mt_rand(120, 140) / 100;
        // スクエア詳細: 150-180ms/件（約6件/秒）
        $detailDelayMs = mt_rand(150, 180);
    } elseif ($currentHour >= 9 && $currentHour < 18) {
        // 昼間（9-18時）: 最も速い
        // ランキング/急上昇: 1.0-1.2倍（20-24分）
        $multiplier = mt_rand(100, 120) / 100;
        // スクエア詳細: 100-150ms/件（約8件/秒）
        $detailDelayMs = mt_rand(100, 150);
    } else {
        // 夜間（18-24時）: 中速
        // ランキング/急上昇: 1.5-1.7倍（30-34分）
        $multiplier = mt_rand(150, 170) / 100;
        // スクエア詳細: 180-220ms/件（約5件/秒）
        $detailDelayMs = mt_rand(180, 220);
    }

    // ランキング/急上昇API用の遅延
    $basePerItemDelay = 12000; // 基本: 12000μs/件（10万件を20分で処理）
    $perItemDelayUs = (int)($basePerItemDelay * $multiplier);
    $baseDelayMs = 50; // リクエスト全体の基本遅延
} else {
    // 遅延モード無効 or 手動設定時
    $detailDelayMs = 0;
}

// 遅延を適用する関数
function applyResponseDelay(int $baseDelayMs, int $perItemDelayUs, int $itemCount): void
{
    if ($baseDelayMs > 0) {
        usleep($baseDelayMs * 1000);
    }
    if ($perItemDelayUs > 0 && $itemCount > 0) {
        usleep($perItemDelayUs * $itemCount);
    }
}

// ランダムテキスト生成
function generateRandomTitle(int $seed): string
{
    mt_srand($seed);

    $templates = [
        '%s好き集まれ！',
        '%s部屋',
        '%s雑談',
        '%sファン',
        '%s初心者歓迎',
        '%s攻略',
        '%sまったり',
        '%sガチ勢',
        '%s情報交換',
        '%sコミュニティ',
    ];

    $topics = [
        'ゲーム', 'アニメ', 'マンガ', 'スポーツ', '音楽', '映画', 'グルメ', '旅行',
        'ファッション', '美容', 'ペット', '車', 'バイク', 'カメラ', '釣り', '料理',
        '筋トレ', 'ヨガ', 'ダンス', 'ギター', 'ピアノ', '英語', '資格', 'プログラミング',
        '副業', '投資', '仮想通貨', 'NFT', 'メタバース', 'AI', 'ガジェット', 'スマホ',
    ];

    $template = $templates[array_rand($templates)];
    $topic = $topics[array_rand($topics)];

    return sprintf($template, $topic);
}

function generateRandomDescription(int $seed): string
{
    mt_srand($seed);

    $templates = [
        '%sについて語り合いましょう！初心者から上級者まで大歓迎です。',
        '%sが好きな人集まれ！気軽に参加してください。',
        '%sの情報交換や雑談をするグループです。みんなで楽しく話しましょう！',
        '%sに関する質問・相談・攻略などなんでもOK！まったりやってます。',
        '%s仲間を探してます。一緒に楽しみましょう！',
    ];

    $topics = [
        'ゲーム', 'アニメ', 'マンガ', 'スポーツ', '音楽', '映画', 'グルメ', '旅行',
        'ファッション', '美容', 'ペット', '趣味', '勉強', '仕事', 'ビジネス',
    ];

    $template = $templates[array_rand($templates)];
    $topic = $topics[array_rand($topics)];

    return sprintf($template, $topic);
}

/**
 * カテゴリに対応するサブカテゴリデータを生成
 *
 * @param int $categoryId カテゴリID
 * @param string $language 言語コード
 * @return array サブカテゴリ配列 [['id' => int, 'subcategory' => string, 'categoryId' => int], ...]
 */
function generateSubcategories(int $categoryId, string $language): array
{
    // カテゴリ0（全部/全部/ทั้งหมด）はサブカテゴリなし
    if ($categoryId === 0) {
        return [];
    }

    // 言語別のサブカテゴリ定義
    $subcategoriesData = [
        'ja' => [
            17 => ['ポケポケ', 'ブレインロット', 'ポケモンza', 'フォートナイト', 'ポケモンGO', 'マリオカート', 'APEX', 'モンハン', 'スプラトゥーン', 'ポケモン', 'ドラクエ', 'あつ森', 'マイクラ', '荒野行動', 'モンスト'],
            16 => ['プロ野球', '陸上', 'テニス', '卓球', 'ゴルフ', 'マラソン', 'キャンプ', '野球', 'Jリーグ', '海外サッカー', 'プレミアリーグ', 'ランニング', '格闘技', '相撲', 'ロードバイク'],
            26 => ['BTS', '藤井風', 'BE:FIRST', 'ITZY', 'Six TONES', 'Snow Man', 'King&Prince', 'NiziU', 'なにわ男子', 'Sexy Zone', 'YouTuber', '俳優', '声優', 'アイドル', 'お笑い'],
            7 => ['学生', '中学生', '高校生', '大学生', '10代', '20代', '30代', '40代', '50代', '60代', '雑談', '相談', '社会人', '女性限定', '専業主婦'],
            22 => ['アニメ', 'オリキャラ', 'なりきり', 'SPY FAMILY', '東京リベンジャーズ', 'PUI PUI モルカー', 'かぐや様は告らせたい', 'ハイキュー!!', 'ポケモン', 'ヒロアカ', '呪術廻戦', 'サンリオ', 'プリキュア', '鬼滅の刃', '声優'],
            40 => ['仮想通貨', '資産運用', '億り人', '投資', 'FX', 'Coin', '貯金', 'マーケティング', '株', 'お金', '不動産', '経済', '節税', '年金', '保険'],
            33 => ['藤井風', 'K-POP', '歌ってみた', '洋楽', '歌い手', 'ボカロ', '作曲', 'YOASOBI', 'フェス', 'ロック', '邦楽', 'Official髭男dism', 'King Gnu', 'DISH//', 'HIPHOP'],
            8 => ['シール', '地震', 'コストコ', '神社', '節約', '北海道', '東京', '神奈川', '愛知', '京都', '大阪', '兵庫', '気象', '防災', '移住'],
            20 => ['恋愛', '垢抜け', 'ダイエット', 'GRL', 'プチプラ', 'ニキビ', '美容', 'メイク', '脱毛', 'GU', 'ユニクロ', 'メンズ', 'スキンケア', 'ネイル', 'ヘア'],
            11 => ['中学', '高校', '大学', '勉強', '英語', '資格', '留学', '韓国語', '中国語', 'フランス語', 'スペイン語', '検定', '漢検', '英検', 'TOEIC'],
            5 => ['25卒', '24卒', '23卒', '就活', '転職', 'インターン', '公務員', '看護師', 'ドライバー', '保育士', 'IT', '営業', 'デザイナー', 'エンジニア', '人事'],
            2 => ['中学', '高校', '大学', '勉強', '部活', '試験', 'サークル', '通信', '大学院'],
            12 => ['クーポン', 'レシピ', 'ラーメン', 'アイス', 'スタバ', '自炊', 'お菓子づくり', 'お弁当', 'パンづくり', 'グルメ情報', 'カフェ', '居酒屋', '北海道', '九州', '沖縄'],
            23 => ['コロナ', 'メンタルヘルス', 'ダイエット', '自律神経', 'カウンセリング', '筋トレ', '睡眠', 'HSP', 'エクササイズ', '運動', '生活習慣', 'アレルギー', '歯', 'アトピー', '肩こり'],
            6 => ['支援', 'オプチャ宣伝', '雑談', '大学', '悩み相談'],
            28 => ['妊活', '子育て', 'ママ友', '育休', '妊娠', '出産', 'フタくま', 'プレママ', '教育', '受験', '保育園', '幼稚園', '学生'],
            19 => ['バイク', '車', '鉄道', 'トミカ', '自衛隊', '新幹線', 'JR', '飛行機', 'トラック', '自転車', '道路', 'バス', '戦闘機', '船', '模型'],
            18 => ['北海道', '沖縄', 'ディズニー', 'ひとり旅', '登山', 'キャンプ', 'USJ', '国内', '関東', '関西', '九州', '海外', 'ワーホリ', 'バックパッカー', 'ハワイ'],
            27 => ['犬', '猫', 'うさぎ', '柴犬', 'ダックス', 'ポメラニアン', 'チワワ', 'トイプードル', 'ハムスター', 'ハリネズミ', 'インコ', '昆虫', '爬虫類', 'めだか', 'アクアリウム'],
        ],
        'tw' => [
            17 => ['寶可夢', '動森', '原神', '英雄聯盟', 'APEX', '絕地求生', 'Minecraft', '天堂', '跑跑卡丁車', 'Steam', 'PS5', 'Switch', '手遊', '電競'],
            42 => ['音樂', '電影', '綜藝', '偶像', 'K-POP', '韓劇', '日劇', '動漫', '明星', '網紅', 'YouTuber', '直播主', '歌手'],
            20 => ['美妝', '保養', '彩妝', '香水', '穿搭', '時尚', '髮型', '美甲', '減肥', '健身', '醫美', '韓妝', '日系'],
            11 => ['英文', '日文', '韓文', '考試', '證照', 'TOEIC', '升學', '讀書', '大學', '高中', '國中', '補習', '線上課程'],
            18 => ['日本', '韓國', '台灣', '泰國', '歐洲', '美國', '自由行', '背包客', '打工度假', '露營', '登山', '溫泉', '美食'],
            6 => ['大學', '社團', '校友', '職場', '公司', '創業', 'NGO', '志工', '互助'],
            14 => ['攝影', '繪畫', '手作', '烘焙', 'DIY', '園藝', '收藏', '模型', '桌遊', '魔術方塊', '釣魚', '咖啡'],
            4 => ['育兒', '懷孕', '親子', '媽媽', '爸爸', '家庭', '教育', '才藝', '托嬰', '幼稚園'],
            12 => ['美食', '餐廳', '小吃', '甜點', '飲料', '火鍋', '燒烤', '日料', '韓食', '泰式', '義式', '烘焙', '料理'],
            40 => ['投資', '股票', '基金', '房地產', '加密貨幣', '理財', '創業', '行銷', '電商', '網拍'],
        ],
        'th' => [
            17 => ['ROV', 'PUBG', 'Free Fire', 'Minecraft', 'Roblox', 'Genshin', 'Mobile Legends', 'Valorant', 'League of Legends', 'Honkai'],
            33 => ['K-POP', 'T-POP', 'BTS', 'BLACKPINK', 'แร็พ', 'ดนตรี', 'เพลงสากล', 'เพลงไทย', 'คอนเสิร์ต'],
            10 => ['ศิลปิน', 'ไอดอล', 'นักร้อง', 'นักแสดง', 'ดารา', 'YouTuber', 'TikToker', 'ครีเอเตอร์'],
            18 => ['ญี่ปุ่น', 'เกาหลี', 'ยุโรป', 'อเมริกา', 'เที่ยวไทย', 'ทะเล', 'ภูเขา', 'แคมปิ้ง', 'บ๊กเกอร์'],
            28 => ['เด็กทารก', 'คุณแม่', 'ตั้งครรภ์', 'เลี้ยงลูก', 'ครอบครัว', 'โรงเรียน', 'อนุบาล'],
            16 => ['ฟุตบอล', 'วิ่ง', 'ฟิตเนส', 'โยคะ', 'แบดมินตัน', 'เทนนิส', 'กอล์ฟ', 'มวย', 'ไตรกีฬา'],
            14 => ['ถ่ายรูป', 'วาดรูป', 'ทำมือ', 'ทำอาหาร', 'ขนม', 'DIY', 'ปลูกผัก', 'ตกปลา'],
            34 => ['โปรแกรม', 'คอมพิวเตอร์', 'มือถือ', 'AI', 'แก็ดเจ็ต', 'สมาร์ทโฟน', 'iPhone', 'Android'],
            2 => ['มหาวิทยาลัย', 'มัธยม', 'ประถม', 'ชมรม', 'เพื่อนเก่า', 'รุ่นพี่'],
            12 => ['อาหาร', 'ร้านอาหาร', 'ขนม', 'เครื่องดื่ม', 'สูตรอาหาร', 'ทำอาหาร', 'อาหารเช้า'],
        ],
    ];

    $subcategoryNames = $subcategoriesData[$language][$categoryId] ?? [];

    // サブカテゴリがない場合は空配列
    if (empty($subcategoryNames)) {
        return [];
    }

    // サブカテゴリデータを生成
    $subcategories = [];
    foreach ($subcategoryNames as $index => $name) {
        $subcategories[] = [
            'id' => ($categoryId * 1000) + $index + 1, // ユニークなID生成
            'subcategory' => $name,
            'categoryId' => $categoryId,
        ];
    }

    return $subcategories;
}

// カテゴリ別件数分布（各カテゴリの差を3倍程度に調整）
// 合計100%、最大と最小の差は約3倍
function getCategoryDistribution(string $language): array
{
    // 日本語（ja） - 各カテゴリ均等分布（最大6.0% - 最小2.0% = 3倍）
    $jaDistribution = [
        17 => 6.0,   // ゲーム
        22 => 5.5,   // アニメ・漫画
        26 => 5.0,   // 芸能人・有名人
        33 => 4.8,   // 音楽
        8 => 4.6,    // 地域・暮らし
        16 => 4.5,   // スポーツ
        7 => 4.4,    // 同世代
        5 => 4.3,    // 働き方・仕事
        11 => 4.2,   // 研究・学習
        2 => 4.1,    // 学校・同窓会
        40 => 4.0,   // 金融・ビジネス
        6 => 3.9,    // 団体
        19 => 3.8,   // 乗り物
        41 => 3.7,   // イラスト
        23 => 3.6,   // 健康
        20 => 3.5,   // ファッション・美容
        28 => 3.4,   // 妊活・子育て
        12 => 3.3,   // 料理・グルメ
        27 => 3.2,   // 動物・ペット
        18 => 3.0,   // 旅行
        37 => 2.8,   // 写真
        30 => 2.6,   // 映画・舞台
        29 => 2.4,   // 本
        24 => 2.2,   // TV・VOD
        0 => 2.0,    // すべて (最小)
    ];

    // 繁体字中国語（tw） - 各カテゴリ均等分布
    $twDistribution = [
        17 => 6.0,   // 遊戲
        42 => 5.8,   // 娛樂
        35 => 5.6,   // 其他
        20 => 5.4,   // 流行／美妝
        11 => 5.2,   // 學習
        18 => 5.0,   // 旅遊
        6 => 4.8,    // 團體／組織
        14 => 4.6,   // 興趣
        4 => 4.4,    // 家庭／親子
        23 => 4.3,   // 健康
        43 => 4.2,   // 心情
        12 => 4.1,   // 美食
        40 => 4.0,   // 金融／商業
        16 => 3.9,   // 運動／健身
        2 => 3.8,    // 學校／校友
        44 => 3.6,   // 工作
        5 => 3.4,    // 公司／企業
        22 => 3.2,   // 動畫／漫畫
        27 => 3.0,   // 寵物
        34 => 2.5,   // 科技
        0 => 2.0,    // 全部 (最小)
    ];

    // タイ語（th） - 各カテゴリ均等分布
    $thDistribution = [
        17 => 6.0,   // เกม
        33 => 5.5,   // เพลง
        10 => 5.2,   // แฟนคลับ
        18 => 5.0,   // ท่องเที่ยว
        28 => 4.8,   // เด็ก
        16 => 4.7,   // กีฬา
        14 => 4.6,   // งานอดิเรก
        34 => 4.5,   // เทคโนโลยี
        2 => 4.4,    // โรงเรียน
        8 => 4.3,    // ท้องถิ่น
        22 => 4.2,   // อนิเมะ & การ์ตูน
        12 => 4.1,   // อาหาร
        19 => 4.0,   // รถยนต์
        27 => 3.9,   // สัตว์เลี้ยง
        40 => 3.7,   // การเงิน & ธุรกิจ
        37 => 3.5,   // การถ่ายภาพ
        11 => 3.3,   // การศึกษา
        35 => 3.1,   // อื่นๆ
        30 => 2.8,   // ภาพยนตร์
        20 => 2.4,   // แฟชั่น & บิวตี้
        24 => 2.2,   // รายการทีวี
        0 => 2.0,    // ทั้งหมด (最小)
    ];

    return match ($language) {
        'tw' => $twDistribution,
        'th' => $thDistribution,
        default => $jaDistribution,
    };
}

// データ読み込み・初期化
function loadOrInitializeData(string $dataFile, int $count, string $language): array
{
    if (file_exists($dataFile)) {
        $json = file_get_contents($dataFile);
        return json_decode($json, true) ?? [];
    }

    // 初期データ生成
    $rooms = [];
    $categoryDistribution = getCategoryDistribution($language);

    // カテゴリ別件数を計算（±2%のランダム変動）
    $categoryCounts = [];
    $totalAssigned = 0;

    foreach ($categoryDistribution as $categoryId => $percentage) {
        // ±2%の範囲でランダム変動（小さくして合計が100%に近づくように）
        mt_srand($categoryId + 5000);
        $variation = mt_rand(-200, 200) / 10000; // -0.02 ~ 0.02
        $adjustedPercentage = ($percentage / 100) + $variation;
        $adjustedPercentage = max(0.0001, $adjustedPercentage); // 最小0.01%
        $categoryCounts[$categoryId] = (int)($count * $adjustedPercentage);
        $totalAssigned += $categoryCounts[$categoryId];
    }

    // 端数調整（差分を最大カテゴリに加算または減算）
    $diff = $count - $totalAssigned;
    if ($diff != 0) {
        arsort($categoryCounts);
        $maxCategory = array_key_first($categoryCounts);
        $categoryCounts[$maxCategory] += $diff;
    }

    // 各カテゴリのルームを生成
    foreach ($categoryCounts as $categoryId => $categoryCount) {
        for ($i = 0; $i < $categoryCount; $i++) {
            // シードベースで固定EMIDを生成
            $uniqueSeed = ($categoryId * 100000) + $i + 1000;
            mt_srand($uniqueSeed);
            $emidSeed = mt_rand();
            $emid = substr(md5((string)$emidSeed), 0, 32);
            $imageHash = substr(md5((string)($emidSeed + 1)), 0, 64);

            $rooms[] = [
                'emid' => $emid,
                'name' => generateRandomTitle($emidSeed),
                'desc' => generateRandomDescription($emidSeed + 100),
                'profileImageObsHash' => $imageHash,
                'memberCount' => rand(100, 10000),
                'category' => $categoryId,
                'emblem' => rand(0, 1),
                'joinMethodType' => rand(0, 1),
                'createdAt' => time() - rand(0, 365 * 24 * 3600),
            ];
        }
    }

    // 保存（JSON_PRETTY_PRINTを削除してメモリ節約）
    file_put_contents($dataFile, json_encode($rooms, JSON_UNESCAPED_UNICODE));
    return $rooms;
}

/**
 * ルームの出現パターンを取得（EMIDベースで固定）
 *
 * @return array{type: string, deletedAtCycle?: int, subtype?: string, intervalCycles?: int}
 */
function getRoomAppearancePattern(string $emid): array
{
    $seed = crc32($emid);
    mt_srand($seed);
    $rand = mt_rand(0, 99);

    if ($rand < 10) {
        // 10%: 削除済みルーム（ランキング/急上昇には一度だけ出現）
        // 削除された時刻は過去1～50サイクル前とする
        $deletedAtCycle = mt_rand(1, 50);

        // 削除済みルームのサブタイプを決定
        $subRand = mt_rand(0, 9);
        if ($subRand === 0) {
            // 10%（全体の1%）: 完全削除（詳細APIも404）
            $subtype = 'complete';
        } elseif ($subRand === 1) {
            // 10%（全体の1%）: 更新あり（たまに内容が変わる）
            $subtype = 'updating';
        } else {
            // 80%（全体の8%）: 通常の削除済み（詳細API参照可能、内容固定）
            $subtype = 'normal';
        }

        return [
            'type' => 'deleted',
            'deletedAtCycle' => $deletedAtCycle,
            'subtype' => $subtype
        ];
    } elseif ($rand < 40) {
        // 30%: 断続的ルーム（数日に一度出現）
        // 2～7日に1回出現（1日=144サイクル、10分×144=24時間）
        $intervalDays = mt_rand(2, 7);
        return [
            'type' => 'intermittent',
            'intervalCycles' => $intervalDays * 144
        ];
    } else {
        // 60%: 通常ルーム（常に出現）
        return ['type' => 'normal'];
    }
}

/**
 * ルームが現在のサイクルで出現するかどうかを判定
 */
function shouldRoomAppear(array $room, int $currentCycle): bool
{
    $pattern = getRoomAppearancePattern($room['emid']);

    switch ($pattern['type']) {
        case 'deleted':
            // 削除済み: 特定のサイクル以前のみ出現
            return $currentCycle <= $pattern['deletedAtCycle'];

        case 'intermittent':
            // 断続的: 周期的に出現（EMIDベースのオフセットで出現タイミングをずらす）
            $seed = crc32($room['emid']);
            $offset = $seed % $pattern['intervalCycles'];
            return ($currentCycle + $offset) % $pattern['intervalCycles'] === 0;

        case 'normal':
        default:
            // 通常: 常に出現
            return true;
    }
}

// データを動的に変化させる
function simulateDataChanges(array $rooms, int $seed): array
{
    $currentCycle = $seed;
    $resultRooms = [];

    foreach ($rooms as $room) {
        // 出現判定
        if (!shouldRoomAppear($room, $currentCycle)) {
            continue; // このルームは今回出現しない
        }

        $pattern = getRoomAppearancePattern($room['emid']);

        // メンバー数変化（削除済みルームは固定）
        if ($pattern['type'] !== 'deleted') {
            // 通常のメンバー数変化処理
            mt_srand($seed + crc32($room['emid']));
            if (mt_rand(1, 100) <= 50) {
                // -5～+17名のランダム変動（平均すると増加傾向）
                $change = mt_rand(-5, 17);
                $room['memberCount'] += $change;
                $room['memberCount'] = max(1, $room['memberCount']);
            }
        }

        // 20%: タイトル・説明文・画像変化
        mt_srand($seed + crc32($room['emid']) + 1000);
        if (mt_rand(1, 100) <= 20) {
            $changeType = mt_rand(1, 3);

            switch ($changeType) {
                case 1: // タイトル変化
                    $suffixes = [' 🔥', ' ✨', ' 💡', ' 🎉', ' 👍', ' 🎊'];
                    $room['name'] .= $suffixes[array_rand($suffixes)];
                    break;

                case 2: // 説明文変化
                    $additions = [
                        '初心者歓迎！',
                        '新メンバー募集中！',
                        '参加者急増！',
                        'まったり雑談！',
                        '気軽に参加OK！'
                    ];
                    $room['desc'] .= ' ' . $additions[array_rand($additions)];
                    break;

                case 3: // 画像ハッシュ変化（最後の文字を変更）
                    $room['profileImageObsHash'] = substr($room['profileImageObsHash'], 0, -1)
                        . dechex(mt_rand(0, 15));
                    break;
            }
        }

        $resultRooms[] = $room;
    }

    return $resultRooms;
}

// レスポンスヘッダー
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$requestUri = $_SERVER['REQUEST_URI'];

try {
    // robots.txt
    if ($requestUri === '/robots.txt') {
        header('Content-Type: text/plain');
        echo "User-agent: *\nAllow: /\n";
        exit;
    }

    // カテゴリランキングAPI・急上昇API
    if (preg_match('#^/api/category/(\d+)\?sort=(RANKING|RISING)&limit=(\d+)(?:&ct=(.*))?$#', $requestUri, $matches)) {
        $categoryId = (int)$matches[1];
        $sort = $matches[2];
        $limit = (int)$matches[3];
        $ct = isset($matches[4]) ? urldecode($matches[4]) : '';

        // デバッグログ
        $debugLog = sprintf(
            "[%s] %s Request - Category: %d, Sort: %s, Limit: %d, CT: %s, Language: %s\n",
            date('Y-m-d H:i:s'),
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $categoryId,
            $sort,
            $limit,
            $ct === '' ? 'empty' : $ct,
            $language
        );
        error_log($debugLog, 3, '/app/data/debug.log');

        // データ読み込み（件数は環境変数で制御）
        // 言語別のデータファイルを使用
        if ($sort === 'RANKING') {
            $dataFile = sprintf($rankingDataFile, $language);
            $allRooms = loadOrInitializeData($dataFile, $rankingCount, $language);
        } else {
            $dataFile = sprintf($risingDataFile, $language);
            $allRooms = loadOrInitializeData($dataFile, $risingCount, $language);
        }

        // カテゴリでフィルター
        $categoryRooms = array_filter($allRooms, fn($r) => $r['category'] === $categoryId);
        $categoryRooms = array_values($categoryRooms);

        // データ変化をシミュレート
        $categoryRooms = simulateDataChanges($categoryRooms, $crawlCycle + $categoryId);

        // ランキングソート（メンバー数順）
        usort($categoryRooms, fn($a, $b) => $b['memberCount'] - $a['memberCount']);

        // シャッフル（30%を新規急上昇としてランダム挿入）
        if ($sort === 'RISING') {
            mt_srand($crawlCycle + $categoryId);
            shuffle($categoryRooms);
        }

        // ページネーション
        $start = $ct === '' ? 0 : (int)$ct;
        $end = $start + $limit;
        $pageRooms = array_slice($categoryRooms, $start, $limit);

        // LINE API形式に変換
        $squares = [];
        $rank = $start + 1;
        foreach ($pageRooms as $room) {
            $squares[] = [
                'square' => [
                    'emid' => $room['emid'],
                    'name' => $room['name'],
                    'desc' => $room['desc'],
                    'profileImageObsHash' => $room['profileImageObsHash'],
                    'emblems' => $room['emblem'] > 0 ? [$room['emblem']] : [],
                    'joinMethodType' => $room['joinMethodType'],
                    'squareState' => 0,
                    'badges' => [],
                    'invitationURL' => "https://line.me/ti/g2/{$room['emid']}",
                ],
                'rank' => $rank++,
                'memberCount' => $room['memberCount'],
                'latestMessageCreatedAt' => time() * 1000,
                'createdAt' => $room['createdAt'] * 1000,
            ];
        }

        // サブカテゴリデータを生成
        $subcategories = generateSubcategories($categoryId, $language);

        $response = [
            'squaresByCategory' => [
                [
                    'category' => ['id' => $categoryId],
                    'squares' => $squares,
                    'subcategories' => $subcategories,
                ]
            ]
        ];

        // 次のページがあれば継続トークン
        if ($end < count($categoryRooms)) {
            $response['continuationTokenMap'] = [(string)$categoryId => (string)$end];
        }

        // デバッグログ（レスポンス）
        $responseLog = sprintf(
            "[%s] %s Response - Category: %d, Sort: %s, Total: %d, Returned: %d, HasNext: %s\n",
            date('Y-m-d H:i:s'),
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $categoryId,
            $sort,
            count($categoryRooms),
            count($pageRooms),
            isset($response['continuationTokenMap']) ? 'yes' : 'no'
        );
        error_log($responseLog, 3, '/app/data/debug.log');

        // レスポンス速度調整（ランキング/急上昇API）
        // 時間帯により20-45分相当の遅延（10万件取得時）
        applyResponseDelay($baseDelayMs, $perItemDelayUs, count($pageRooms));

        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // スクエア詳細API
    if (preg_match('#^/api/square/([a-zA-Z0-9_-]+)\?limit=1$#', $requestUri, $matches)) {
        $emid = $matches[1];

        // 両方のデータセットから検索（言語別）
        $rankingFile = sprintf($rankingDataFile, $language);
        $risingFile = sprintf($risingDataFile, $language);
        $allRooms = array_merge(
            loadOrInitializeData($rankingFile, $rankingCount, $language),
            loadOrInitializeData($risingFile, $risingCount, $language)
        );

        $room = null;
        foreach ($allRooms as $r) {
            if ($r['emid'] === $emid) {
                $room = $r;
                break;
            }
        }

        if (!$room) {
            http_response_code(404);
            echo json_encode(['error' => 'Square not found']);
            exit;
        }

        $pattern = getRoomAppearancePattern($room['emid']);

        // 完全削除された場合は404を返す
        if ($pattern['type'] === 'deleted' && $pattern['subtype'] === 'complete' && !shouldRoomAppear($room, $crawlCycle)) {
            http_response_code(404);
            echo json_encode(['error' => 'Square not found']);
            exit;
        }

        // 断続的ルーム: ランキング/急上昇に出ていない期間中も情報更新
        if ($pattern['type'] === 'intermittent' && !shouldRoomAppear($room, $crawlCycle)) {
            mt_srand(crc32($room['emid']) + $crawlCycle);
            $updateType = mt_rand(1, 2);

            if ($updateType === 1) {
                $suffixes = [' [更新]', ' 【情報更新】', ' ※変更あり'];
                $room['name'] .= $suffixes[array_rand($suffixes)];
            } else {
                $additions = ['※最近情報が更新されました', '管理者より更新', '新しい情報があります'];
                $room['desc'] .= ' ' . $additions[array_rand($additions)];
            }
        }

        // 削除済み（更新あり）: たまに内容が変わる
        if ($pattern['type'] === 'deleted' && $pattern['subtype'] === 'updating') {
            // 10サイクル（100分）に1回変更
            mt_srand(crc32($room['emid']) + (int)($crawlCycle / 10));
            $updateType = mt_rand(1, 2);

            if ($updateType === 1) {
                $suffixes = [' [変更]', ' ※更新', ' (編集済)'];
                $room['name'] .= $suffixes[array_rand($suffixes)];
            } else {
                $additions = ['管理者による変更がありました', '情報が更新されています'];
                $room['desc'] .= ' ' . $additions[array_rand($additions)];
            }
        }

        // invitationTicket生成（EMIDの先頭10文字を使用）
        $invitationTicket = substr($room['emid'], 0, 10);

        // レスポンス速度調整（スクエア詳細API）
        if ($detailDelayMs > 0) {
            usleep($detailDelayMs * 1000);
        }

        echo json_encode([
            'square' => [
                'squareEmid' => $room['emid'],
                'name' => $room['name'],
                'desc' => $room['desc'],
                'profileImageObsHash' => $room['profileImageObsHash'],
                'memberCount' => $room['memberCount'],
                'joinMethodType' => $room['joinMethodType'],
            ],
            'recommendedSquares' => [],
            'noteCount' => 0,
            'productKey' => 'square-seo-real',
            'invitationTicket' => $invitationTicket,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 招待ページHTML
    if (preg_match('#^/(jp|tw|th)?/?ti/g2/([a-zA-Z0-9_-]+)$#', $requestUri, $matches)) {
        $langPrefix = $matches[1] ?? 'jp';
        $emid = $matches[2];

        // URLパスから言語を判定（jp/tw/th）
        $pageLang = match($langPrefix) {
            'tw' => 'tw',
            'th' => 'th',
            default => 'ja',
        };

        // 両方のデータセットから検索（言語別）
        $rankingFile = sprintf($rankingDataFile, $pageLang);
        $risingFile = sprintf($risingDataFile, $pageLang);
        $allRooms = array_merge(
            loadOrInitializeData($rankingFile, $rankingCount, $pageLang),
            loadOrInitializeData($risingFile, $risingCount, $pageLang)
        );

        $room = null;
        foreach ($allRooms as $r) {
            if ($r['emid'] === $emid) {
                $room = $r;
                break;
            }
        }

        if (!$room) {
            http_response_code(404);
            echo '<html><body>Not Found</body></html>';
            exit;
        }

        $pattern = getRoomAppearancePattern($room['emid']);

        // 完全削除された場合は404を返す
        if ($pattern['type'] === 'deleted' && $pattern['subtype'] === 'complete' && !shouldRoomAppear($room, $crawlCycle)) {
            http_response_code(404);
            echo '<html><body>Not Found</body></html>';
            exit;
        }

        // 断続的ルーム: ランキング/急上昇に出ていない期間中も情報更新
        if ($pattern['type'] === 'intermittent' && !shouldRoomAppear($room, $crawlCycle)) {
            mt_srand(crc32($room['emid']) + $crawlCycle);
            $updateType = mt_rand(1, 2);

            if ($updateType === 1) {
                $suffixes = [' [更新]', ' 【情報更新】', ' ※変更あり'];
                $room['name'] .= $suffixes[array_rand($suffixes)];
            } else {
                $additions = ['※最近情報が更新されました', '管理者より更新', '新しい情報があります'];
                $room['desc'] .= ' ' . $additions[array_rand($additions)];
            }
        }

        // 削除済み（更新あり）: たまに内容が変わる
        if ($pattern['type'] === 'deleted' && $pattern['subtype'] === 'updating') {
            mt_srand(crc32($room['emid']) + (int)($crawlCycle / 10));
            $updateType = mt_rand(1, 2);

            if ($updateType === 1) {
                $suffixes = [' [変更]', ' ※更新', ' (編集済)'];
                $room['name'] .= $suffixes[array_rand($suffixes)];
            } else {
                $additions = ['管理者による変更がありました', '情報が更新されています'];
                $room['desc'] .= ' ' . $additions[array_rand($additions)];
            }
        }

        header('Content-Type: text/html; charset=utf-8');
        echo "<!DOCTYPE html>
<html><head><meta charset='UTF-8'><title>{$room['name']}</title></head>
<body style='font-family:sans-serif;padding:20px;'>
<div style='max-width:600px;margin:0 auto;'>
<img src='https://obs.line-scdn.net/{$room['profileImageObsHash']}' style='width:100px;height:100px;border-radius:50%;'>
<h1 class='MdMN04Txt'>{$room['name']}</h1>
<p class='MdMN05Txt'>メンバー数: " . number_format($room['memberCount']) . "</p>
<p class='MdMN06Desc'>{$room['desc']}</p>
</div></body></html>";
        exit;
    }

    // 画像CDN（画像ハッシュは通常50文字以上）
    if (preg_match('#^/([a-zA-Z0-9_-]{30,})(/preview)?$#', $requestUri, $matches)) {
        $imageHash = $matches[1];

        header('Content-Type: image/jpeg');
        header('Cache-Control: public, max-age=31536000');

        // ハッシュから一貫性のある画像生成
        $seed = crc32($imageHash);
        mt_srand($seed);

        $r = mt_rand(150, 255);
        $g = mt_rand(150, 255);
        $b = mt_rand(150, 255);

        $img = imagecreatetruecolor(100, 100);
        $bgColor = imagecolorallocate($img, $r, $g, $b);
        imagefill($img, 0, 0, $bgColor);

        ob_start();
        imagejpeg($img, null, 80);
        echo ob_get_clean();
        exit;
    }

    // 404
    http_response_code(404);
    echo json_encode(['error' => 'Not Found', 'uri' => $requestUri]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
