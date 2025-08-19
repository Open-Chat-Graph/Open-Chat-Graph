<?php

declare(strict_types=1);

namespace App\ServiceProvider\test;

use PHPUnit\Framework\TestCase;
use Shadow\Kernel\Application;

abstract class AbstractServiceProviderTestCase extends TestCase
{
    protected Application $app;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->app = new Application();
    }
    
    /**
     * Test that a service provider correctly binds interfaces to implementations
     *
     * @param string $serviceProviderClass The service provider class to test
     * @param array $bindings Array of [interface => implementation] pairs to verify
     */
    protected function assertServiceProviderBindings(string $serviceProviderClass, array $bindings): void
    {
        $serviceProvider = new $serviceProviderClass();
        $serviceProvider->register();
        
        foreach ($bindings as $interface => $implementation) {
            $resolved = $this->app->make($interface);
            
            $this->assertInstanceOf(
                $implementation,
                $resolved,
                "Failed to bind {$interface} to {$implementation}"
            );
            
            $this->assertInstanceOf(
                $interface,
                $resolved,
                "Resolved class does not implement {$interface}"
            );
        }
    }
    
    /**
     * Test that singleton bindings work correctly
     *
     * @param string $serviceProviderClass The service provider class to test
     * @param array $singletons Array of interfaces that should be singletons
     */
    protected function assertServiceProviderSingletons(string $serviceProviderClass, array $singletons): void
    {
        $serviceProvider = new $serviceProviderClass();
        $serviceProvider->register();
        
        foreach ($singletons as $interface) {
            $instance1 = $this->app->make($interface);
            $instance2 = $this->app->make($interface);
            
            $this->assertSame(
                $instance1,
                $instance2,
                "{$interface} should be registered as a singleton"
            );
        }
    }
    
    /**
     * Test that non-singleton bindings create new instances
     *
     * @param string $serviceProviderClass The service provider class to test
     * @param array $nonSingletons Array of interfaces that should NOT be singletons
     */
    protected function assertServiceProviderNonSingletons(string $serviceProviderClass, array $nonSingletons): void
    {
        $serviceProvider = new $serviceProviderClass();
        $serviceProvider->register();
        
        foreach ($nonSingletons as $interface) {
            $instance1 = $this->app->make($interface);
            $instance2 = $this->app->make($interface);
            
            $this->assertNotSame(
                $instance1,
                $instance2,
                "{$interface} should NOT be registered as a singleton"
            );
        }
    }
}