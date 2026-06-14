<?php

namespace Shared\Exceptions;

use App\Controllers\Pages\NotFoundPageController;
use Shared\MimimalCmsConfig;

/**
 * ErrorPage class to handle displaying error message and generating Github URLs for error lines
 */
class ErrorPage
{
    /**
     * @var string|null The Github repository URL to generate links for error lines
     */
    public string|null $githubUrl = null;

    /**
     * @var string The directory name to be hidden in error messages
     */
    private string $hiddenDir = '';

    /**
     * @var string The regex pattern to extract the throw line number from error message
     */
    private string $THROW_LINE_PATTERN = '/in.+html\/(.+)\(\d+\)/';

    /**
     * @var string The regex pattern to extract the PHP error line number and file path from error message
     */
    private string $PHP_ERROR_LINE_PATTERN = '/\/html\/(.*) on line (\d+)/';

    /**
     * @var string The regex pattern to extract the file path and line number from error stack trace
     */
    private string $STACKTRACE_FILE_PATH_PATTERN = '/(#\d+) .+html\/(.+)\(\d+\)/';

    /**
     * @var string The regex pattern to extract the line number from PHP code in error message
     */
    private string $LINE_NUMBER_PATTERN = '/\.php\((\d+)\)/';

    /**
     * @var string The detailed error message
     */
    private string $detailsMessage = '';

    /**
     * @var string|false The PHP file path of the error line or false if not found
     */
    private string|false $phpErrorLineFilePath = false;

    /**
     * @var string|false The line number of the error line or false if not found
     */
    private string|false $phpErrorLineNum = false;

    /**
     * @var string The line number of the throw line
     */
    private string $thorwLineNum = '';

    /**
     * @var array The line numbers of the PHP code in error message
     */
    private array $lineNums = [];

    /**
     * Constructor method to initialize ErrorPage object
     */
    public function __construct()
    {
        $config = MimimalCmsConfig::class;
        if (class_exists($config) && isset($config::$errorPageGitHubUrl)) {
            $this->githubUrl = $config::$errorPageGitHubUrl;
        } else {
            return;
        }

        if (isset($config::$errorPageDocumentRootName)) {
            $dir = $config::$errorPageDocumentRootName;
            $this->THROW_LINE_PATTERN = "/in.+{$dir}\/(.+)\(\d+\)/";
            $this->PHP_ERROR_LINE_PATTERN = "/\/{$dir}\/(.*) on line (\d+)/";
            $this->STACKTRACE_FILE_PATH_PATTERN = "/(#\d+) .+{$dir}\/(.+)\(\d+\)/";
            $this->LINE_NUMBER_PATTERN = "/\.php\((\d+)\)/";
        }

        if (isset($config::$errorPageHideDirectory)) {
            $this->hiddenDir = $config::$errorPageHideDirectory;
        }
    }

    /**
     * Set the error message and extract necessary information from it
     *
     * @param string $detailsMessage The detailed error message
     */
    public function setMessage(string $detailsMessage)
    {
        $this->detailsMessage = $detailsMessage;

        if (!$this->githubUrl) {
            return;
        }

        [$this->phpErrorLineFilePath, $this->phpErrorLineNum] = $this->extractPhpErrorLine();

        $lineNums = $this->extractPhpLineNumbers();
        if (count($lineNums) > 1) {
            $this->thorwLineNum = array_shift($lineNums);
            $this->lineNums = $lineNums;
        }
    }

    /**
     * Get the error message with the hidden directory name removed
     *
     * @return string The error message
     */
    public function getMessage()
    {
        return str_replace($this->hiddenDir, '', $this->detailsMessage);
    }

    /**
     * Get the Github URL for the error line where the PHP error occurred
     *
     * @return string The Github URL for the error line or an empty string if not found
     */
    public function getGithubUrlWithPhpErrorLine(): string
    {
        if ($this->phpErrorLineFilePath) {
            return $this->getGithubUrl($this->phpErrorLineFilePath, $this->phpErrorLineNum);
        } else {
            return '';
        }
    }

