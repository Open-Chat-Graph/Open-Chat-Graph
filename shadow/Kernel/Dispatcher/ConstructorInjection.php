<?php

declare(strict_types=1);

namespace Shadow\Kernel\Dispatcher;

use Shared\MimimalCmsConfig;

/**
 * @author mimimiku778 <0203.sub@gmail.com>
 * @license https://github.com/mimimiku778/MimimalCMS/blob/master/LICENSE.md
 */
class ConstructorInjection implements ConstructorInjectionInterface
{
    public static array $container = [];
    protected array $injectionParameters;
    protected array $classMap;
    protected array $reflectionClasses;

    public function __construct(array $injectionParameters = [])
    {
        $this->classMap = MimimalCmsConfig::$constructorInjectionMap;
        $this->injectionParameters = $injectionParameters;
    }

    public function constructorInjection(string $className, bool $container = true): object
    {
        if ($container && isset(self::$container[$className])) {
            return $this->getInstance($className);
        }

        if (!class_exists($className)) {
            $concreteName = $this->resolveInterfaceToClass($className);
        } else {
            $concreteName = $className;
        }

        $reflectionClass = $this->getReflectionClass($concreteName);
        $constructor = $reflectionClass->getConstructor();

        if ($constructor === null) {
            return new $concreteName();
        }

        $methodArgs = $this->getMethodArgs($constructor);
        $resolvedInstances[$className] = $reflectionClass->newInstanceArgs($methodArgs);

        return $resolvedInstances[$className];
    }

    /**
     * Gets the resolved arguments for a given constructor.
     *
     * @param \ReflectionMethod $constructor The constructor to resolve dependencies for
     * 
     * @return array            An array of resolved dependencies for the constructor
     * 
     * @throws \ReflectionException
     */
    protected function getMethodArgs(\ReflectionMethod $constructor): array
    {
        $methodArgs = [];

        foreach ($constructor->getParameters() as $param) {
            $paramType = $param->getType();

            if (
                isset($this->injectionParameters[$param->name])
                || $paramType === null
                || $paramType->isBuiltin()
                || ($paramType instanceof \ReflectionNamedType && $paramType->allowsNull())
                || ($paramType instanceof \ReflectionUnionType
                    && array_reduce(
                        $paramType->getTypes(),
                        fn(bool $carry, \ReflectionType $type): bool => $carry || ($type instanceof \ReflectionNamedType && $type->allowsNull()),
                        false
                    ))
            ) {
                $methodArgs[] = $this->injectionParameters[$param->name] ?? null;
                continue;
            }

            $paramClassName = $paramType->getName();

            if (isset(self::$container[$paramClassName])) {
                $methodArgs[] = $this->getInstance($paramClassName);
                continue;
            }

            if (!class_exists($paramClassName)) {
                $paramClassName = $this->resolveInterfaceToClass($paramClassName);
            }

            $methodArgs[] = $this->constructorInjection($paramClassName);
        }

        return $methodArgs;
    }

    public function resolveInterfaceToClass(string $interfaceName): string
    {
        if (!isset($this->classMap[$interfaceName])) {
            throw new \LogicException("No implementation found for interface '{$interfaceName}'");
        }

        $className = $this->classMap[$interfaceName];

        if (!class_exists($className)) {
            throw new \LogicException("Class '{$className}' not found");
        }

        return $className;
    }

    /**
     * Gets a ReflectionClass instance for a given class name.
     *
     * @param string $className The name of the class to get a ReflectionClass instance for
     * @return \ReflectionClass A ReflectionClass instance for the given class
     * 
     * @throws \ReflectionException
     */
    protected function getReflectionClass(string $className): \ReflectionClass
    {
        if (!isset($this->reflectionClasses[$className])) {
            $this->reflectionClasses[$className] = new \ReflectionClass($className);
        }

        return $this->reflectionClasses[$className];
    }

    public function register(string $className, \Closure|string|null $concrete = null, $singleton = false): void
    {
        if (!$singleton) {
            self::$container[$className] = ['concrete' => $concrete, 'singleton' => false];
        } else {
            self::$container[$className] = ['concrete' => $concrete, 'singleton' => ['flag' => false]];
        }
    }

    /**
     * Retrieve an instance from the DI container.
     *
     * @param string $className The service name
     * @return object The instance
     * 
     * @throws LogicException If Closure return value is not an object.
     */
    protected function getInstance(string $className): object
    {
        $element = self::$container[$className];

        if ($element['singleton']['flag'] ?? null) {
            return $element['concrete'];
        }

        if ($element['concrete'] instanceof \Closure) {
            $concrete = $element['concrete']();

            if (!is_object($concrete)) {
                throw new \LogicException("Closure return value is not an object");
            }
        } elseif ($element['concrete'] !== null) {
            $concrete = $this->constructorInjection($element['concrete'], container: false);
        } else {
            $concrete = $this->constructorInjection($className, container: false);
        }

        if ($element['singleton']) {
            self::$container[$className]['concrete'] = $concrete;
            self::$container[$className]['singleton']['flag'] = true;
        }

        return $concrete;
    }
}
