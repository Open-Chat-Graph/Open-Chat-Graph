<?php

namespace Shadow\Kernel\Dispatcher;

/**
 * @author mimimiku778 <0203.sub@gmail.com>
 * @license https://github.com/mimimiku778/MimimalCMS/blob/master/LICENSE.md
 */
interface ConstructorInjectionInterface
{
    /**
     * Resolves a class constructor's dependencies recursively through constructor injection.
     *
     * @param string $className          The name of the class to resolve
     * 
     * @return object The resolved instance
     * 
     * @throws \ReflectionException
     */
    public function constructorInjection(string $className): object;

    /**
     * Resolves an interface name to a concrete class name
     *
     * @param string $interfaceName The name of the interface to resolve
     * 
     * @return string               The name of the concrete class that implements the interface
     * 
     * @throws \LogicException
     */
    public function resolveInterfaceToClass(string $interfaceName): string;

    /**
     * Registers a class.
     *
     * @param string $className The fully qualified class name.
     * @param \Closure|string|null $instance  The instance of the class to be stored.
     */
    public function register(string $className, \Closure|string|null $concrete = null, $singleton = false): void;
}
