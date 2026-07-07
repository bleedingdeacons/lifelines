<?php

declare(strict_types=1);

namespace LifeLines;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use LifeLines\Core\LifeLinesServiceProvider;
use Psr\Container\ContainerInterface;
use Unity\Core\Interfaces\Container;

use RuntimeException;

/**
 * Main LifeLines Plugin Class
 *
 * Orchestrates the plugin lifecycle: service registration and admin
 * initialisation. Service registration is delegated to
 * LifeLinesServiceProvider, following the same pattern as
 * Unity\Core\UnityServiceProvider.
 */
class Plugin
{
    use \LifeLines\Logger\HasLogger;

    protected static function logChannel(): string
    {
        return 'lifelines';
    }

    private static ?ContainerInterface $container = null;
    private static bool $initialized = false;

    /**
     * Initialize the plugin.
     *
     * @param Container $unityContainer The Unity dependency container
     */
    public static function init(Container $unityContainer): void
    {
        if (self::$initialized) {
            return;
        }

        self::$container = $unityContainer;

        (new LifeLinesServiceProvider())->register($unityContainer);

        self::$initialized = true;

        // TODO: resolve and boot managers / admin services here as the plugin
        // grows, e.g. self::$container->get(SomeManager::class);

        self::logDebug('Initialised', ['version' => defined('LIFELINES_VERSION') ? LIFELINES_VERSION : 'unknown']);
    }

    /**
     * Get the dependency container.
     *
     * @return ContainerInterface
     * @throws RuntimeException If the plugin is not initialized
     */
    public static function getContainer(): ContainerInterface
    {
        if (self::$container === null) {
            throw new RuntimeException('LifeLines Plugin not initialized');
        }
        return self::$container;
    }
}
