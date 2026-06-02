<?php

declare(strict_types=1);

namespace BsFabric\Tests\Unit\Fields\Types;

use BsFabric\Fields\Types\RelationField;
use BsFabric\Tests\Unit\TestCase;

final class RelationFieldTest extends TestCase
{
    private RelationField $field;

    protected function setUp(): void
    {
        parent::setUp();

        \Brain\Monkey\Functions\when('absint')->alias(
            static fn (mixed $value): int => (int) max(0, (int) $value)
        );

        $this->field = new RelationField();
    }

    public function testSanitizeValueReturnsEmptyArrayForEmptyInput(): void
    {
        $this->assertSame([], $this->field->sanitizeValue(null));
        $this->assertSame([], $this->field->sanitizeValue(''));
        $this->assertSame([], $this->field->sanitizeValue([]));
    }

    public function testSanitizeValueNormalizesSingleEntryId(): void
    {
        $this->assertSame([42], $this->field->sanitizeValue(42));
        $this->assertSame([7], $this->field->sanitizeValue('7'));
    }

    public function testSanitizeValueNormalizesMultipleEntryIds(): void
    {
        $this->assertSame([1, 2, 3], $this->field->sanitizeValue([1, 2, 3]));
    }

    public function testSanitizeValueFiltersInvalidAndDuplicateIds(): void
    {
        $this->assertSame([5, 9], $this->field->sanitizeValue([5, 0, -3, '9', 5, 'invalid']));
    }

    public function testSanitizeValueAcceptsArrayItemsWithIdKey(): void
    {
        $this->assertSame([12], $this->field->sanitizeValue([['id' => 12]]));
    }
}
