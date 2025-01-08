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
if (class_exists($config) && isset($config::$urlRoot)) {
    switch (MimimalCmsConfig::$urlRoot) {
        case '':
            $message = 'お探しのページは一時的にアクセスができない状況にあるか、移動もしくは削除された可能性があります。';
            $message2 = 'このオープンチャットは登録されていないか、削除されました';
            break;
        case '/th':
            $message = 'หน้าที่คุณกำลังมองหาอยู่อาจไม่สามารถเข้าถึงได้ชั่วคราว หรืออาจถูกย้ายหรือลบไปแล้ว';
            $message2 = 'ห้องสนทนานี้ไม่ได้ลงทะเบียนหรือถูกลบ';
            break;
        case '/tw':
            $message = '您正在查找的页面可能暂时无法访问，或者可能已移动或删除';
            $message2 = '此聊天室未注册或已删除';
    }
} else {
    $message = 'The page you are looking for is temporarily inaccessible and may be moved or deleted.';
}


$_meta = meta()->setTitle("{$httpCode} {$httpStatusMessage}")
    ->setDescription($message)
    ->setOgpDescription($message);

$_css = ['room_list', 'site_header', 'site_footer'];

?>
<!DOCTYPE html>
<html lang="ja">
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
    </style>

    <!-- 固定ヘッダー -->
    <main class="main" style="padding: 0 1rem;">
        <div style="margin: 0 -1rem; ">
            <?php viewComponent('site_header') ?>
        </div>
        <header style="padding: 0;">
            <?php if ($httpCode != 404 || !strpos(path(), 'oc/')) : ?>
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
    <?php if ($httpCode == 404) : ?>
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