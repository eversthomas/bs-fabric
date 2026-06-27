<?php

declare(strict_types=1);

namespace BsFabric\Core;

use BsFabric\Templates\Template;
use BsFabric\Templates\TemplateSettings;

final class TemplateAuthorization
{
    public static function canManageSchema(): bool
    {
        return current_user_can(Capabilities::MANAGE_SCHEMA);
    }

    public static function canManageEntries(): bool
    {
        return current_user_can(Capabilities::MANAGE_ENTRIES);
    }

    public static function canAssignTemplateFields(): bool
    {
        return self::canManageSchema() || current_user_can(Capabilities::ASSIGN_TEMPLATE_FIELDS);
    }

    /**
     * Felder im Template zuweisen, sortieren, Pflicht setzen, aus Template entfernen.
     */
    public static function canManageTemplateFields(?Template $template): bool
    {
        if (self::canManageSchema()) {
            return true;
        }

        if ($template === null || ! self::canAssignTemplateFields()) {
            return false;
        }

        return TemplateSettings::editorsMayManageFields($template->settings);
    }
}
