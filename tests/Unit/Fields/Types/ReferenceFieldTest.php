<?php

declare(strict_types=1);

namespace BsFabric\Tests\Unit\Fields\Types;

use BsFabric\Fields\Types\ReferenceField;
use BsFabric\Tests\Unit\TestCase;
use Brain\Monkey\Functions;

final class ReferenceFieldTest extends TestCase
{
    private ReferenceField $field;

    protected function setUp(): void
    {
        parent::setUp();

        Functions\when('sanitize_key')->alias(
            static fn (string $value): string => strtolower(preg_replace('/[^a-z0-9_\-]/', '', $value) ?? '')
        );
        Functions\when('absint')->alias(static fn (mixed $value): int => abs((int) $value));
        Functions\when('__')->alias(static fn (string $value): string => $value);
        Functions\when('checked')->alias(
            static fn (mixed $checked, mixed $current, bool $echo): string => (bool) $checked === (bool) $current ? ' checked="checked"' : ''
        );

        $this->field = new ReferenceField();
    }

    public function testSanitizeValueReturnsNullForEmpty(): void
    {
        $this->assertNull($this->field->sanitizeValue(null));
        $this->assertNull($this->field->sanitizeValue(''));
        $this->assertNull($this->field->sanitizeValue(0));
        $this->assertNull($this->field->sanitizeValue([]));
    }

    public function testSanitizeValueReturnsSingleIdForScalar(): void
    {
        $this->assertSame(42, $this->field->sanitizeValue('42'));
        $this->assertSame(7, $this->field->sanitizeValue(7));
    }

    public function testSanitizeValueReturnsListForArray(): void
    {
        $this->assertSame([1, 2, 3], $this->field->sanitizeValue(['1', 2, 3, 2]));
    }

    public function testSanitizeValueAcceptsObjectShapeWithId(): void
    {
        $this->assertSame(9, $this->field->sanitizeValue(['id' => 9, 'title' => 'X']));
    }

    public function testSanitizeSettingsNormalizesPostTypes(): void
    {
        $settings = $this->field->sanitizeSettings([
            'post_types' => ['post', 'page', ''],
            'extra_post_types' => 'product, event',
            'multiple' => '1',
        ]);

        $this->assertTrue($settings['multiple']);
        $this->assertSame(['post', 'page', 'product', 'event'], $settings['post_types']);
        $this->assertArrayNotHasKey('extra_post_types', $settings);
    }

    public function testSanitizeSettingsFallsBackToPostAndPage(): void
    {
        $settings = $this->field->sanitizeSettings([
            'post_types' => [],
        ]);

        $this->assertSame(['post', 'page'], $settings['post_types']);
    }

    public function testRenderValueReturnsNullWhenPostMissing(): void
    {
        Functions\when('get_post')->justReturn(null);

        $this->assertNull($this->field->renderValue(99));
    }
}
