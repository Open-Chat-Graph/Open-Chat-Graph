<?php

declare(strict_types=1);

namespace Shadow\Kernel\Dispatcher;

use Shared\Exceptions\UploadException;
use Shadow\Kernel\Reception;
use Shadow\Kernel\RouteClasses\RouteDTO;
use Shared\Exceptions\ValidationException;

/**
 * @author mimimiku778 <0203.sub@gmail.com>
 * @license https://github.com/mimimiku778/MimimalCMS/blob/master/LICENSE.md
 */
class ReceptionInitializer implements ReceptionInitializerInterface
{
    use TraitErrorResponse;

    protected RouteDTO $routeDto;

    public function init(RouteDTO $routeDto)
    {
        $this->routeDto = $routeDto;
        $this->routeFails = $routeDto->getFailsResponse();

        $this->getDomainAndHttpHost();
        Reception::$requestMethod =       $this->routeDto->requestMethod;
        Reception::$isJson =              $this->routeDto->isJson;

        Reception::$flashSession =        $this->getFlashSession();
        Reception::$inputData =           $this->parseRequestBody($this->routeDto->paramArray);
    }

    public static function getDomainAndHttpHost(string $urlRoot = URL_ROOT): string
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        Reception::$domain = $protocol . '://' . ($_SERVER['HTTP_HOST'] ?? '') . $urlRoot;

        return Reception::$domain;
    }

    /**
     * Get the flash session data if it exists and unset it from the session.
     *
     * @return array The flash session data
     */
    protected function getFlashSession(): array
    {
        if (isset($_SESSION[FLASH_SESSION_KEY_NAME])) {
            $session = $_SESSION[FLASH_SESSION_KEY_NAME];
            unset($_SESSION[FLASH_SESSION_KEY_NAME]);
        } else {
            $session = [];
        }

        return $session;
    }

    /**
     * Parses the request body and returns the input data.
     *
     * @return array The input data passed with the incoming request.
     */
    protected function parseRequestBody(): array
    {
        if (Reception::$requestMethod === 'GET') {
            return array_merge($_GET, $this->routeDto->paramArray);
        }

        if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
            return array_merge($_GET, $this->parseJson(), $this->routeDto->paramArray);
        }

        return array_merge($_GET, $_POST, $_FILES, $this->routeDto->paramArray);
    }

    protected function parseJson(): array
    {
        $requestBody = file_get_contents('php://input');
        if (!is_string($requestBody)) {
            return [];
        }

        $jsonArray = json_decode($requestBody, true);
        if (!is_array($jsonArray)) {
            return [];
        }

        return $jsonArray;
    }

    /**
     * Validate the incoming request using the built-in validators and the route callback validator, if available.
     * Store the validated input data in the static Reception::$inputData property.
     * 
     * @param array $inputArray
     * 
     * @return array Validated array
     * 
     * @throws InvalidArgumentException
     * @throws ValidationException
     * @throws NotFoundException
     */
    public function callRequestValidator()
    {
        if (!empty($_FILES)) {
            $this->checkUploadError();
        }

        $builtinValidators = $this->routeDto->getValidater();
        if ($builtinValidators !== false) {
            $validatedArray = $this->callBuiltinValidator($builtinValidators);
            Reception::$inputData = array_replace_recursive(Reception::$inputData, $validatedArray);
        }
    }

    protected function checkUploadError()
    {
        foreach ($_FILES as $key => $file) {
            try {
                $this->isUploadError($file);
            } catch (UploadException $e) {
                $errors[] = [
                    'key' => $key,
                    'code' => $e->getCode(),
                    'message' => $e->getMessage()
                ];
            }
        }

        if (!empty($errors)) {
            $this->errorResponse($errors, UploadException::class);
        }
    }

    protected function isUploadError(array $file)
    {
        $messages = '';

        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;

            case UPLOAD_ERR_INI_SIZE:
                $messages = 'The uploaded file is too large. Please upload a file smaller than ' . ini_get('upload_max_filesize') . '.';
                break;

            case UPLOAD_ERR_FORM_SIZE:
                $messages = 'The uploaded file is too large. Please upload a file smaller than ' . ($_POST['MAX_FILE_SIZE'] / 1000) . 'KB.';
                break;

            case UPLOAD_ERR_PARTIAL:
                $messages = 'Upload failed (communication error). Please try uploading again.';
                break;

            case UPLOAD_ERR_NO_TMP_DIR:
                $messages = 'Upload failed (system error). Please try uploading again.';
                break;
        }

        if ($messages) {
            throw new UploadException($messages, 999);
        }
    }

    protected function callBuiltinValidator(array $validators): array
    {
        // Initialize the result array and error array
        $validatedArray = [];
        $errors = [];

        // Iterate through the validators
        foreach ($validators as $key => $validator) {
            // Start with the original input data
            $data = Reception::$inputData;

            // Initialize the current level to the result array
            $currentLevel = &$validatedArray;

            // Split the key into property chain
            $propertyChain = explode('.', $key);

            // Get the last property in the chain
            $lastProperty = array_pop($propertyChain);

            // Traverse through each property in the chain
            foreach ($propertyChain as $property) {
                // Access the corresponding data property
                $data = &$data[$property] ?? null;

                // If the property doesn't exist in the current level, create an empty array
                if (!isset($currentLevel[$property])) {
                    $currentLevel[$property] = [];
                }

                // Move to the next level
                $currentLevel = &$currentLevel[$property];
            }

            try {
                // Validate the value using the specified validator
                $validatedValue = $validator($data[$lastProperty] ?? null);
            } catch (ValidationException $e) {
                // If a validation exception is caught, record the error details
                $errors[] = [
                    'key' => $key,
                    'code' => $e->getCode(),
                    'message' => $e->getMessage()
                ];

                // Set the validated value to null
                $validatedValue = null;
            }

            // Set the validated value at the appropriate location in the result array
            $currentLevel[$lastProperty] = $validatedValue;
        }

        // If there are errors, handle the error response
        if (!empty($errors)) {
            $this->errorResponse($errors);
        }

        // Return the validated array
        return $validatedArray;
    }
}
