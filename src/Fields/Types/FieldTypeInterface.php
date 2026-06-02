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
     * @param array<string, mixed> $field
     */
    public function renderInput(array $field, mixed $value): string;

    /**
     * @param array<string, mixed> $field
     */
    public function renderSettings(array $field): string;

    public function renderValue(mixed $value): mixed;
}
