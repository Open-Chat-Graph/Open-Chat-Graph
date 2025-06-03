<?php

namespace ExceptionHandler;

use App\Exceptions\Handlers\ApplicationExceptionHandler;
use Shared\MimimalCmsConfig;

/**
 * MimimalCMS v1
 * 
 * @author mimimiku778 <0203.sub@gmail.com>
 * @license https://github.com/mimimiku778/MimimalCMS/blob/master/LICENSE.md
 */

/**
 * Registers ExceptionHandler::handleException() as the global exception handler.
 */
set_exception_handler('\ExceptionHandler\ExceptionHandler::handleException');

/**
 * Sets the error reporting level to include all errors.
 */
error_reporting(E_ALL);

/**
 * Registers a custom error handler that throws exceptions for all errors.
 */
set_error_handler(function ($no, $msg, $file, $line) {
    if (error_reporting() !== 0) {
        throw new \ErrorException($msg, 0, $no, $file, $line);
    }
});

/**
 * Registers a shutdown function that checks for fatal errors and
 * passes them to ExceptionHandler::handleException().
 */
register_shutdown_function(function () {
    $last = error_get_last();
    if (
        isset($last['type'])
        && boolval($last['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR))
    ) {
        ExceptionHandler::handleException(
            new \ErrorException($last['message'], 0, $last['type'], $last['file'], $last['line'])
        );
    }
});

/**
 * Exception handling and configuration class for MimimalCMS.
 * 
 * @author mimimiku778 <0203.sub@gmail.com>
 * @license https://github.com/mimimiku778/MimimalCMS/blob/master/LICENSE.md
 */
class ExceptionHandler
{
    /**
     * Handles the specified \Throwable instance.
     *
     * @param \Throwable $e The \Throwable instance to handle.
     */
    public static function handleException(\Throwable $e)
    {
        $appHandlerClass = ApplicationExceptionHandler::class;
        if (class_exists($appHandlerClass) && isset($appHandlerClass::$exceptionMap)) {
            $className = get_class($e);
            if (array_key_exists($className, $appHandlerClass::$exceptionMap)) {
                \App\Exceptions\Handlers\ApplicationExceptionHandler::handleException($e, $className);
                return;
            }
        }

        // Determine whether to show detailed error information
        $configClass = MimimalCmsConfig::class;
        $bool = class_exists($configClass) && ($configClass::$exceptionHandlerDisplayBeforeObClean ?? false);
        if ($bool && ob_get_length() !== false && ob_get_length() > 0) {
            ob_clean();
        }

        // Handle a TestException instance
        if ($e instanceof \Shared\Exceptions\TestException) {
            self::errorResponse($e, mb_convert_encoding($e->getMessage(), 'UTF-8'), 500, ($e->getCode() ? true : false), 'Internal Server Error😥', true);
            return;
        }

        if (!class_exists($configClass) || !$error = ($configClass::$httpErrors[get_class($e)] ?? [])) {
            // Handle an unhandled exception
            self::response500($e);
            return;
        }

        self::errorResponse($e, mb_convert_encoding($e->getMessage(), 'UTF-8'), ...$error);
    }

    private static function response500(\Throwable $e, bool $log = true)
    {
        self::errorResponse($e, 'please try again later', 500, $log, 'Internal Server Error😥');

        $configClass = MimimalCmsConfig::class;
        $adminToolClass = \App\Services\Admin\AdminTool::class;
        if (
            class_exists($configClass)
            && !($configClass::$exceptionHandlerDisplayErrorTraceDetails ?? false)
            && class_exists($adminToolClass)
        ) {
            try {
                $adminToolClass::sendDiscordNotify($e->__toString() . "\nIP: " . getIp() . "\nUA: " . getUA());
            } catch (\Throwable $exception) {
                self::errorLog($exception);
            }
        }
    }

    /**
     * Return an error response with appropriate status code and message
     *
     * @param \Throwable $e                 The exception object
     * @param string    $message           The error message to display
     * @param int       $httpCode          The HTTP status code to return
     * @param string    $httpStatusMessage The HTTP status message to return
     */
    private static function errorResponse(
        \Throwable $e,
        string $message,
        int $httpCode,
        bool $log,
        string $httpStatusMessage,
        bool $isTest = false
    ) {
        if ($log && !$isTest) {
            self::errorLog($e);
            $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        } elseif ($log && $isTest) {
            $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        } else {
            $message = '';
        }

        if (!isset($_SERVER['REQUEST_URI'])) {
            print_r($e->__toString());
            return;
        }

        // Set the HTTP response code
        http_response_code($httpCode);

        // Determine whether to show detailed error information
        $className = MimimalCmsConfig::class;
        $showErrorTraceFlag = class_exists($className) && ($className::$exceptionHandlerDisplayErrorTraceDetails ?? false);

        // If the request is JSON, return a JSON response
        if (self::isJsonRequest()) {
            self::jsonResponse([
                'error' => [
                    'code' => $e->getCode(),
                    'message' => $showErrorTraceFlag ? self::getDetailsMessage($e) : $message
                ]
            ]);
            return;
        }

        // If the request is not JSON, prepare the error message for display
        $detailsMessage = $showErrorTraceFlag ? (get_class($e) . ": " . self::getDetailsMessage($e)) : $message;
        $detailsMessage = htmlspecialchars($detailsMessage, ENT_QUOTES, 'UTF-8');

        // If the error page can be displayed, show it
        if (!self::showErrorPage($httpCode, $httpStatusMessage, $detailsMessage)) {
            // Otherwise, output the error message
            echo "{$httpCode} {$httpStatusMessage}<br>";
            echo "<pre>" . $detailsMessage . "</pre>";
        }
    }

    /**
     * Returns a string that contains a detailed error message with information 
     * about the file, line, and trace of the \Throwable instance.
     *
     * @param \Throwable $e The \Throwable instance to get the detailed error message from.
     * @return string      The detailed error message.
     */
    private static function getDetailsMessage(\Throwable $e): string
    {
        return mb_convert_encoding($e->getMessage(), 'UTF-8')
            . " in "
            . $e->getFile() . '(' . $e->getLine() . ')'
            . ": \n"
            . $e->getTraceAsString();
    }

    /**
     * Attempts to show a custom error page based on the HTTP code.
     * If a custom error page is not found, falls back to the generic error page.
     *
     * @param int    $httpCode          The HTTP status code.
     * @param string $httpStatusMessage The HTTP status message.
     * @param string $detailsMessage    The details message to be displayed on the error page.
     * 
     * @return bool Whether a custom error page was found and displayed.
     */
    private static function showErrorPage(int $httpCode, string $httpStatusMessage, string $detailsMessage): bool
    {
        if (!class_exists(\Shared\MimimalCmsConfig::class) || !isset(\Shared\MimimalCmsConfig::$viewsDir)) {
            return false;
        }

        $viewsDir = \Shared\MimimalCmsConfig::$viewsDir;

        $filePath = $viewsDir . '/errors/' . $httpCode . '.php';
        if (file_exists($filePath)) {
            require_once $filePath;
            return true;
        }

        $filePath = $viewsDir . '/errors/error.php';
        if (file_exists($filePath)) {
            require_once $filePath;
            return true;
        }

        return false;
    }

    /**
     * Writes error messages to the error log file and exits.
     *
     * @param \Throwable $e
     */
    public static function errorLog(\Throwable $e)
    {
        // Get current date and time with timezone
        $time = date('Y-m-d H:i:s') . ' ' . date_default_timezone_get() . ': ';

        // Construct error message with class name, message and stack trace
        $message = sprintf(
            "%s: %s\n%s\n",
            get_class($e),
            $e->getMessage(),
            $e->getTraceAsString()
        );

        // Get request headers as string
        $headerString = implode("\n", array_map(function ($key, $val) {
            if (!is_string($val)) {
                $val = var_export($val, true);
            }
            $val = str_replace("\n", '', $val);
            return "{$key}: {$val}";
        }, array_keys($_SERVER), $_SERVER));


        $className = MimimalCmsConfig::class;
        if (!class_exists($className) || !isset($className::$exceptionLogDirectory)) {
            return;
        }

        // Write error log with timestamp, error message and request headers
        try {
            error_log("\n" . $time . "\n" . $message . $headerString . "\n", 3, $className::$exceptionLogDirectory);
        } catch (\Throwable $e) {
            return;
        }
    }

    private static function jsonResponse(array $data)
    {
        header("Content-Type: application/json; charset=utf-8");
        ob_start('ob_gzhandler');
        echo json_encode($data);
    }

    private static function isJsonRequest(): bool
    {
        $reception = \Shadow\Kernel\Reception::class;
        if (class_exists($reception) && $reception::$isJson) {
            return true;
        }

        return strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false;
    }
}
