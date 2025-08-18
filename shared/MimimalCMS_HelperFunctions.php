<?php

/**
 * MimimalCMS v1 Helper functions test
 * 
 * @author mimimiku778 <0203.sub@gmail.com>
 * @license https://github.com/mimimiku778/MimimalCMS/blob/master/LICENSE.md
 */

declare(strict_types=1);

use Shared\MimimalCmsConfig;

/**
 * @return object|\Shadow\Kernel\Application
 */
function app(?string $abstract = null, array $parameters = []): object
{
    if ($abstract) {
        return (new \Shadow\Kernel\Application($parameters))->make($abstract);
    }

    return new \Shadow\Kernel\Application;
}

/**
 * Render a template file with optional values.
 *
 * @param string|null $viewTemplateFile  Path to the template file.
 * @param array|null $valuesArray        [optional] associative array of values to pass to the template, 
 *                                       Keys starting with "_" will not be sanitized.
 * 
 * @return \Shadow\Kernel\ViewInterface
 * 
 * @throws \InvalidArgumentException      If passed invalid array or not found the template file.
 */
function view(?string $viewTemplateFile = null, ?array $valuesArray = null): \Shadow\Kernel\ViewInterface
{
    $instance = (new \Shadow\Kernel\Application)->make(\Shadow\Kernel\ViewInterface::class);

    if ($viewTemplateFile === null && $valuesArray === null) {
        return $instance;
    }

    return $instance->set($viewTemplateFile, $valuesArray);
}

/**
 * Returns HTTP status code and response in JSON format.
 *
 * @param mixed $data        Value to be returned as response.
 * @param ?int $responseCode [optional] HTTP status code
 * 
 * @return \Shadow\Kernel\Response
 */
function response(mixed $data, int $responseCode = 200): \Shadow\Kernel\Response
{
    return new \Shadow\Kernel\Response($responseCode, jsonData: $data);
}

/**
 * Returns HTTP status code and redirect.
 *
 * @param ?string $url      The url of path to be redirect.
 * @param int $responseCode [optional] HTTP status code
 * @param ?string $urlRoot   [optional] The root of the url. Default is `MimimalCmsConfig::$urlRoot`
 * @return \Shadow\Kernel\Response
 */
function redirect(?string $url = null, int $responseCode = 302, ?string $urlRoot = null): \Shadow\Kernel\Response
{
    $urlRoot = $urlRoot ?? MimimalCmsConfig::$urlRoot;

    if ($url === null) {
        $url = \Shadow\Kernel\Dispatcher\ReceptionInitializer::getDomainAndHttpHost($urlRoot);
    } elseif (!preg_match("~^(?:f|ht)tps?://~i", $url)) {
        $path = ltrim($url, "/");
        $url = \Shadow\Kernel\Dispatcher\ReceptionInitializer::getDomainAndHttpHost($urlRoot) . "/" . $path;
    }

    return new \Shadow\Kernel\Response($responseCode, $url);
}

/**
 * Get / set the specified session value or return a new Session instance.
 * If an array is passed as the key, an array of values will be set.
 *
 * @param  array|string|null  $key
 * @param  mixed  $default
 * 
 * @return mixed|\Shadow\Kernel\Session
 */
function session(null|string|array $value = null, mixed $default = null): mixed
{
    if ($value === null) {
        return new \Shadow\Kernel\Session;
    }

    if (is_array($value)) {
        \Shadow\Kernel\Session::push($value);
        return null;
    }

    return \Shadow\Kernel\Session::get($value, $default);
}

/**
 * Retrieve an old input value from the previous request, or return all old input as an array.
 *
 * @param string|null $key  The key of the old input value to retrieve.
 *                          If null, all old input values are returned as an array.
 * 
 * @return mixed|null|array Returns the requested old input value, or null if it does not exist. 
 *                          If $key is null, an array of all old input values is returned.
 */
