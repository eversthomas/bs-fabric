<?php

declare(strict_types=1);

namespace BsFabric\Fields;

use BsFabric\Fields\Types\CodeField;
use BsFabric\Fields\Types\DateField;
use BsFabric\Fields\Types\FieldTypeInterface;
use BsFabric\Fields\Types\FileField;
use BsFabric\Fields\Types\ImageField;
use BsFabric\Fields\Types\RelationField;
use BsFabric\Fields\Types\RichtextField;
use BsFabric\Fields\Types\SelectField;
use BsFabric\Fields\Types\TextareaField;
use BsFabric\Fields\Types\TextField;
use InvalidArgumentException;

final class FieldRegistry
{
    public const SERVICE_ID = 'field.registry';

    /** @var array<string, FieldTypeInterface>|null */
    private ?array $types = null;

    public function get(string $type): FieldTypeInterface
    {
        $types = $this->all();

        if (! isset($types[$type])) {
            throw new InvalidArgumentException(sprintf('Unknown field type: %s', $type));
        }

        return $types[$type];
    }

    public function has(string $type): bool
    {
        return isset($this->all()[$type]);
    }

    /**
     * @return array<string, FieldTypeInterface>
     */
    public function all(): array
    {
        if ($this->types !== null) {
            return $this->types;
        }

        /** @var array<string, FieldTypeInterface> $types */
        $types = apply_filters(
            'bs_fabric_field_types',
            $this->getBuiltInTypes()
        );

        $this->types = $this->validateTypes($types);

        return $this->types;
    }

    /**
     * @return array<string, FieldTypeInterface>
     */
    private function getBuiltInTypes(): array
    {
        $builtIns = [
            new TextField(),
            new TextareaField(),
            new RichtextField(),
            new ImageField(),
            new FileField(),
            new DateField(),
            new SelectField(),
            new RelationField(),
            new CodeField(),
        ];

        $types = [];

        foreach ($builtIns as $fieldType) {
            $types[$fieldType->getType()] = $fieldType;
        }

        return $types;
    }

    /**
     * @param array<string, FieldTypeInterface> $types
     *
     * @return array<string, FieldTypeInterface>
     */
    private function validateTypes(array $types): array
    {
        $validated = [];

        foreach ($types as $key => $fieldType) {
            if (! $fieldType instanceof FieldTypeInterface) {
                continue;
            }

            $validated[$fieldType->getType()] = $fieldType;
        }

        return $validated;
    }
}
