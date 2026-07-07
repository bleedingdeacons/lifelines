<?php

declare(strict_types=1);

namespace LifeLines\Core;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use Unity\Core\Interfaces\Container;

/**
 * LifeLines Service Provider
 *
 * Registers all LifeLines services in Unity's dependency container.
 * Follows the same pattern as Unity\Core\UnityServiceProvider — add
 * $container->register(SomeService::class, fn (ContainerInterface $c) => …)
 * bindings inside the register* helpers as the plugin grows.
 */
class LifeLinesServiceProvider
{
    /**
     * Register all LifeLines services in the container.
     *
     * @param Container $container The Unity dependency container
     * @return void
     */
    public function register(Container $container): void
    {
        $this->registerManagers($container);
    }

    /**
     * Register non-admin managers (always loaded).
     */
    private function registerManagers(Container $container): void
    {
        // TODO: register LifeLines managers here, e.g.
        // $container->register(ExampleManager::class, function (ContainerInterface $c) {
        //     return new ExampleManager();
        // });
    }
}