function old(?string $key = null): mixed
{
    if ($key === null) {
        return \Shadow\Kernel\Reception::$flashSession['OLD_ARRAY'] ?? [];
    }

    return \Shadow\Kernel\Reception::$flashSession['OLD_ARRAY'][$key] ?? null;
}

/**
 * Get / set the specified cookie value or return a new Cookie instance.
 * If an array is passed as the key, an array of values will be set.
 * 
 * @param array|string|null $value
 * @param int $expires
 * @param string $path
 * @param string $samesite
 * @param bool $secure
 * @param bool $httpOnly
 * @param string $domain
 * 
 * @return mixed|\Shadow\Kernel\Cookie
 */
function cookie(
    null|string|array $value = null,
    int $expires = 0,
    string $path = '/',
    ?string $samesite = null,
    ?bool $secure = null,
    ?bool $httpOnly = null,
    string $domain = ''
): mixed {
    if ($value === null) {
        return new \Shadow\Kernel\Cookie;
    }

    if (is_array($value)) {
        \Shadow\Kernel\Cookie::push(
            $value,
            null,
            $expires,
            $path,
            $samesite,
            $secure,
            $httpOnly,
            $domain
        );
        return null;
    }

    return \Shadow\Kernel\Cookie::get($value);
}

/**
 * 
 * Returns the absolute path to the public directory, optionally with a subdirectory appended.
 * 
 * @param string $path [optional] The path to a subdirectory within the public directory.
 * @param ?string $publicDir [optional] The public directory path. Default is the constant MimimalCmsConfig::$publicDir.
 * 
 * @return string      The absolute path to the public directory with the specified subdirectory appended (if provided).
 * 
 * * **Example :** Input: `publicDir()`  Output: `/var/www/public`
 * * **Example :** Input: `publicDir("css/styles.css")`  Output: `/var/www/public/css/styles.css`
 * * **Example :** Input: `publicDir("/css/styles.css")`  Output: `/var/www/public/css/styles.css`
 */
function publicDir(string $path = '', ?string $publicDir = null): string
{
    $publicDir = $publicDir ?? MimimalCmsConfig::$publicDir;

    if ($path !== '') {
        $path = "/" . ltrim($path, "/");
    }

    return $publicDir . $path;
}

/**
 * Returns the full URL of the current website, including the domain and optional path.
 *
 * @param string|array{ urlRoot:string,paths:string|string[] } $paths [optional] path to append to the domain in the URL. 
 * 
 * @return string      The full URL of the current website domain.
 * 
 * * **Example :** Input: `url("home", "article")`  Output: `https://exmaple.com/home/article`
 * * **Example :** Input: `url("/home", "/article")`  Output: `https://exmaple.com/home/article`
 * * **Example :** Input: `url("home/", "article/")`  Output: `https://exmaple.com/home//article/`
 * * **Example :** Input: `url(["urlRoot" => "/en", "paths" => ["home", "article"]])`  Output: `https://example.com/en/home/article`
 * 
 * @throws \InvalidArgumentException If the argument passed is an array and does not contain the required keys.
 */
function url(string|array ...$paths): string
{
    if (isset($paths[0]) && is_array($paths[0])) {
        $urlRoot = $paths[0]['urlRoot'] ?? throw new \InvalidArgumentException('Invalid argument passed to url() function.');
        $paths = $paths[0]['paths'] ?? throw new \InvalidArgumentException('Invalid argument passed to url() function.');
    } else {
        $urlRoot = MimimalCmsConfig::$urlRoot;
    }

    $uri = '';
    foreach (is_array($paths) ? $paths : [$paths] as $path) {
        $uri .= "/" . ltrim($path, "/");
    }

    return \Shadow\Kernel\Dispatcher\ReceptionInitializer::getDomainAndHttpHost($urlRoot) . $uri;
}

