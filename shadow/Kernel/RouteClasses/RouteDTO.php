<?php

declare(strict_types=1);

namespace Shadow\Kernel\RouteClasses;

use Shadow\Kernel\ResponseInterface;

/**
 * @author mimimiku778 <0203.sub@gmail.com>
 * @license https://github.com/mimimiku778/MimimalCMS/blob/master/LICENSE.md
 */
class RouteDTO
{
    /**
     * `['routePath']`  
     */
    public array $routePathArray = [];

    /**
     * `['routePathArrayKey' => ['requestMethod' => ['parametarName' => Closure|ResponseInterface]]]`  
     */
    public array $routeValidatorArray = [];

    /**
     * `['routePathArrayKey' => ['requestMethod' => Closure|ResponseInterface]]`  
     */
    public array $routeCallbackArray = [];

    /**
     * `['routePathArrayKey' => ['requestMethod' => ['controllerClassName ', 'methodName']]]`  
     */
    public array $routeExplicitControllerArray = [];

    public array $routeFailsArray = [];

    public array $routeMiddlewareArray = [];

    public array $kernelMiddlewareArray = [];

    /**
     * `['currentPath']`  
     */
    public array $parsedPathArray;

    /**
     * `['currentURIParameta']`  
     */
    public array $paramArray;

    /**
     * `['currentAllowedRequestMethod']`  
     */
    public array|false $routeRequestMethodArray;

    /**
     * Current key of routePathArray
     */
    protected int|string $routeArrayKey;

    /**
     * `['currentControllerClassName']`  
     */
    public string $controllerClassName;

    /**
     * `['currentControllerMethodName']`  
     */
    public string $methodName;

    /**
     * `['currentRequestMethod']`  
     */
    public string $requestMethod;
    public bool $isJson;

    public mixed $contlollerResponse;

    public function __construct()
    {
        $this->isJson = strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false
            || strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
        $this->requestMethod = $_SERVER['REQUEST_METHOD'] ?? '';
    }

    public function setRouteArrayKey(int|string $key)
    {
        $this->routeArrayKey = $key;
    }

    /**
     * @return array|false `['controllerClassName ', 'methodName']`
     */
    public function getExplicitControllerArray(): array|false
    {
        return $this->routeExplicitControllerArray[$this->routeArrayKey][$this->requestMethod] ?? false;
    }

    /**
     * @return ResponseInterface|false|null
     */
    public function getFailsResponse(): ResponseInterface|false|null
    {
        return $this->routeFailsArray[$this->routeArrayKey][$this->requestMethod] ?? null;
    }

    /**
     * @return array|false `['parametarName' => $Closure]`
     */
    public function getValidater(): array|false
    {
        return $this->routeValidatorArray[$this->routeArrayKey][$this->requestMethod] ?? false;
    }

    /**
     * @return \Closure|ResponseInterface|false Callback function passed in the routing definition.
     */
    public function getRouteCallback(): \Closure|ResponseInterface|false
    {
        return $this->routeCallbackArray[$this->routeArrayKey][$this->requestMethod] ?? false;
    }

    /**
     * @return array `['middlewareName']`
     */
    public function getMiddleware(): array
    {
        $routeMiddlewareArray = $this->routeMiddlewareArray[$this->routeArrayKey][$this->requestMethod] ?? [];
        return array_merge($this->kernelMiddlewareArray, $routeMiddlewareArray);
    }

    /**
     * @return array `['middlewareName']`
     */
    public function existsMiddleware(): bool
    {
        return !empty($this->kernelMiddlewareArray) || isset($this->routeMiddlewareArray[$this->routeArrayKey][$this->requestMethod]);
    }

    public function isDefinedRoute(): bool
    {
        return is_int($this->routeArrayKey ?? null);
    }
}
