<?php

declare(strict_types=1);

namespace Shadow\Kernel;

use Shadow\Kernel\RouteClasses\RouteDTO;
use Shadow\Kernel\Dispatcher\ReceptionInitializer;
use Shadow\Kernel\Dispatcher\RequestParser;
use Shadow\Kernel\Dispatcher\Routing;
use Shadow\Kernel\Dispatcher\ControllerInvoker;
use Shadow\Kernel\Dispatcher\MiddlewareInvoker;
use Shadow\Kernel\Dispatcher\RouteCallbackInvoker;
use Shadow\Kernel\Utility\KernelUtility;
use Shared\Exceptions\NotFoundException;

/**
 * @author mimimiku778 <0203.sub@gmail.com>
 * @license https://github.com/mimimiku778/MimimalCMS/blob/master/LICENSE.md
 */
class Kernel
{
    protected RouteDTO $routeDto;

    public function handle(RouteDTO $routeDto)
    {
        $this->routeDto = $routeDto;
        $this->parseRequest();

        if ($this->routing()) {
            $this->validateRequest();
            $this->callMiddleware();
            $this->callRouteCallback();
            $this->callController();
            $this->handleResponse();
        } else {
            $this->validateRequest();
            $this->callMiddleware();
            $this->callRouteCallback();
        }
    }

    /**
     * @throws \InvalidArgumentException
     * @throws \LogicException
     */
    protected function parseRequest()
    {
        $request = new RequestParser;
        $uri = KernelUtility::getCurrentUri() ?: '/';
        $request->parse($this->routeDto, $uri);
    }

    /**
     * @throws NotFoundException
     * @throws MethodNotAllowedException
     */
    protected function routing(): bool
    {
        $routing = new Routing;
        $routing->setRouteDto($this->routeDto);

        $result = true;
        try {
            $routing->resolveController();
        } catch (NotFoundException $e) {
            if ($this->routeDto->isDefinedRoute()) {
                $result = false;
            } else {
                throw $e;
            }
        }

        $routing->validateAllowedMethods();

        return $result;
    }

    /**
     * @throws NotFoundException        If the request is GET
     * @throws ValidationException      If the request is other than GET
     * @throws \InvalidArgumentException
     */
    protected function validateRequest()
    {
        $reception = new ReceptionInitializer;
        $reception->init($this->routeDto);
        $reception->callRequestValidator();
    }

    /**
     * @throws \InvalidArgumentException
     */
    protected function callMiddleware()
    {
        if (!$this->routeDto->existsMiddleware()) {
            return;
        }

        $middleware = new MiddlewareInvoker;
        $middleware->invoke($this->routeDto);
    }

    protected function callRouteCallback()
    {
        $routeCallback = $this->routeDto->getRouteCallback();
        if ($routeCallback instanceof \Closure) {
            $routeCallbackInvoker = new RouteCallbackInvoker;
            $routeCallbackInvoker->invoke($this->routeDto, $routeCallback);
        } elseif ($routeCallback instanceof \Shadow\Kernel\ResponseInterface) {
            $routeCallback->send();
        }
    }

    /**
     * @throws \InvalidArgumentException
     */
    protected function callController()
    {
        $controller = new ControllerInvoker;
        $controller->invoke($this->routeDto);
    }

    /**
     * @throws NotFoundException        If the request is GET
     * @throws BadRequestException      If the request is other than GET
     */
    protected function handleResponse()
    {
        $response = new ResponseHandler;
        $response->handleResponse($this->routeDto->contlollerResponse);
    }
}
