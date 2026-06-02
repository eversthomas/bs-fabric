<?php

declare(strict_types=1);

namespace BsFabric\Tests\Unit\Fields\Types;

use BsFabric\Fields\Types\TextField;
use BsFabric\Tests\Unit\TestCase;

final class TextFieldTest extends TestCase
{
    private TextField $field;

    protected function setUp(): void
    {
        parent::setUp();
        $this->field = new TextField();
    }

    public function testSanitizeValueReturnsEmptyStringForNullAndEmpty(): void
    {
        $this->assertSame('', $this->field->sanitizeValue(null));
        $this->assertSame('', $this->field->sanitizeValue(''));
    }

    public function testSanitizeValueRejectsNonScalarValues(): void
    {
        $this->assertSame('', $this->field->sanitizeValue([]));
        $this->assertSame('', $this->field->sanitizeValue(new \stdClass()));
    }

    public function testSanitizeValueUsesWordPressSanitizer(): void
    {
        \Brain\Monkey\Functions\expect('sanitize_text_field')
            ->once()
            ->with('  Hello World  ')
            ->andReturn('Hello World');

        $this->assertSame('Hello World', $this->field->sanitizeValue('  Hello World  '));
    }

    public function testSanitizeValueCastsNumericValuesToString(): void
    {
        \Brain\Monkey\Functions\expect('sanitize_text_field')
            ->once()
            ->with('42')
            ->andReturn('42');

        $this->assertSame('42', $this->field->sanitizeValue(42));
    }
}
