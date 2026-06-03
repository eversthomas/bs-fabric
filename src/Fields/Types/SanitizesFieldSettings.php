<?php

declare(strict_types=1);

namespace BsFabric\Fields\Types;

use BsFabric\Fields\FieldSettings;

trait SanitizesFieldSettings
{
    /**
     * @param array<string, mixed> $raw
     *
     * @return array<string, mixed>
     */
    public function sanitizeSettings(array $raw): array
    {
        $merged = array_merge($this->getDefaultSettings(), $raw);

        return $this->sanitizeTypeSettings($this->sanitizeGlobalFieldSettings($merged));
    }

    /**
     * @param array<string, mixed> $settings
     *
     * @return array<string, mixed>
     */
    protected function sanitizeTypeSettings(array $settings): array
    {
        return $settings;
    }

    /**
     * @param array<string, mixed> $settings
     *
     * @return array<string, mixed>
     */
    protected function sanitizeGlobalFieldSettings(array $settings): array
    {
        if (array_key_exists(FieldSettings::SHOW_IN_LIST, $settings)) {
            $settings[FieldSettings::SHOW_IN_LIST] = ! empty($settings[FieldSettings::SHOW_IN_LIST]);
        }

        return $settings;
    }

    /**
     * @param array<string, mixed> $settings
     *
     * @return array<string, mixed>
     */
    protected function sanitizeStringSetting(array $settings, string $key): array
    {
        if (array_key_exists($key, $settings)) {
            $settings[$key] = sanitize_text_field((string) $settings[$key]);
        }

        return $settings;
    }

    /**
     * @param array<string, mixed> $settings
     *
     * @return array<string, mixed>
     */
    protected function sanitizeIntSetting(array $settings, string $key, int $min = 0): array
    {
        if (array_key_exists($key, $settings)) {
            $settings[$key] = max($min, (int) $settings[$key]);
        }

        return $settings;
    }

    /**
     * @param array<string, mixed> $settings
     *
     * @return array<string, mixed>
     */
    protected function sanitizeBoolSetting(array $settings, string $key): array
    {
        if (array_key_exists($key, $settings)) {
            $settings[$key] = ! empty($settings[$key]);
        }

        return $settings;
    }

    /**
     * @param array<string, mixed> $settings
     *
     * @return array<string, mixed>
     */
    protected function sanitizeKeySetting(array $settings, string $key): array
    {
        if (array_key_exists($key, $settings)) {
            $settings[$key] = sanitize_key((string) $settings[$key]);
        }

        return $settings;
    }
}
