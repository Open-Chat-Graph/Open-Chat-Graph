<?php

declare(strict_types=1);

use App\Config\SecretsConfig;
use App\Config\AppConfig;
use App\Services\Admin\AdminAuthService;
use App\Services\OpenChat\Utility\OpenChatServicesUtility;
use Shadow\Kernel\Dispatcher\ReceptionInitializer;
use Shadow\Kernel\Utility\KernelUtility;
use Shared\Exceptions\NotFoundException;
use Shared\MimimalCmsConfig;

/**
 * Inserts HTML line breaks before all newlines in a string.
 *
 * @param string $string The input string to be processed.
 * @return string The string with HTML line breaks.
 */
function nl2brReplace(string $string): string
{
    $lines = preg_split('/\r\n|\r|\n/', $string);
    $result = implode("<br>", $lines);
    return $result;
}

function gTag(string $id): string
{
    if (AppConfig::$isStaging || AppConfig::$isDevlopment) {
        return '';
    }

    return
        <<<HTML
        <!-- Google Tag Manager -->
        <script>
        ;(function (w, d, s, l, i) {
            w[l] = w[l] || []
            w[l].push({ 'gtm.start': new Date().getTime(), event: 'gtm.js' })
            var f = d.getElementsByTagName(s)[0],
            j = d.createElement(s),
            dl = l != 'dataLayer' ? '&l=' + l : ''
            j.async = true
            j.src = 'https://www.googletagmanager.com/gtm.js?id=' + i + dl
            f.parentNode.insertBefore(j, f)
        })(window, document, 'script', 'dataLayer', '{$id}')
        </script>
        HTML;
}

function meta(): App\Views\Meta\Metadata
{
    return new App\Views\Meta\Metadata;
}

function signedNum(int|float $num): string
{
    if ($num < 0) {
        return (string)$num;
    } elseif ($num > 0) {
        return '+' . $num;
    } else {
        return '0';
    }
}

function signedNumF(int|float $num): string
{
    if ($num < 0) {
        return number_format($num);
    } elseif ($num > 0) {
        return '+' . number_format($num);
    } else {
        return '0';
    }
}

function signedCeil(int|float $num): float
{
    if ($num < 0) {
        return floor($num);
    } else {
        return ceil($num);
    }
}

function viewComponent(string $string, ?array $var = null): void
{
    if ($var) {
        extract($var);
    }
    include __DIR__ . '/../Views/components/' . $string . '.php';
}

function dateTimeAttr(int $timestamp): string
{
    return date('Y-m-d\TH:i:sO', $timestamp);
}

function convertDatetime(string|int $datetime, bool $time = false, string $format = 'Y/n/j'): string
{
    if (is_int($datetime)) {
        // タイムスタンプが与えられた場合
        if ($time) {
            return date($format . ' G:i', $datetime);
        }
        return date($format, $datetime);
    }

    // 日付文字列をDateTimeImmutableオブジェクトに変換
    $dateTime = new DateTimeImmutable($datetime);

    // 形式を変更して返す
    if ($time) {
        return $dateTime->format($format . ' G:i');
    }
    return $dateTime->format($format);
}

function timeElapsedString(string $datetime, int $thresholdMinutes = 15): string
{
    $now = new DateTimeImmutable();
    $interval = $now->diff(new DateTimeImmutable($datetime));

    $totalMinutes = $interval->days * 24 * 60 + $interval->h * 60 + $interval->i;

    if ($totalMinutes <= $thresholdMinutes) {
        return 'たった今';
    } elseif ($interval->y > 0) {
        return $interval->y . '年前';
    } elseif ($interval->m > 0) {
        return $interval->m . 'ヶ月前';
    } elseif ($interval->d > 0) {
        return $interval->d . '日前';
    } elseif ($interval->h > 0) {
        return $interval->h . '時間前';
    } elseif ($interval->i > 0) {
        return $interval->i . '分前';
    } else {
        return $interval->s . '秒前';
    }
}

