<?php

declare(strict_types=1);

namespace BsFabric\Tests\Unit\Fields\Types;

use BsFabric\Fields\Types\BooleanField;
use BsFabric\Tests\Unit\TestCase;

final class BooleanFieldTest extends TestCase
{
    private BooleanField $field;

    protected function setUp(): void
    {
        parent::setUp();
        $this->field = new BooleanField();
    }

    public function testSanitizeValueReturnsFalseForEmptyValues(): void
    {
        $this->assertFalse($this->field->sanitizeValue(null));
        $this->assertFalse($this->field->sanitizeValue(''));
    }

    public function testSanitizeValueAcceptsTruthyScalars(): void
    {
        $this->assertTrue($this->field->sanitizeValue(true));
        $this->assertTrue($this->field->sanitizeValue(1));
        $this->assertTrue($this->field->sanitizeValue('1'));
        $this->assertTrue($this->field->sanitizeValue('yes'));
    }

    public function testSanitizeValueRejectsNonScalarValues(): void
    {
        $this->assertFalse($this->field->sanitizeValue([]));
        $this->assertFalse($this->field->sanitizeValue('0'));
        $this->assertFalse($this->field->sanitizeValue(0));
    }

    public function testRenderValueReturnsBoolean(): void
    {
        $this->assertTrue($this->field->renderValue('1'));
        $this->assertFalse($this->field->renderValue('0'));
    }
}