/**
 * Generates the URL for a given page number.
 * 
 * @param string $path       The path to use.
 * @param int    $pageNumber The page number to generate the URL for. If 1, the page number is omitted.
 * @param ?string $urlRoot    [optional] The root of the URL. Default is `MimimalCmsConfig::$urlRoot`.
 * @return string The URL for the given page number.
 * 
 * * **Example :** Input: `pagerUrl("home", 5)`  Output: `https://exmaple.com/home/5`
 * * **Example :** Input: `pagerUrl("/home/", 5)`  Output: `https://exmaple.com/home/5`
 * * **Example :** Input: `pagerUrl("home", 1)`  Output: `https://exmaple.com/home`
 */
function pagerUrl(string $path, int $pageNumber, ?string $urlRoot = null): string
{
    $urlRoot = $urlRoot ?? MimimalCmsConfig::$urlRoot;

    if ($path !== '') {
        $path = "/" . ltrim(rtrim($path, "/"), "/");
    }

    $secondPath = ($pageNumber > 1) ? "/" . (string) $pageNumber : '';
    return \Shadow\Kernel\Dispatcher\ReceptionInitializer::getDomainAndHttpHost($urlRoot) . $path . $secondPath;
}

/**
 * Returns the current request path.
 *
 * @param ?string $urlRoot [optional] The root of the URL. Default is `MimimalCmsConfig::$urlRoot`.
 * 
 * @return string The current request path.
 *
 * * **Example :** Output: `/home`
 */
function path(?string $urlRoot = null): string
{
    $urlRoot = $urlRoot ?? MimimalCmsConfig::$urlRoot;

    return \Shadow\Kernel\Utility\KernelUtility::getCurrentUri($urlRoot);
}

/**
 * Create a log message from the user agent.
 * 
 * @return string User Agent.
 */
function getUA(): string
{
    static $ua = null;

    if ($ua !== null) {
        return $ua;
    }

    $string = mb_ereg_replace('[\x00-\x1F\x7F]', '', ($_SERVER['HTTP_USER_AGENT'] ?? 'null'));
    $ua = mb_substr($string, 0, 512);

    return $ua;
}

/**
 * This function returns the user's IP address as a string. 
 * It first checks several HTTP headers for possible IP addresses, 
 * and then falls back to the REMOTE_ADDR server variable.
 *
 * @return string The client's IP address, or 'null' if it cannot be determined.
 */
function getIP(): string
{
    static $ip = null;

    if ($ip !== null) {
        return $ip;
    }

    $headers = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_SP_HOST',
        'HTTP_VIA',
        'HTTP_CLIENT_IP',
        'HTTP_FORWARDED',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_FROM'
    ];

    foreach ($headers as $header) {
        if (!isset($_SERVER[$header])) {
            continue;
        }

        $ips = array_map('trim', explode(',', $_SERVER[$header]));
        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'null';
    return $ip;
}

/**
 * Generate a random CSRF token, save it to the session, and returns the token.
 * 
 * @return string CSRF token
 */
function getCsrfToken(): string
{
    $token = bin2hex(random_bytes(16));
    $_SESSION['_csrf'] = hash('sha256', $token);
    return $token;
}

/**
 * Generate a random CSRF token, save it to the session, and output an HTML input element containing the token.
 */
function csrfField()
{
    echo '<input type="hidden" name="_csrf" value="' . getCsrfToken() . '" />';
}

/**
 * Verify CSRF token from the session and the request in `$_POST['_csrf']` or `$_SERVER["HTTP_X_CSRF_TOKEN"]` or `$_COOKIE['CSRF-Token']`.
 *
 * @param bool $removeTokenFromSession [option]
 * @throws \Shared\Exceptions\BadRequestException         If CSRF token is not found on the request parameter.
 * @throws \Shared\Exceptions\ValidationException         If CSRF token in the request does not matche the token in the session.
 * @throws \Shared\Exceptions\SessionTimeoutException     If CSRF token for the session is not found.
 * @throws \LogicException              If CSRF token for the session is not string.
 */