function getCronModifiedDateTime(string $datetime, string $format = 'Y/n/j G:i'): string
{
    $fileTime = OpenChatServicesUtility::getModifiedCronTime($datetime);
    return $fileTime->format($format);
}

function getHostAndUri(): string
{
    return ReceptionInitializer::getDomainAndHttpHost('') . KernelUtility::getCurrentUri('');
}

function getQueryString(string $separater = '?'): string
{
    return $_SERVER['QUERY_STRING'] ? $separater . $_SERVER['QUERY_STRING'] : '';
}

function cache()
{
    header('Cache-Control: private');
}

function noStore()
{
    header('Cache-Control: no-store, no-cache, must-revalidate');
}

function handleRequestWithETagAndCache(string $content, int $maxAge = 0, int $sMaxAge = 3600, $hourly = true): void
{
    if (AppConfig::$isStaging || !AppConfig::$enableCloudflare) {
        cache();
        return;
    }

    // ETagを生成（ここではコンテンツのMD5ハッシュを使用）
    if ($hourly) {
        $etag = '"' . md5(MimimalCmsConfig::$urlRoot . $content . filemtime(AppConfig::getStorageFilePath('hourlyCronUpdatedAtDatetime'))) . '"';
    } else {
        $etag = '"' . md5(MimimalCmsConfig::$urlRoot . $content) . '"';
    }

    // max-ageと共にCache-Controlヘッダーを設定
    header("Cache-Control: public, max-age={$maxAge}, must-revalidate");
    header("Cloudflare-CDN-Cache-Control: max-age={$sMaxAge}");

    // 現在のリクエストのETagを取得
    $requestEtag = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? str_replace('-gzip', '', trim($_SERVER['HTTP_IF_NONE_MATCH'])) : '';

    // ETagが一致する場合は304 Not Modifiedを返して終了
    if ($requestEtag === $etag) {
        header("HTTP/1.1 304 Not Modified");
        exit;
    }

    header("ETag: $etag");
}

