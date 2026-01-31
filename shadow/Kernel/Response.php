<?php

declare(strict_types=1);

namespace Shadow\Kernel;

/**
 * @author mimimiku778 <0203.sub@gmail.com>
 * @license https://github.com/mimimiku778/MimimalCMS/blob/master/LICENSE.md
 */
class Response implements ResponseInterface
{
    protected int $responseCode;
    protected ?string $url;
    protected mixed $jsonData;
    protected array $flashSession = [];
    protected ?array $errorArray = null;
    protected ?array $withInputExceptArray = null;

    public function __construct(int $responseCode, ?string $url = null, mixed $jsonData = null)
    {
        $this->responseCode = $responseCode;
        $this->url = $url;
        $this->jsonData = $jsonData;
    }

    public function with(string|array $key, mixed $value = ''): ResponseInterface
    {
        if (is_array($key)) {
            $this->flashSession = array_merge($this->flashSession, $key);
        } else {
            $this->flashSession[$key] = $value;
        }

        return $this;
    }

    public function withErrors(string $key, int $code = 0, string $message = ''): ResponseInterface
    {
        if ($this->errorArray === null) {
            $this->errorArray = [];
        }

        $this->errorArray[$key] = ['code' => $code, 'message' => $message];

        return $this;
    }

    public function withInput(string ...$exceptNames): ResponseInterface
    {
        $this->withInputExceptArray = $exceptNames;

        return $this;
    }
    
    public function send()
    {
        if (!isset($this->responseCode)) {
            throw new \LogicException('Response code is not defined');
        }

        $this->session();

        $this->header();
        $this->sendResponse();
    }

    protected function session()
    {
        if ($this->errorArray !== null) {
            foreach($this->errorArray as $key => $value) {
                Session::addError($key, ...$value);   
            }
        }

        if ($this->withInputExceptArray !== null) {
            Session::flashInput(...$this->withInputExceptArray);
        }

        if ($this->flashSession !== []) {
            Session::flash($this->flashSession);
        }
    }

    protected function header()
    {
        if (isset($this->jsonData)) {
            http_response_code($this->responseCode);
            header("Content-Type: application/json; charset=utf-8");
        }

        if (isset($this->url)) {
            header('Location: ' . $this->url, true, $this->responseCode);
            exit;
        }
    }

    protected function sendResponse()
    {
        if (isset($this->jsonData)) {
            ob_start('ob_gzhandler');
            echo json_encode($this->jsonData, JSON_THROW_ON_ERROR);
        }
    }

    /**
     * Get the response body content as a string.
     * For JSON responses, returns the JSON-encoded string.
     *
     * @return string The response body content.
     */
    public function getBody(): string
    {
        if (isset($this->jsonData)) {
            return json_encode($this->jsonData, JSON_THROW_ON_ERROR);
        }
        return '';
    }
}
