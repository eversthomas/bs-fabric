<?php

declare(strict_types=1);

namespace BsFabric\Fields\Types;

interface FieldTypeInterface
{
    public function getType(): string;

    public function getLabel(): string;

    /**
     * @return array<string, mixed>
     */
    public function getDefaultSettings(): array;

    public function sanitizeValue(mixed $value): mixed;

    /**
     * @param array<string, mixed> $raw
     *
     * @return array<string, mixed>
     */
    public function sanitizeSettings(array $raw): array;

    /**
     * @param array<string, mixed> $field
     *
     * Admin HTML — implementors must escape output (esc_attr, esc_html, wp_kses_post).
     */
    public function renderInput(array $field, mixed $value): string;

    /**
     * @param array<string, mixed> $field
     *
     * Admin HTML — implementors must escape output.
     */
    public function renderSettings(array $field): string;

    /**
     * @security String output must be escaped (esc_html, esc_attr, wp_kses_post) before echo;
     *           implementors returning HTML are responsible for escaping all dynamic values.
     */
    public function renderValue(mixed $value): mixed;
}