    /**
     * Get the Github URL for the throw line
     *
     * @return string The Github URL for the throw line
     */
    public function getGithubUrlWithThrownLine(): string
    {
        $line = $this->extractThrowLine();
        if ($line) {
            return $this->getGithubURL($line, $this->thorwLineNum);
        } else {
            return '';
        }
    }

    /**
     * Get an array of Github URLs for all lines of PHP code in error message
     *
     * @return array An array of Github URLs
     */
    public function getGithubUrlsWithLine(): array
    {
        $array = [];
        foreach ($this->extractPaths() as $key => $path) {
            $array[$key] = $this->getGithubURL($path, $this->lineNums[$key] ?? '');
        }

        return $array;
    }

    /**
     * Get the Github URL for a given file path and line number
     *
     * @param string $path The file path of the error line
     * @param string|false $lineNum The line number of the error line or false if not found
     *
     * @return string The Github URL for the error line or an empty string if the Github URL is not set
     */
    private function getGithubUrl(string $path, $lineNum): string
    {
        return $this->githubUrl ? ($this->githubUrl . $path . '#L' . ($lineNum ?? '')) : '';
    }

    private function extractPhpErrorLine()
    {
        if (preg_match($this->PHP_ERROR_LINE_PATTERN, $this->detailsMessage, $matches)) {
            $file_path = $matches[1] ?? null;
            $line_number = $matches[2] ?? null;
        }

        return [$file_path ?? false, $line_number ?? false];
    }

    private function extractPhpLineNumbers(): array
    {
        preg_match_all($this->LINE_NUMBER_PATTERN, $this->detailsMessage, $matche);
        return $matche[1] ?? ['', ''];
    }


    private function extractThrowLine()
    {
        preg_match($this->THROW_LINE_PATTERN, $this->detailsMessage, $matche);
        return $matche[1] ?? '';
    }

    private function extractPaths(): array
    {
        preg_match_all($this->STACKTRACE_FILE_PATH_PATTERN, $this->detailsMessage, $matches);
        return $matches[2] ?? [];
    }
}

noStore();

$detailsMessage = $detailsMessage ?? '';
$httpStatusMessage = $httpStatusMessage ?? '';
$httpCode = $httpCode ?? 506;

try {
    if ($detailsMessage) {
        $m = new ErrorPage;
        $m->setMessage($detailsMessage);

        // Get the error message from the ErrorPage object.
        $errorMessage = $m->getMessage();

        // Get the Github URL with the PHP error line.
        $errorLineUrl = $m->getGithubUrlWithPhpErrorLine();

        // Get the Github URL with the thrown line.
        $thrownLineUrl = $m->getGithubUrlWithThrownLine();

        // Get an array of Github URLs with each line in the error message.
        $linesUrl = $m->getGithubUrlsWithLine();
    }
} catch (\Exception $e) {
    $errorMessage = $e->getMessage();
}

$config = MimimalCmsConfig::class;

// サーバーエラー（5xx）画面の文言。ロケール未確定時は英語にフォールバックする。
$srv = [
    'title'  => 'A temporary server error occurred',
    'lead'   => 'The server may be busy or experiencing a temporary problem. Please wait a moment and reload the page.',
    'reload' => 'Reload',
    'home'   => 'Back to home',
    'code'   => 'Error code',
];