function verifyCsrfToken(bool $removeTokenFromSession = false)
{
    // Check if CSRF token is set in the request.
    if (isset($_POST['_csrf'])) {
        $token = $_POST['_csrf'];
    } elseif (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'];
    } elseif (isset($_COOKIE['CSRF-Token'])) {
        $token = $_COOKIE['CSRF-Token'];
    } else {
        throw new \Shared\Exceptions\BadRequestException('CSRF token was not found on the request parameter.');
    }

    // Check if CSRF token is set in the session.
    if (!isset($_SESSION['_csrf'])) {
        throw new \Shared\Exceptions\SessionTimeoutException('Your session has expired.');
    }

    // Get CSRF token from the session.
    $sessionToken = $_SESSION['_csrf'];
    if (!is_string($sessionToken)) {
        throw new \LogicException('CSRF token for session is not string.');
    }

    // Verify that CSRF token in the request matches the token in the session.
    $result = is_string($token) && hash_equals($sessionToken, hash('sha256', $token));
    if (!$result) {
        throw new \Shared\Exceptions\ValidationException('Invalid CSRF token');
    }

    if ($removeTokenFromSession) {
        unset($_SESSION['_csrf']);
    }
}

/**
 * Outputs a string or number after HTML-escaping it.
 *
 * @param mixed $string The string or number to output.
 *                      Note: If the argument is not a string or number, it will not be outputted.
 * @return string
 */
function h(mixed $string): string
{
    if (is_string($string) || is_int($string) || is_float($string)) {
        return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
    }

    return '';
}

/**
 * Remove all zero-width characters from a string.
 *
 * This function removes all zero-width spaces, zero-width non-joiners, and zero-width no-break spaces
 * from the input string. It also normalizes the input string to Unicode Normalization Form KC (Compatibility Composition).
 *
 * @param string $inputString The input string to be processed.
 * @return string The input string without any zero-width characters.
 */
function removeAllZeroWidthCharacters(string $inputString): string
{
    // Normalize the string to Unicode Normalization Form KC (Compatibility Composition).
    $normalizedString = \Normalizer::normalize($inputString, \Normalizer::FORM_KC);

    // Use a regular expression to remove all zero-width characters (U+200B to U+200D, U+FEFF, U+200C).
    $cleanedString = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}\x{200C}]/u', '', $normalizedString);

    return $cleanedString;
}

/**
 * Removes non-ASCII characters from the given string.
 *
 * @param string $string The input string to be cleaned.
 * @return string        The cleaned string with only ASCII characters.
 */
function sanitizeString(string $string): string
{
    return preg_replace('/[^(\x20-\x7F)]*/', '', $string);
}

/**
 * Get the class name from a fully qualified class name.
 *
 * @param string|object $fullyQualifiedClassName Fully qualified class name (including namespace).
 * @return string Class name extracted from the fully qualified name.
 */
function getClassSimpleName(string|object $fullyQualifiedClassName): string
{
    if (!is_string($fullyQualifiedClassName)) {
        $fullyQualifiedClassName = get_class($fullyQualifiedClassName);
    }

    return substr($fullyQualifiedClassName, strrpos($fullyQualifiedClassName, '\\') + 1);
}

/**
 * Prints a variable in a preformatted way.
 *
 * @param mixed $var The variable to print.
 *
 * @return void
 *
 * * **Example :** 
 * ```php
 * pre_var_dump($array);
 * ```
 * ```html
 * 
 * Output:
 * <pre>
 * array(
 *  [0] => "foo",
 *  [1] => "bar",
 * )
 * </pre>
 * ```
 */
function pre_var_dump($var)
{
    echo "<pre>";
    var_dump($var);
    echo "</pre>";
}

