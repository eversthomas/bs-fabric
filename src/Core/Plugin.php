<?php

declare(strict_types=1);

namespace BsFabric\Core;

use BsFabric\Database\Installer;
use BsFabric\Database\Migrator;
use BsFabric\Fields\FieldRegistry;

final class Plugin
{
    private static ?self $instance = null;

    private bool $initialized = false;

    private readonly ServiceContainer $container;

    private function __construct()
    {
        $this->container = new ServiceContainer();
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function init(): void
    {
        if ($this->initialized) {
            return;
        }

        $this->initialized = true;
        $this->registerServices();
        $this->registerHooks();
    }

    public function container(): ServiceContainer
    {
        return $this->container;
    }

    private function registerServices(): void
    {
        $this->container->set(self::class, fn (): self => $this);
        $this->container->set(ServiceContainer::class, fn (): ServiceContainer => $this->container);
        $this->container->set(Migrator::class, fn (): Migrator => new Migrator());
        $this->container->set(
            Installer::class,
            fn (): Installer => new Installer($this->container->get(Migrator::class))
        );
        $this->container->set(
            FieldRegistry::SERVICE_ID,
            static fn (): FieldRegistry => new FieldRegistry()
        );
    }

    private function registerHooks(): void
    {
        add_action('plugins_loaded', [$this, 'onPluginsLoaded']);
    }

    public function onPluginsLoaded(): void
    {
        load_plugin_textdomain(
            'bs-fabric',
            false,
            dirname(plugin_basename(BS_FABRIC_FILE)) . '/languages'
        );

        $this->runMigrations();
    }

    public function activate(): void
    {
        $this->init();
        $this->runMigrations();
    }

    private function runMigrations(): void
    {
        $this->container->get(Migrator::class)->migrate(
            $this->container->get(Installer::class)
        );
    }

    public function deactivate(): void
    {
        // Keine Aktion in Session 01.
    }
}
