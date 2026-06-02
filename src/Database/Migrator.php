<?php

declare(strict_types=1);

namespace BsFabric\Database;

final class Migrator
{
    public const OPTION_KEY = 'bs_fabric_db_version';

    public function getVersion(): string
    {
        $version = get_option(self::OPTION_KEY, '0');

        return is_string($version) ? $version : '0';
    }

    public function needsMigration(): bool
    {
        return version_compare($this->getVersion(), Installer::DB_VERSION, '<');
    }

    public function updateVersion(string $version): void
    {
        update_option(self::OPTION_KEY, $version, false);
    }

    public function migrate(Installer $installer): void
    {
        if (! $this->needsMigration()) {
            return;
        }

        $installer->install();
    }
}