/**
 * Returns the elapsed time since a specific point in time, in milliseconds.
 *
 * @param float $start The start time. If null, the current microtime is used.
 *
 * @return float The elapsed time in milliseconds.
 *
 * **Example :** 
 * ```php
 * $start = getScriptExecutionTime();
 *
 * // Do something
 * $elapsed = getScriptExecutionTime($start);
 *
 * echo $elapsed; // in milliseconds
 * ```
 */
function getScriptExecutionTime(?float $start = null): float
{
    if ($start === null) {
        $start = microtime(true);
    }

    return (float) round(microtime(true) - $start, 3);
}

/**
 * Calculate a base62 hash from the input string using the specified algorithm.
 *
 * @param string $str The input string.
 * @param string $alg The hashing algorithm to use (default: 'fnv1a64').
 * @return string The calculated base62 hash.
 */
function base62Hash(string $str, string $alg = 'fnv1a64'): string
{
    $hex = hash($alg, $str);
    $charset = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $base = strlen($charset);
    $encoded = '';

    // Split the input hexadecimal string into chunks of 8 characters each
    $chunks = str_split($hex, 8);
    foreach ($chunks as $chunk) {
        $num = 0;

        // Convert hexadecimal to decimal
        for ($i = 0, $len = strlen($chunk); $i < $len; $i++) {
            $num = $num * 16 + hexdec($chunk[$i]);
        }

        // Convert decimal to base62
        while ($num > 0) {
            $remainder = $num % $base;
            $num = intdiv($num, $base);
            $encoded = $charset[$remainder] . $encoded;
        }
    }

    return $encoded;
}

/**
 * Check if the given future UNIX timestamp is within half of the specified expiration term.
 *
 * @param int $futureUnixTime The UNIX timestamp representing a future point in time.
 * @param int $expirationTimeInSeconds The expiration term in seconds.
 * @return bool Returns true if the future timestamp is within half of the expiration term, otherwise false.
 */
function isWithinHalfExpires(int $futureUnixTime, int $expirationTimeInSeconds): bool
{
    $currentTime = time(); // Current UNIX timestamp
    $halfSeconds = $expirationTimeInSeconds / 2; // Half of the expiration term in seconds

    return ($futureUnixTime - $currentTime) <= $halfSeconds;
}

/**
 * Safely rewrites the content of the specified file by first writing to a temporary file
 * and then renaming it to the target file. It ensures that the target file always
 * contains complete and uncorrupted data.
 *
 * @param string $targetFile The path to the file that should be rewritten.
 * @param string $content The new content to write to the file.
 * @return void Throws an Exception if the rename operation fails.
 * @throws RuntimeException if the temporary file cannot be renamed to the target file.
 */
function safeFileRewrite(string $targetFile, string $content, int $permissions = 0777)
{
    $tempFile = tempnam(sys_get_temp_dir(), 'TMP_');

    file_put_contents($tempFile, $content);

    if (!chmod($tempFile, $permissions)) {
        throw new \RuntimeException("Could not set the desired file permissions on the temporary file.");
    }

    if (!rename($tempFile, $targetFile)) {
        throw new \RuntimeException("Could not rename the temporary file to the target file.");
    }
}

/**
 * Generates a versioned file URL based on the provided file path. If the file exists, a URL with a version query parameter is returned.
 *
 * @param string $filePath The path to the file, relative to the public directory.
 * @param ?string $publicDir [optional] The public directory path. Default is the constant MimimalCmsConfig::$publicDir.
 * @param ?string $urlRoot   [optional] The root of the url. Default is `MimimalCmsConfig::$urlRoot`.
 * 
 * @return string The versioned file URL.
 * 
 * **Example:**   
 * Input: `fileUrl("css/styles.css")` 
 * If the file "css/styles.css" exists in the public directory, and its modification time is 1609459200, the output will be: 
 * `"http://example.com/css/styles.css?v=1609459200"`
 * 
 * Input: `fileUrl("js/script.js")` 
 * If the file "js/script.js" exists in the public directory, and its modification time is 1609459300, the output will be: 
 * `"http://example.com/js/script.js?v=1609459300"`
 * 
 * Input: `fileUrl("images/logo.png")` 
 * If the file "images/logo.png" doesn't exist in the public directory, the output will be: 
 * `"http://example.com/images/logo.png"`
 */