function purgeCacheCloudFlare(
    ?string $zoneID = null,
    ?string $apiKey = null,
    ?array $files = null,
    ?array $prefixes = null
): string {
    $zoneID = $zoneID ?? SecretsConfig::$cloudFlareZoneId;
    $apiKey = $apiKey ?? SecretsConfig::$cloudFlareApiKey;

    if (AppConfig::$isStaging || AppConfig::$isDevlopment || !AppConfig::$enableCloudflare) {
        return 'is Development';
    }

    // cURLセッションを初期化
    $ch = curl_init();

    // Cloudflare APIに送信するデータを設定
    $payload = [];
    if ($files) {
        $payload['files'] = $files;
    }

    if ($prefixes) {
        $payload['prefixes'] = $prefixes;
    }
    
    if (empty($payload)) {
        $payload['purge_everything'] = true;
    }

    $data = json_encode($payload);

    curl_setopt($ch, CURLOPT_URL, "https://api.cloudflare.com/client/v4/zones/$zoneID/purge_cache");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

    // 認証情報をヘッダーに追加
    $headers = [
        "Authorization: Bearer $apiKey",
        "Content-Type: application/json",
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    // リクエストを実行し、レスポンスを取得
    $response = curl_exec($ch);

    // エラーチェック
    if (curl_errno($ch)) {
        echo 'Error:' . curl_error($ch);
    }

    return $response;
}

function getHouryUpdateTime()
{
    return file_get_contents(AppConfig::getStorageFilePath('hourlyCronUpdatedAtDatetime'));
}

function getDailyUpdateTime()
{
    return file_get_contents(AppConfig::getStorageFilePath('dailyCronUpdatedAtDate'));
}

/**
 * @return string oc-img/{$idPath}/{$imgUrl}.webp
 */
function getImgPath(int $open_chat_id, string $imgUrl): string
{
    $subDir = filePathNumById($open_chat_id);
    return AppConfig::OPENCHAT_IMG_PATH[MimimalCmsConfig::$urlRoot] . "/{$subDir}/{$imgUrl}.webp";
}

/**
 * @return string oc-img/preview/{$idPath}/{$imgUrl}_p.webp
 */
function getImgPreviewPath(int $open_chat_id, string $imgUrl): string
{
    $subDir = filePathNumById($open_chat_id);
    return AppConfig::OPENCHAT_IMG_PATH[MimimalCmsConfig::$urlRoot] . '/' . AppConfig::OPENCHAT_IMG_PREVIEW_PATH . "/{$subDir}/{$imgUrl}" . AppConfig::OPENCHAT_IMG_PREVIEW_SUFFIX . ".webp";
}

function imgUrl($img_url)
{
    return AppConfig::$lineImageUrl . $img_url;
}

function imgPreviewUrl($img_url)
{
    return AppConfig::$lineImageUrl . $img_url . AppConfig::LINE_IMG_URL_PREVIEW_PATH;
}

function filePathNumById(int $id): string
{
    return (string)floor($id / 1000);
}

function getCategoryName(int $category): string
{
    return array_flip(AppConfig::OPEN_CHAT_CATEGORY[MimimalCmsConfig::$urlRoot])[$category] ?? '';
}

function isDailyUpdateTime(
    DateTime $currentTime = new DateTime,
    DateTime $nowStart = new DateTime,
): bool {
    $start = [
        AppConfig::CRON_MERGER_HOUR_RANGE_START[MimimalCmsConfig::$urlRoot],
        AppConfig::CRON_START_MINUTE[MimimalCmsConfig::$urlRoot]
    ];

    // currentTimeの日付を基準にstartTimeを設定（faketimeに対応）
    $baseDate = $currentTime->format('Y-m-d');
    $startTime = (new DateTime($baseDate))->setTime(...$start);
    $endTime = (new DateTime($startTime->format('Y-m-d H:i:s')))->modify('+1 hour');

    // 日次処理時刻が23時台の場合、日付跨ぎを考慮
    // 例: 23:30開始の場合、23:30〜翌0:30が範囲
    if ($start[0] >= 23) {
        // 現在時刻が0時台かつ終了時刻より前なら、前日の開始時刻と比較
        if ($currentTime->format('H') < $start[0] && $currentTime < $endTime) {
            $startTime = (new DateTime($baseDate))->modify('-1 day')->setTime(...$start);
            $endTime = (new DateTime($startTime->format('Y-m-d H:i:s')))->modify('+1 hour');
        }
    }

    if ($currentTime >= $startTime && $currentTime < $endTime) return true;
    return false;
}

/**
 * Daily Cron開始時刻からの経過時間を計算
 *
 * @param string|null $urlRoot 言語（null = 現在の言語）
 * @param DateTime|null $currentTime 現在時刻（null = 現在時刻）
 * @return float 経過時間（時間単位）
 */
function getDailyCronElapsedHours(?string $urlRoot = null, ?DateTime $currentTime = null): float
{
    $urlRoot = $urlRoot ?? MimimalCmsConfig::$urlRoot;
    $currentTime = $currentTime ?? new DateTime();

    $cronStartTime = (new DateTime())->setTime(
        AppConfig::CRON_MERGER_HOUR_RANGE_START[$urlRoot],
        AppConfig::CRON_START_MINUTE[$urlRoot],
        0
    );

    // cronが前日開始の場合
    if ($cronStartTime > $currentTime) {
        $cronStartTime->modify('-1 day');
    }

    return ($currentTime->getTimestamp() - $cronStartTime->getTimestamp()) / 3600;
}

/**
 * Daily Cronが指定時間以内に開始されたかチェック
 *
 * @param float $withinHours 開始から何時間以内か
 * @param string|null $urlRoot 言語（null = 現在の言語）
 * @param DateTime|null $currentTime 現在時刻（null = 現在時刻）
 * @return bool 指定時間以内ならtrue
 */
function isDailyCronWithinHours(float $withinHours, ?string $urlRoot = null, ?DateTime $currentTime = null): bool
{
    return getDailyCronElapsedHours($urlRoot, $currentTime) < $withinHours;
}

function checkLineSiteRobots(int $retryLimit = 3, int $retryInterval = 1): void
{
    if (AppConfig::$isMockEnvironment) {
        return;
    }

    $retryCount = 0;

    while ($retryCount < $retryLimit) {
        try {
            $robots = file_get_contents('https://openchat.line.me/robots.txt');
            if (!str_contains($robots, 'User-agent: *')) {
                throw new \RuntimeException('Robots.txt: 拒否 ' . $robots);
            }

            return;
        } catch (\Throwable $e) {
            $retryCount++;
            if ($retryCount >= $retryLimit) {
                throw new \RuntimeException(get_class($e) . ': ' . $e->getMessage());
            }

            sleep($retryInterval);
            continue;
        }

        $retryCount++;
        sleep($retryInterval);
    }

    throw new \RuntimeException('Line site robots.txt not found or invalid');
}

function getImgSetErrorTag(): string
{
    return <<<HTML
        onerror="this.src='/assets/ogp.png'; this.removeAttribute('onerror'); this.removeAttribute('onload');" onload="this.removeAttribute('onerror'); this.removeAttribute('onload');"
    HTML;
}

function getFilePath($path, $pattern): string
{
    $file = glob(MimimalCmsConfig::$publicDir . "/{$path}/{$pattern}");
    if ($file) {
        $fileName = basename($file[0]);
        return "{$path}/{$fileName}";
    } else {
        return '';
    }
}

function allowCORS()
{
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE");
    header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept');
    if ($_SERVER['REQUEST_METHOD'] === "OPTIONS") {
        exit;
    }
}

function formatMember(int $n)
{
    if (MimimalCmsConfig::$urlRoot !== '') {
        return number_format($n);
    }

    return $n < 1000 ? $n : ($n >= 10000 ? (floor($n / 1000) / 10 . '万') : number_format($n));
}

function sortAndUniqueArray(array $array, int $min = 2)
{
    // 各要素の出現回数をカウント
    $counts = array_count_values(array_filter($array, fn($el) => is_string($el) || is_int($el) || $el));

    // 出現回数が2以上の要素のみを保持
    $filteredCounts = array_filter($counts, fn($count) => $count >= $min);

    // 出現回数の多い順にソート（同じ出現回数の場合は元の順序を保持）
    uksort($filteredCounts, function ($a, $b) use ($filteredCounts) {
        if ($filteredCounts[$a] === $filteredCounts[$b]) {
            return 0;
        }
        return $filteredCounts[$a] < $filteredCounts[$b] ? 1 : -1;
    });

    // キーのみを抽出（重複排除）
    return array_keys($filteredCounts);
}

function calculatePositionPercentage($number): string
{
    $percentage = $number;
    $position = ($percentage <= 50) ? "上位" : "下位";
    $adjustedPercentage = ($percentage <= 50) ? $percentage : 100 - $percentage;
    $adjustedPercentage = $adjustedPercentage ? $adjustedPercentage : 1;

    return $position . $adjustedPercentage . "%";
}

function calculateTimeDifference($latestDateTime, $pastDateTime): string
{
    // 日時をDateTimeオブジェクトに変換
    $latest = new DateTime($latestDateTime);
    $past = new DateTime($pastDateTime);

    // 差を計算
    $difference = $latest->diff($past);

    // 総時間を計算
    $hours = ($difference->days * 24) + $difference->h + ($difference->i / 60) + ($difference->s / 3600);

    // 100時間未満かどうか判断し、適切な文字列を返す
    if ($hours < 100) {
        return sprintf("%d時間", $hours);
    } else {
        // 日数を計算（小数点以下切り捨て）
        $days = floor($hours / 24);
        return sprintf("%d日", $days);
    }
}

function calculateTimeFrame($latestDateTime, $pastDateTime): string
{
    // 日時をDateTimeオブジェクトに変換
    $latest = new DateTime($latestDateTime);
    $past = new DateTime($pastDateTime);

    // 差を計算
    $difference = $latest->diff($past);

    // 総日数を計算
    $days = $difference->days;

    // 総時間を計算
    $hours = ($days * 24) + $difference->h + ($difference->i / 60) + ($difference->s / 3600);

    // 経過時間に応じて戻り値を変更
    if ($hours <= 24) {
        return 'hour';
    } elseif ($days <= 7) {
        return 'week';
    } elseif ($days <= 30) {
        return 'month';
    } else {
        return 'all';
    }
}


function formatDateTimeHourly2(string $dateTimeStr): string
{
    // 引数の日時をDateTimeオブジェクトに変換
    $dateTime = new \DateTime($dateTimeStr);

    // 現在の年を取得
    $currentYear = date("Y");

    // 引数の日時の年を取得
    $yearOfDateTime = $dateTime->format("Y");

    // 現在の年と引数の日時の年を比較
    if ($yearOfDateTime == $currentYear) {
        // 今年の場合のフォーマット
        return $dateTime->format("m/d G:i");
    } else {
        // 今年以外の場合のフォーマット
        return $dateTime->format("Y/m/d G:i");
    }
}

function formatDateTime(string $dateTimeStr): string
{
    // 引数の日時をDateTimeオブジェクトに変換
    $dateTime = new \DateTime($dateTimeStr);

    // 現在の年を取得
    $currentYear = date("Y");

    // 引数の日時の年を取得
    $yearOfDateTime = $dateTime->format("Y");

    // 現在の年と引数の日時の年を比較
    if ($yearOfDateTime == $currentYear) {
        // 今年の場合のフォーマット
        return $dateTime->format("n/j");
    } else {
        // 今年以外の場合のフォーマット
        return $dateTime->format("Y/n/j");
    }
}

function timeElapsedString2(string $datetime, int $thresholdMinutes = 15, int $intervalDate = 6): string
{
    $now = new DateTimeImmutable();
    $interval = $now->diff(new DateTimeImmutable($datetime));

    $totalMinutes = $interval->days * 24 * 60 + $interval->h * 60 + $interval->i;

    if ($totalMinutes <= $thresholdMinutes) {
        return 'たった今';
    } elseif ($interval->y > 0 || $interval->m > 0 || $interval->d > $intervalDate) {
        return formatDateTime($datetime);
    } elseif ($interval->d > 0) {
        return $interval->d . '日前';
    } elseif ($interval->h > 0) {
        return $interval->h . '時間前';
    } elseif ($interval->i > 0) {
        return $interval->i . '分前';
    } else {
        return $interval->s . '秒前';
    }
}

function isMobile(): bool
{
    $user_agent =  getUA();
    if ((strpos($user_agent, 'iPhone') !== false)
        || (strpos($user_agent, 'Android') !== false)
    ) {
        return true;
    } else {
        return false;
    }
}

function sessionStart(): bool
{
    if (isset($_SERVER['HTTP_HOST'])) {
        session_set_cookie_params([
            'secure' => MimimalCmsConfig::$cookieDefaultSecure,
            'httponly' => MimimalCmsConfig::$cookieDefaultHttpOnly,
            'samesite' => MimimalCmsConfig::$cookieDefaultSameSite,
        ]);
        session_name("session");
    }

    return session_start();
}

/**
 * @throws NotFoundException
 */
function adminMode(): true
{
    noStore();

    /** @var AdminAuthService $adminAuthService */
    $adminAuthService = app(AdminAuthService::class);
    if (!$adminAuthService->auth())
        throw new NotFoundException;

    return true;
}

function isAdmin(): bool
{
    /** @var AdminAuthService $adminAuthService */
    $adminAuthService = app(AdminAuthService::class);
    return $adminAuthService->auth();
}

function getStorageFileTime(string $filename, bool $fullPath = false): int|false
{
    $path = $fullPath === false ? (__DIR__ . '/../../storage/' . $filename) : $filename;

    if (!file_exists($path)) {
        return false;
    }

    return filemtime($path);
}

/**
 * GitHub参照情報からGitHubのURLを生成する
 *
 * @param array{filePath: string, lineNumber: string|int, fileName?: string, label?: string} $githubRef
 * @return string GitHubのURL
 */
function buildGitHubUrl(array $githubRef): string
{
    $repo = AppConfig::$githubRepo;
    $branch = AppConfig::$githubBranch;
    return "https://github.com/{$repo}/blob/{$branch}/{$githubRef['filePath']}#L{$githubRef['lineNumber']}";
}

/**
 * GitHubリンクHTMLを生成する
 *
 * @param string $path ファイルパス
 * @param int $line 行番号
 * @param string|null $label リンクのラベル（省略時はファイル名:行番号）
 * @return string Aタグ付きのHTML
 */
function githubLink(string $path, int $line, ?string $label = null): string
{
    $url = buildGitHubUrl(['filePath' => $path, 'lineNumber' => $line]);
    $displayLabel = $label ?? basename($path) . ':' . $line;
    return '<a href="' . htmlspecialchars($url) . '" target="_blank" rel="noopener">' . htmlspecialchars($displayLabel) . '</a>';
}

function t(string $text, ?string $lang = null): string
{
    static $data = json_decode(file_get_contents(AppConfig::TRANSLATION_FILE), true);
    static $defaultLang = str_replace('/', '', MimimalCmsConfig::$urlRoot) ?: 'ja';

    if ($lang === null) {
        $lang = $defaultLang;
    } else {
        $lang = str_replace('/', '', $lang) ?: 'ja';
    }

    return $data[$text][$lang] ?? $text;
}

function sprintfT(string $format, string|int ...$values): string
{
    $text = t($format);
    return sprintf($text, ...$values);
}

/**
 * 説明文を指定文字数で切り詰める
 * @param string $text 元のテキスト
 * @param int $limit 文字数制限
 * @param string $suffix 省略記号
 * @return string 切り詰められたテキスト
 */
function truncateDescription($text, $limit = 70, $suffix = '...')
{
    // 改行やタブを半角スペースに変換
    $text = preg_replace('/[\r\n\t]+/', ' ', $text);

    // 連続する半角スペースを1つに
    $text = preg_replace('/\s+/', ' ', $text);

    // 前後の空白を削除
    $text = trim($text);

    // 文字数チェック
    if (mb_strlen($text, 'UTF-8') <= $limit) {
        return $text;
    }

    // 指定文字数で切り取り
    $truncated = mb_substr($text, 0, $limit, 'UTF-8');

    // 単語の途中で切れないように調整（日本語対応）
    $lastSpace = mb_strrpos($truncated, ' ', 0, 'UTF-8');
    if ($lastSpace !== false && $lastSpace > $limit * 0.8) {
        $truncated = mb_substr($truncated, 0, $lastSpace, 'UTF-8');
    }

    return $truncated . $suffix;
}

/**
 * @param array{url: string, emid: string, ...} $oc OpenChat data array containing at least url and emid
 * @return string
 */
function lineAppUrl(array $oc): string
{
    /*  if (isMobile())
        return AppConfig::LINE_APP_URL_SP . $oc['emid'] . AppConfig::LINE_APP_SUFFIX_SP; */

    return AppConfig::LINE_APP_URL . $oc['url'] . AppConfig::LINE_APP_SUFFIX;
}

/**
 * 経過時間を分秒形式でフォーマット
 *
 * microtime(true)で取得した開始時刻からの経過時間を、
 * 「X分Y秒」または「Y秒」の形式で返す。
 *
 * @param float $startTime microtime(true)で取得した開始時刻
 * @return string フォーマットされた経過時間（例: "2分30秒", "45秒"）
 *
 * @example
 * ```php
 * $start = microtime(true);
 * // ... 処理 ...
 * echo formatElapsedTime($start); // "2分30秒"
 * ```
 */
function formatElapsedTime(float $startTime): string
{
    $elapsedSeconds = microtime(true) - $startTime;
    $minutes = (int) floor($elapsedSeconds / 60);
    $seconds = (int) round($elapsedSeconds - ($minutes * 60));
    return $minutes > 0 ? "{$minutes}分{$seconds}秒" : "{$seconds}秒";
}