if (class_exists($config) && isset($config::$urlRoot)) {
    switch (MimimalCmsConfig::$urlRoot) {
        case '':
            $message = 'お探しのページは一時的にアクセスができない状況にあるか、移動もしくは削除された可能性があります。';
            $message2 = 'このオープンチャットは登録されていないか、削除されました';
            $srv = [
                'title'  => 'サーバーで一時的なエラーが発生しました',
                'lead'   => 'アクセスが集中しているか、一時的な不具合が起きている可能性があります。少し時間をおいてから、もう一度読み込みしてください。',
                'reload' => '再読み込み',
                'home'   => 'トップページへ戻る',
                'code'   => 'エラーコード',
            ];
            break;
        case '/th':
            $message = 'หน้าที่คุณกำลังมองหาอยู่อาจไม่สามารถเข้าถึงได้ชั่วคราว หรืออาจถูกย้ายหรือลบไปแล้ว';
            $message2 = 'ห้องสนทนานี้ไม่ได้ลงทะเบียนหรือถูกลบ';
            $srv = [
                'title'  => 'เซิร์ฟเวอร์เกิดข้อผิดพลาดชั่วคราว',
                'lead'   => 'อาจมีผู้เข้าใช้งานจำนวนมาก หรือเกิดปัญหาชั่วคราว กรุณารอสักครู่แล้วโหลดหน้านี้ใหม่อีกครั้ง',
                'reload' => 'โหลดหน้าใหม่',
                'home'   => 'กลับสู่หน้าแรก',
                'code'   => 'รหัสข้อผิดพลาด',
            ];
            break;
        case '/tw':
            $message = '您正在查找的页面可能暂时无法访问，或者可能已移动或删除';
            $message2 = '此社群未注册或已删除';
            $srv = [
                'title'  => '伺服器發生暫時性錯誤',
                'lead'   => '可能是存取量過大或發生暫時性的問題，請稍候片刻後重新載入此頁面。',
                'reload' => '重新載入',
                'home'   => '返回首頁',
                'code'   => '錯誤代碼',
            ];
    }
} else {
    $message = 'The page you are looking for is temporarily inaccessible and may be moved or deleted.';
}

// 5xx（サーバー側の一時的な障害）は専用の「再読み込み」画面を出す。
// 404 / 410 など 4xx は従来どおりの表示を維持する。
$isServerError = $httpCode >= 500;

// 再読み込み先は、今まさにエラーになった URL（クエリ含む）。JS 無効でも href で同じ URL を再試行できる。
$reloadUrl = htmlspecialchars($_SERVER['REQUEST_URI'] ?? url(''), ENT_QUOTES, 'UTF-8');

// /oc/* の 410 (削除済み確定) のみエラーコードを出さず削除済みメッセージに切り替え。
// 404 (範囲外 / 未登録 id) は通常の "404 Not Found" + 汎用メッセージで返す。
$isOcDeletedPage = $httpCode == 410 && strpos(path(), 'oc/');
$titleText = $isServerError ? $srv['title'] : ($isOcDeletedPage ? $message2 : "{$httpCode} {$httpStatusMessage}");
$descText  = $isServerError ? $srv['lead']  : ($isOcDeletedPage ? $message2 : $message);

$_meta = meta()->setTitle($titleText)
    ->setDescription($descText)
    ->setOgpDescription($descText);

$_css = ['components/room_list', 'components/site_header', 'components/site_footer'];

try {
    $langCode = t('ja');
} catch (\Throwable $e) {
    $langCode = 'ja_JP';
}

?>
<!DOCTYPE html>
<html lang="<?php echo $langCode ?>">
<?php viewComponent('head', compact('_css', '_meta')) ?>

