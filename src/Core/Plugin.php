<?php

declare(strict_types=1);

namespace BsFabric\Core;

use BsFabric\Api\EntryQueryFactory;
use BsFabric\Api\EntryResolver;
use BsFabric\Api\PublicApi;
use BsFabric\Admin\Ajax\EntryAjax;
use BsFabric\Admin\Ajax\FieldAjax;
use BsFabric\Admin\Ajax\TemplateAjax;
use BsFabric\Admin\MenuManager;
use BsFabric\Admin\Screens\DocsScreen;
use BsFabric\Admin\Screens\EntryEditScreen;
use BsFabric\Admin\Screens\EntryListScreen;
use BsFabric\Admin\Screens\ExplorerScreen;
use BsFabric\Admin\Screens\FieldsScreen;
use BsFabric\Admin\Screens\TemplatesScreen;
use BsFabric\Database\Installer;
use BsFabric\Database\Migrator;
use BsFabric\Entries\EntryRepository;
use BsFabric\Fields\FieldRegistry;
use BsFabric\Fields\FieldRepository;
use BsFabric\Frontend\Renderer;
use BsFabric\Relations\RelationRepository;
use BsFabric\Templates\TemplateRepository;

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
        $this->container->set(
            FieldRepository::SERVICE_ID,
            fn (): FieldRepository => new FieldRepository($this->container->get(FieldRegistry::SERVICE_ID))
        );
        $this->container->set(
            FieldsScreen::class,
            fn (): FieldsScreen => new FieldsScreen(
                $this->container->get(FieldRepository::SERVICE_ID),
                $this->container->get(FieldRegistry::SERVICE_ID)
            )
        );
        $this->container->set(
            FieldAjax::class,
            fn (): FieldAjax => new FieldAjax($this->container->get(FieldRepository::SERVICE_ID))
        );
        $this->container->set(
            TemplateRepository::SERVICE_ID,
            fn (): TemplateRepository => new TemplateRepository($this->container->get(FieldRepository::SERVICE_ID))
        );
        $this->container->set(
            TemplatesScreen::class,
            fn (): TemplatesScreen => new TemplatesScreen(
                $this->container->get(TemplateRepository::SERVICE_ID),
                $this->container->get(FieldRepository::SERVICE_ID)
            )
        );
        $this->container->set(
            TemplateAjax::class,
            fn (): TemplateAjax => new TemplateAjax($this->container->get(TemplateRepository::SERVICE_ID))
        );
        $this->container->set(
            RelationRepository::SERVICE_ID,
            static fn (): RelationRepository => new RelationRepository()
        );
        $this->container->set(
            EntryRepository::SERVICE_ID,
            fn (): EntryRepository => new EntryRepository(
                $this->container->get(TemplateRepository::SERVICE_ID),
                $this->container->get(FieldRepository::SERVICE_ID),
                $this->container->get(FieldRegistry::SERVICE_ID),
                $this->container->get(RelationRepository::SERVICE_ID)
            )
        );
        $this->container->set(
            EntryListScreen::class,
            fn (): EntryListScreen => new EntryListScreen(
                $this->container->get(EntryRepository::SERVICE_ID)
            )
        );
        $this->container->set(
            ExplorerScreen::class,
            fn (): ExplorerScreen => new ExplorerScreen(
                $this->container->get(EntryRepository::SERVICE_ID),
                $this->container->get(TemplateRepository::SERVICE_ID)
            )
        );
        $this->container->set(
            EntryEditScreen::class,
            fn (): EntryEditScreen => new EntryEditScreen(
                $this->container->get(EntryRepository::SERVICE_ID),
                $this->container->get(TemplateRepository::SERVICE_ID),
                $this->container->get(FieldRepository::SERVICE_ID),
                $this->container->get(FieldRegistry::SERVICE_ID),
                $this->container->get(RelationRepository::SERVICE_ID)
            )
        );
        $this->container->set(
            EntryAjax::class,
            fn (): EntryAjax => new EntryAjax(
                $this->container->get(EntryRepository::SERVICE_ID),
                $this->container->get(TemplateRepository::SERVICE_ID)
            )
        );
        $this->container->set(
            Renderer::SERVICE_ID,
            static fn (): Renderer => new Renderer()
        );
        $this->container->set(
            EntryQueryFactory::class,
            fn (): EntryQueryFactory => new EntryQueryFactory(
                $this->container->get(TemplateRepository::SERVICE_ID),
                $this->container->get(EntryRepository::SERVICE_ID)
            )
        );
        $this->container->set(
            EntryResolver::class,
            fn (): EntryResolver => new EntryResolver(
                $this->container->get(TemplateRepository::SERVICE_ID),
                $this->container->get(EntryRepository::SERVICE_ID)
            )
        );
        $this->container->set(
            PublicApi::SERVICE_ID,
            fn (): PublicApi => new PublicApi(
                $this->container->get(EntryQueryFactory::class),
                $this->container->get(EntryResolver::class)
            )
        );
        $this->container->set(
            DocsScreen::class,
            static fn (): DocsScreen => new DocsScreen()
        );
        $this->container->set(
            MenuManager::class,
            fn (): MenuManager => new MenuManager(
                $this->container->get(TemplateRepository::SERVICE_ID),
                $this->container->get(ExplorerScreen::class),
                $this->container->get(EntryListScreen::class),
                $this->container->get(EntryEditScreen::class),
                $this->container->get(TemplatesScreen::class),
                $this->container->get(FieldsScreen::class),
                $this->container->get(DocsScreen::class),
            )
        );
    }

    private function registerHooks(): void
    {
        add_action('plugins_loaded', [$this, 'onPluginsLoaded']);

        if (! is_admin()) {
            add_action('plugins_loaded', [$this, 'registerFrontend'], 20);
        }

        if (is_admin()) {
            add_action('init', [$this, 'registerAdmin']);
        }
    }

    public function registerFrontend(): void
    {
        require_once BS_FABRIC_PATH . 'src/Api/PublicApi.php';
    }

    public function registerAdmin(): void
    {
        $this->container->get(MenuManager::class)->register();
        $this->container->get(FieldsScreen::class)->register();
        $this->container->get(TemplatesScreen::class)->register();
        $this->container->get(EntryEditScreen::class)->register();
        $this->container->get(FieldAjax::class)->register();
        $this->container->get(TemplateAjax::class)->register();
        $this->container->get(EntryAjax::class)->register();
    }

    public function onPluginsLoaded(): void
    {
        load_plugin_textdomain(
            'bs-fabric',
            false,
            dirname(plugin_basename(BS_FABRIC_FILE)) . '/languages'
        );

        Capabilities::register();
        $this->runMigrations();
    }

    public function activate(): void
    {
        $this->init();
        Capabilities::register();
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