function fileUrl(string $filePath, ?string $publicDir = null, ?string $urlRoot = null): string
{
    $publicDir = $publicDir ?? MimimalCmsConfig::$publicDir;
    $urlRoot = $urlRoot ?? MimimalCmsConfig::$urlRoot;

    $filePath = "/" . ltrim($filePath, "/");
    $fullFilePath = $publicDir . $filePath;

    if (!file_exists($fullFilePath)) {
        return Shadow\Kernel\Dispatcher\ReceptionInitializer::getDomainAndHttpHost($urlRoot) . $filePath;
    }

    return Shadow\Kernel\Dispatcher\ReceptionInitializer::getDomainAndHttpHost($urlRoot) . $filePath . '?v=' . filemtime($fullFilePath);
}

/**
 * Display multiple variables' values in the console for debugging purposes.
 *
 * @param mixed ...$vars The variables to display.
 */
function debug(...$vars)
{
    foreach ($vars as $var) {
        echo "\n";
        print_r(var_export($var));
    }

    echo "\n";
}

function stringToView(string $str): Shadow\Kernel\View
{
    $view = new \Shadow\Kernel\View;
    $view->renderCache = $str;
    return $view;
}

/**
 * Save serialized value to a file.
 *
 * @param string $path The name of the file to save the serialized array to.
 * @param mixed $value The value to be serialized and saved.
 * @throws \RuntimeException If there is an issue with file writing.
 */
function saveSerializedFile(string $path, mixed $value): void
{
    $data = gzencode(serialize($value));
    safeFileRewrite($path, $data);
}

/**
 * Retrieve and unserialize value from a file.
 *
 * @param string $path The name of the file.
 * @return mixed The unserialized value, or false if an invalid file or error occurs.
 */
function getUnserializedFile(string $path): mixed
{
    if (!file_exists($path)) {
        return false;
    }

    $data = file_get_contents($path);
    if ($data === false) {
        return false;
    }

    return unserialize(gzdecode($data));
}

/**
 * Delete a file from the storage directory.
 *
 * @param string $filename The name of the file to be deleted.
 * @param bool $fullPath [optional] Whether $filename is a full path. Default is false.
 * @return bool True if the file was successfully deleted, false if the file does not exist.
 */
function deleteStorageFile(string $filename, bool $fullPath = false): bool
{
    $path = $fullPath === false ? (__DIR__ . '/../storage/' . $filename) : $filename;

    if (!file_exists($path)) {
        return false;
    }

    unlink($path);

    return true;
}

/**
 * Delete all files matching a specified pattern from the storage directory.
 *
 * @param string $path The relative path within the storage directory, or an empty string to target the root storage directory.
 * @param bool $fullPath [optional] Whether $path is a full path. Default is false.
 * @throws \LogicException If an attempt to delete the root directory is detected.
 * @return void
 */
function deleteStorageFileAll(string $path, bool $fullPath = false): void
{
    if ($path === '') {
        throw new \LogicException('An exception was thrown because deletion of the root directory was detected.');
    }

    if ($fullPath === false) {
        $path = "/" . ltrim(rtrim($path, "/"), "/");
        $path = __DIR__ . '/../storage' . $path . '/*.*';
    } else {
        $path = rtrim($path, "/");
        $path = $path . '/*.*';
    }

    array_map('unlink', glob($path) ?: []);
}