<body class="body">
    <style>
        /* Increase size of the main heading */
        h1 {
            font-size: 5rem;
        }

        /* Break long lines in the code section */
        code {
            word-wrap: break-word;
        }

        /* Set width, center, and add padding to the ordered list */
        ol {
            width: fit-content;
            margin: 0 auto;
            margin-top: 1.5rem;
            padding: 0 1rem;
        }

        /* Break URLs to fit in the list */
        a {
            word-break: break-all;
        }

        .main {
            max-width: var(--width);
        }

        /* ===== サーバーエラー（5xx）画面 =====
           tokens.css のセマンティック層のみ参照（ダーク/ライト両対応）。
           「公式の確認ゲート」(--ocj-*) と同じ ＝ 紙色カード / 琥珀の警告 / 緑のCTA の語彙。 */
        .srv-err {
            max-width: 460px;
            margin: 0 auto;
            padding: clamp(28px, 8vh, 72px) 6px 56px;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .srv-err-badge {
            width: 84px;
            height: 84px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            color: var(--ocj-amber);
            background: var(--ocj-amber-bg);
            animation: srvErrPulse 2.6s ease-out infinite, srvErrRise .5s both;
        }

        .srv-err-badge svg {
            width: 40px;
            height: 40px;
            display: block;
        }

        /* h1 の font-size:5rem を上書き（class セレクタが要素セレクタに勝つ） */
        .srv-err-title {
            font-size: 1.5rem;
            line-height: 1.5;
            font-weight: 800;
            letter-spacing: .01em;
            color: var(--ocj-ink-strong);
            margin: 22px 0 0;
            animation: srvErrRise .5s both .06s;
        }

        .srv-err-lead {
            font-size: .95rem;
            line-height: 1.85;
            color: var(--ocj-ink-soft);
            margin: 14px 0 0;
            max-width: 30em;
            animation: srvErrRise .5s both .12s;
        }

        a.srv-err-reload {
            margin-top: 30px;
            width: 100%;
            max-width: 320px;
            min-height: 56px;
            box-sizing: border-box;
            padding: 0 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            border-radius: 14px;
            background: var(--c-btn-brand-bg);
            color: var(--c-text-inverse);
            font-size: 1.0625rem;
            font-weight: 800;
            letter-spacing: .02em;
            text-decoration: none;
            word-break: normal;
            box-shadow: var(--ocj-shadow-btn);
            transition: background .15s ease, box-shadow .2s ease, transform .08s ease;
            animation: srvErrRise .5s both .18s;
            -webkit-tap-highlight-color: transparent;
        }

        a.srv-err-reload:hover {
            background: var(--c-btn-brand-bg-hover);
            box-shadow: var(--ocj-shadow-btn-hover);
        }

        a.srv-err-reload:active {
            transform: translateY(1px) scale(.99);
            box-shadow: var(--ocj-shadow-btn-press);
        }

        a.srv-err-reload:focus-visible {
            outline: 3px solid var(--c-brand-ring);
            outline-offset: 2px;
        }

        .srv-err-reload svg {
            width: 22px;
            height: 22px;
            flex: none;
        }

        a.srv-err-reload:hover svg {
            animation: srvErrSpin .6s ease;
        }

        a.srv-err-home {
            margin-top: 18px;
            font-size: .9rem;
            font-weight: 600;
            color: var(--c-text-link);
            text-decoration: none;
            padding: 6px 12px;
            border-radius: 8px;
            word-break: normal;
            animation: srvErrRise .5s both .24s;
        }

        a.srv-err-home:hover {
            text-decoration: underline;
        }

        .srv-err-code {
            margin-top: 26px;
            font-size: .75rem;
            letter-spacing: .06em;
            color: var(--ocj-ink-mute);
            font-variant-numeric: tabular-nums;
            animation: srvErrRise .5s both .3s;
        }

        @keyframes srvErrRise {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes srvErrPulse {
            0% {
                box-shadow: 0 0 0 0 var(--ocj-amber-line);
            }

            70% {
                box-shadow: 0 0 0 18px transparent;
            }

            100% {
                box-shadow: 0 0 0 0 transparent;
            }
        }

        @keyframes srvErrSpin {
            from {
                transform: rotate(0);
            }

            to {
                transform: rotate(360deg);
            }
        }

        @media (prefers-reduced-motion: reduce) {

            .srv-err-badge,
            .srv-err-title,
            .srv-err-lead,
            a.srv-err-reload,
            a.srv-err-home,
            .srv-err-code {
                animation: none;
            }

            a.srv-err-reload:hover svg {
                animation: none;
            }
        }
    </style>

    <!-- 固定ヘッダー -->
    <main class="main" style="padding: 0 1rem;">
        <div style="margin: 0 -1rem; ">
            <?php viewComponent('site_header') ?>
        </div>
        <?php // mvp.css は素の header を装飾しなくなったため、中央寄せはここで指定 ?>
        <header style="padding: 0; text-align: center;">
            <?php if ($isServerError) : ?>
                <?php // 5xx: サーバー側の一時的な障害。再読み込みを促す専用画面。 ?>
                <div class="srv-err">
                    <div class="srv-err-badge" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z" />
                            <line x1="12" y1="9" x2="12" y2="13" />
                            <line x1="12" y1="17" x2="12.01" y2="17" />
                        </svg>
                    </div>
                    <h1 class="srv-err-title"><?php echo $srv['title'] ?></h1>
                    <p class="srv-err-lead"><?php echo $srv['lead'] ?></p>
                    <a class="srv-err-reload" href="<?php echo $reloadUrl ?>" onclick="location.reload(); return false;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <polyline points="23 4 23 10 17 10" />
                            <polyline points="1 20 1 14 7 14" />
                            <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15" />
                        </svg>
                        <span><?php echo $srv['reload'] ?></span>
                    </a>
                    <a class="srv-err-home" href="<?php echo url('') ?>"><?php echo $srv['home'] ?></a>
                    <p class="srv-err-code"><?php echo $srv['code'] ?>: <?php echo (int) $httpCode ?></p>
                </div>
            <?php elseif ($httpCode != 410 || !strpos(path(), 'oc/')) : ?>
                <h1><?php echo $httpCode ?? '' ?></h1>
                <h2><?php echo $httpStatusMessage ?? '' ?></h2>
                <br>
                <p><?php echo $message ?></p>
            <?php else : ?>
                <br>
                <p><?php echo $message2 ?>🙀</p>
            <?php endif ?>
        </header>
        <?php if ($detailsMessage) : ?>
            <!-- Display error message if it exists -->
            <section>
                <pre><code><?php echo $errorMessage ?></code></pre>
            </section>
            <?php if ($errorLineUrl || $thrownLineUrl || $linesUrl) : ?>
                <!-- Display links to relevant lines on GitHub if available -->
                <ol>
                    <!-- Error line -->
                    <?php if ($errorLineUrl) : ?>
                        <li style="list-style-type: none">
                            <small>
                                <a href="<?php echo $errorLineUrl ?>"><?php echo $errorLineUrl ?></a>
                            </small>
                        </li>
                    <?php endif ?>
                    <!-- Line -->
                    <?php if ($thrownLineUrl) : ?>
                        <li style="list-style-type: none">
                            <small>
                                <a href="<?php echo $thrownLineUrl ?>"><?php echo $thrownLineUrl ?></a>
                            </small>
                        </li>
                    <?php endif ?>
                    <!-- Stack Trace -->
                    <?php foreach ($linesUrl as $key => $url) : ?>
                        <li value="<?php echo $key ?>">
                            <small>
                                <a href="<?php echo $url ?>"><?php echo $url ?></a>
                            </small>
                        </li>
                    <?php endforeach ?>
                </ol>
            <?php endif ?>
        <?php else : ?>
            <!-- Display empty paragraph if error message does not exist -->
            <p></p>
        <?php endif ?>
    </main>
    <?php if ($httpCode == 404 || $httpCode == 410) : ?>
        <?php /** @var NotFoundPageController $c */
        try {
            $c = app(NotFoundPageController::class);
            $c->index()->render();
        } catch (\Throwable $e) {
            echo 'error';
            pre_var_dump($e->__toString());
        }
        ?>
    <?php endif ?>
    <footer style="padding: 1rem;">
        <?php viewComponent('footer_inner') ?>
    </footer>
    <script defer src="<?php echo fileUrl("/js/site_header_footer.js", urlRoot: "") ?>"></script>
</body>

</html>