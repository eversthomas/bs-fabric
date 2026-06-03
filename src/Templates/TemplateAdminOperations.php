<?php

declare(strict_types=1);

namespace BsFabric\Templates;

interface TemplateAdminOperations
{
    public function delete(int $id): bool;

    public function assignField(int $templateId, int $fieldId, bool $isRequired = false): bool;

    public function removeField(int $templateId, int $fieldId): bool;

    /**
     * @return list<array<string, mixed>>
     */
    public function getFields(int $templateId): array;

    /**
     * @param list<int> $fieldIds
     */
    public function updateFieldOrder(int $templateId, array $fieldIds): bool;

    public function updateFieldRequired(int $templateId, int $fieldId, bool $isRequired): bool;
}
