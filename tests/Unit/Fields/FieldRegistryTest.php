<?php

declare(strict_types=1);

namespace BsFabric\Tests\Unit\Fields;

use BsFabric\Fields\FieldRegistry;
use BsFabric\Fields\Types\FieldTypeInterface;
use BsFabric\Fields\Types\TextField;
use BsFabric\Tests\Unit\TestCase;
use InvalidArgumentException;

final class FieldRegistryTest extends TestCase
{
    public function testRegistersAllBuiltInTypes(): void
    {
        \Brain\Monkey\Functions\when('apply_filters')->alias(
            static fn (string $hook, mixed $value): mixed => $value
        );

        $registry = new FieldRegistry();
        $types = $registry->all();

        $this->assertCount(12, $types);
        $this->assertSame(
            [
                'text',
                'textarea',
                'richtext',
                'image',
                'file',
                'date',
                'select',
                'boolean',
                'number',
                'address',
                'relation',
                'code',
            ],
            array_keys($types)
        );
    }

    public function testGetReturnsRequestedFieldType(): void
    {
        \Brain\Monkey\Functions\when('apply_filters')->alias(
            static fn (string $hook, mixed $value): mixed => $value
        );

        $registry = new FieldRegistry();

        $this->assertInstanceOf(TextField::class, $registry->get('text'));
        $this->assertSame('text', $registry->get('text')->getType());
    }

    public function testGetUnknownTypeThrowsException(): void
    {
        \Brain\Monkey\Functions\when('apply_filters')->alias(
            static fn (string $hook, mixed $value): mixed => $value
        );

        $registry = new FieldRegistry();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown field type: unknown');

        $registry->get('unknown');
    }

    public function testFieldTypesFilterCanExtendRegistry(): void
    {
        $customType = new class implements FieldTypeInterface {
            public function getType(): string
            {
                return 'custom';
            }

            public function getLabel(): string
            {
                return 'Custom';
            }

            public function getDefaultSettings(): array
            {
                return [];
            }

            public function sanitizeValue(mixed $value): mixed
            {
                return $value;
            }

            public function sanitizeSettings(array $raw): array
            {
                return $raw;
            }

            public function renderInput(array $field, mixed $value): string
            {
                return '';
            }

            public function renderSettings(array $field): string
            {
                return '';
            }

            public function renderValue(mixed $value): mixed
            {
                return $value;
            }
        };

        \Brain\Monkey\Functions\expect('apply_filters')
            ->once()
            ->with('bs_fabric_field_types', \Mockery::type('array'))
            ->andReturnUsing(static function (string $hook, array $types) use ($customType): array {
                $types['custom'] = $customType;

                return $types;
            });

        $registry = new FieldRegistry();

        $this->assertTrue($registry->has('custom'));
        $this->assertSame('custom', $registry->get('custom')->getType());
        $this->assertCount(13, $registry->all());
    }
}