/**
 * Get a list of files and directories in the storage directory matching a specified pattern.
 *
 * @param string $path The relative path within the storage directory.
 * @param string $pattern [optional] The pattern to match. Default is '/*.*'.
 * @param bool $fullPath [optional] Whether $path is a full path. Default is false.
 * @return array An array of file and directory names in the specified path that match the pattern.
 */
function getStorageFileList(string $path, string $pattern = '/*.*', bool $fullPath = false): array
{
    if (!$fullPath) {
        $storagePath = __DIR__ . '/../storage';
        $path = ($path !== '') ? "/" . ltrim($path, "/") : '';
        $path = $storagePath . $path;
    }

    $listPath = $path . $pattern;
    $list = glob($listPath);
    $result = [];

    foreach ($list as $value) {
        if (is_file($value)) {
            if ($fullPath) {
                $result[] = $value;
            } else {
                $result[] = str_replace($storagePath . "/", '', $value);
            }
        } else {
            $subDirectory = str_replace($storagePath . "/", '', $value);
            $result[$subDirectory] = getStorageFileList($subDirectory, $pattern, $fullPath);
        }
    }

    natsort($result);
    return $result;
}

/**
 * Create a directory if it does not exist.
 *
 * @param string $directory The directory path.
 * @param int $permissions [optional] The mode, default is 0777 (widest possible access).
 * @param bool $recursive [optional] Allow creating nested directories, default is true.
 * @throws \RuntimeException If there is an issue with directory creation.
 */
function mkdirIfNotExists(string $directory, int $permissions = 0777, bool $recursive = true): void
{
    try {
        if (!is_dir($directory)) {
            if (!mkdir($directory, $permissions, $recursive) && !is_dir($directory)) {
                throw new \RuntimeException('Failed to create directory.');
            }
        }
    } catch (\ErrorException $e) {
        throw new \RuntimeException('Error while creating directory: ' . $e->getMessage());
    }
}

/**
 * Recursively deletes a directory and all its contents.
 *
 * @param string $dir The path to the directory to be deleted.
 * @return bool Returns true if the directory is successfully deleted, false otherwise.
 */
function deleteDirectory(string $dir): bool
{
    if (!file_exists($dir)) {
        return true;
    }

    if (!is_dir($dir) || is_link($dir)) {
        return unlink($dir);
    }

    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') {
            continue;
        }

        $itemPath = $dir . '/' . $item;
        if (!deleteDirectory($itemPath)) {
            chmod($itemPath, 0777);
            if (!deleteDirectory($itemPath)) {
                return false;
            }
        }
    }

    return rmdir($dir);
}

/**
 * Retrieves an iterator containing all files with a specific extension from
 * the given directory and its subdirectories.
 *
 * @param string $dir The directory path to search in.
 * @param string $ext The target file extension to look for without the dot ('.').
 * 
 * @return \CallbackFilterIterator An iterator of \SplFileInfo objects for files matching the extension.
 * 
 * @throws \UnexpectedValueException
 * 
 * - Example
 * ```php
 * $files = getFilesWithExtension('/path/to/directory', 'txt');
 * foreach ($files as $file) {
 *     // Prints the path to each .txt file found.
 *     echo $file->getRealPath() . PHP_EOL;
 * }
 * ```
 *
 * Note: This function throws a UnexpectedValueException if a directory cannot be accessed.
 */
function getFilesWithExtension(string $dir, string $ext): \CallbackFilterIterator
{
    // Create a recursive directory iterator to traverse the directory
    $directory = new \RecursiveDirectoryIterator($dir);

    // Wrap the directory iterator in a recursive iterator to iterate over each item recursively
    $iterator = new \RecursiveIteratorIterator($directory);

    // Define a filter using an arrow function to select files with the specified extension
    $filter = fn(\SplFileInfo $file) => !$file->isDir() && $file->getExtension() === $ext;

    // Return a filtered iterator containing files matching the extension
    return new \CallbackFilterIterator($iterator, $filter);
}
