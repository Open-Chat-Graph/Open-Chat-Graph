<?php

declare(strict_types=1);

namespace Shadow\Kernel\Dispatcher;

use Shadow\Kernel\Reception;

/**
 * @author mimimiku778 <0203.sub@gmail.com>
 * @license https://github.com/mimimiku778/MimimalCMS/blob/master/LICENSE.md
 */
abstract class AbstractInvoker
{
    protected ConstructorInjectionInterface $ci;

    public function __construct()
    {
        $this->ci = new ConstructorInjection;
    }

    /**
     * Get the arguments for the given closure function and return an array of both the closure arguments and validated input data.
     * 
     * @throws \BadMethodCallException If the method is private.
     */
    protected function getMethodArgs(string $className, string $methodName): array
    {
        $reflectionMethod = new \ReflectionMethod($className, $methodName);
        if (!$reflectionMethod->isPublic()) {
            throw new \BadMethodCallException('Method is private');
        }

        $methodArgs = [];
        foreach ($reflectionMethod->getParameters() as $param) {
            $paramType = $param->getType();

            if ($paramType === null || $paramType->isBuiltin()) {
                $methodArgs[] = Reception::$inputData[$param->name] ?? null;
                continue;
            }

            $paramClassName = $paramType->getName();
            
            // Check container bindings first, just like ConstructorInjection::getMethodArgs does
            if (isset(ConstructorInjection::$container[$paramClassName])) {
                $methodArgs[] = $this->ci->constructorInjection($paramClassName);
                continue;
            }
            
            if (!class_exists($paramClassName)) {
                $paramClassName = $this->ci->resolveInterfaceToClass($paramClassName);
            }

            $methodArgs[] = $this->ci->constructorInjection($paramClassName);
        }

        return $methodArgs;
    }

    /**
     * Get the arguments for the given closure function and return an array of both the closure arguments and validated input data.
     */
    protected function getClosureArgs(\Closure $closure): array
    {
        $reflection = new \ReflectionFunction($closure);

        $closureArgs = [];
        foreach ($reflection->getParameters() as $param) {
            $paramType = $param->getType();

            if ($paramType === null || $paramType->isBuiltin()) {
                $closureArgs[] = Reception::$inputData[$param->name] ?? null;
                continue;
            }

            $paramClassName = $paramType->getName();
            
            // Check container bindings first, just like ConstructorInjection::getMethodArgs does
            if (isset(ConstructorInjection::$container[$paramClassName])) {
                $closureArgs[] = $this->ci->constructorInjection($paramClassName);
                continue;
            }
            
            if (!class_exists($paramClassName)) {
                $paramClassName = $this->ci->resolveInterfaceToClass($paramClassName);
            }

            $closureArgs[] = $this->ci->constructorInjection($paramClassName);
        }

        return $closureArgs;
    }
}
