<?php

declare(strict_types=1);

namespace BsFabric\Tests\Unit\Fields\Types;

use BsFabric\Fields\Types\NumberField;
use BsFabric\Tests\Unit\TestCase;

final class NumberFieldTest extends TestCase
{
    private NumberField $field;

    protected function setUp(): void
    {
        parent::setUp();
        \Brain\Monkey\Functions\when('sanitize_text_field')->alias(
            static fn (string $value): string => trim($value)
        );
        $this->field = new NumberField();
    }

    public function testSanitizeValueReturnsEmptyStringForInvalidInput(): void
    {
        $this->assertSame('', $this->field->sanitizeValue(null));
        $this->assertSame('', $this->field->sanitizeValue(''));
        $this->assertSame('', $this->field->sanitizeValue('abc'));
        $this->assertSame('', $this->field->sanitizeValue([]));
    }

    public function testSanitizeValueAcceptsIntegersAndFloats(): void
    {
        $this->assertSame(42, $this->field->sanitizeValue('42'));
        $this->assertSame(42, $this->field->sanitizeValue(42));
        $this->assertSame(3.5, $this->field->sanitizeValue('3.5'));
        $this->assertSame(3.5, $this->field->sanitizeValue('3,5'));
    }

    public function testRenderValueReturnsNullForEmpty(): void
    {
        $this->assertNull($this->field->renderValue(''));
    }

    public function testRenderValueReturnsNumericValue(): void
    {
        $this->assertSame(10, $this->field->renderValue('10'));
        $this->assertSame(2.5, $this->field->renderValue('2.5'));
    }

    public function testSanitizeSettingsNormalizesBoundsAndStep(): void
    {
        $settings = $this->field->sanitizeSettings([
            'min' => '1.5',
            'max' => '99',
            'step' => '0,5',
            'unit' => ' km',
        ]);

        $this->assertSame(1.5, $settings['min']);
        $this->assertSame(99, $settings['max']);
        $this->assertSame('0.5', $settings['step']);
        $this->assertSame('km', $settings['unit']);
    }
}
