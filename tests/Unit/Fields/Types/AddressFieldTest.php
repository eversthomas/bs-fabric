<?php

declare(strict_types=1);

namespace BsFabric\Tests\Unit\Fields\Types;

use BsFabric\Fields\Types\AddressField;
use BsFabric\Tests\Unit\TestCase;

final class AddressFieldTest extends TestCase
{
    private AddressField $field;

    protected function setUp(): void
    {
        parent::setUp();

        \Brain\Monkey\Functions\when('sanitize_text_field')->alias(
            static fn (string $value): string => trim(strip_tags($value))
        );
        \Brain\Monkey\Functions\when('sanitize_key')->alias(
            static fn (string $value): string => strtolower(preg_replace('/[^a-z0-9_\-]/', '', $value) ?? '')
        );
        \Brain\Monkey\Functions\when('esc_attr')->alias(
            static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8')
        );
        \Brain\Monkey\Functions\when('esc_html')->alias(
            static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8')
        );
        \Brain\Monkey\Functions\when('selected')->alias(
            static fn (mixed $selected, mixed $current, bool $echo): string => $selected === $current ? ' selected="selected"' : ''
        );
        \Brain\Monkey\Functions\when('checked')->alias(
            static fn (mixed $checked, mixed $current, bool $echo): string => (bool) $checked === (bool) $current ? ' checked="checked"' : ''
        );
        \Brain\Monkey\Functions\when('__')->alias(
            static fn (string $text): string => $text
        );
        \Brain\Monkey\Functions\when('esc_html__')->alias(
            static fn (string $text): string => htmlspecialchars($text, ENT_QUOTES, 'UTF-8')
        );
        \Brain\Monkey\Functions\when('esc_attr__')->alias(
            static fn (string $text): string => htmlspecialchars($text, ENT_QUOTES, 'UTF-8')
        );

        $this->field = new AddressField();
    }

    public function testSanitizeValueReturnsNullForEmptyInput(): void
    {
        $this->assertNull($this->field->sanitizeValue(null));
        $this->assertNull($this->field->sanitizeValue(''));
        $this->assertNull($this->field->sanitizeValue([]));
        $this->assertNull($this->field->sanitizeValue([
            'street' => '',
            'zip' => '',
            'city' => '',
        ]));
    }

    public function testSanitizeValueNormalizesFullAddress(): void
    {
        $result = $this->field->sanitizeValue([
            'street' => '  Musterstraße 1 ',
            'zip' => '10115',
            'city' => 'Berlin',
            'country' => 'de',
        ]);

        $this->assertIsArray($result);
        $this->assertSame('Musterstraße 1', $result['street']);
        $this->assertSame('10115', $result['zip']);
        $this->assertSame('Berlin', $result['city']);
        $this->assertSame('DE', $result['country']);
        $this->assertSame('Musterstraße 1, 10115 Berlin', $result['formatted']);
    }

    public function testSanitizeValueAcceptsJsonString(): void
    {
        $json = '{"street":"Hauptstr. 5","zip":"80331","city":"M\u00fcnchen","country":"DE"}';

        $result = $this->field->sanitizeValue($json);

        $this->assertIsArray($result);
        $this->assertSame('Hauptstr. 5, 80331 München', $result['formatted']);
    }

    public function testSanitizeValueRejectsInvalidCountry(): void
    {
        $result = $this->field->sanitizeValue([
            'street' => 'Testweg 1',
            'zip' => '12345',
            'city' => 'Teststadt',
            'country' => 'Deutschland',
        ]);

        $this->assertIsArray($result);
        $this->assertSame('', $result['country']);
    }

    public function testSanitizeValuePreservesExistingGeoFields(): void
    {
        $result = $this->field->sanitizeValue([
            'street' => 'Testweg 1',
            'zip' => '12345',
            'city' => 'Teststadt',
            'country' => 'DE',
            'lat' => 52.52,
            'lng' => 13.405,
            'geocoded_at' => '2026-06-08T12:00:00Z',
            'geocode_status' => 'ok',
            'geocode_hash' => str_repeat('a', 64),
        ]);

        $this->assertIsArray($result);
        $this->assertSame(52.52, $result['lat']);
        $this->assertSame(13.405, $result['lng']);
        $this->assertSame('2026-06-08T12:00:00Z', $result['geocoded_at']);
        $this->assertSame('ok', $result['geocode_status']);
        $this->assertSame(str_repeat('a', 64), $result['geocode_hash']);
    }

    public function testSanitizeSettingsNormalizesGeocodingFlag(): void
    {
        $settings = $this->field->sanitizeSettings([
            'enable_geocoding' => '1',
            'enable_map_picker' => '1',
        ]);

        $this->assertTrue($settings['enable_geocoding']);
        $this->assertTrue($settings['enable_map_picker']);
    }

    public function testSanitizeValueAcceptsManualGeocodeStatus(): void
    {
        $result = $this->field->sanitizeValue([
            'street' => 'Testweg 1',
            'zip' => '12345',
            'city' => 'Teststadt',
            'country' => 'DE',
            'lat' => 52.5,
            'lng' => 13.4,
            'geocode_status' => 'manual',
        ]);

        $this->assertIsArray($result);
        $this->assertSame('manual', $result['geocode_status']);
        $this->assertSame(52.5, $result['lat']);
    }

    public function testRenderInputIncludesMapWhenEnabled(): void
    {
        $html = $this->field->renderInput(
            [
                'name' => 'standort',
                'settings' => [
                    'enable_map_picker' => true,
                ],
            ],
            [
                'street' => 'Test',
                'zip' => '10115',
                'city' => 'Berlin',
                'country' => 'DE',
                'lat' => 52.52,
                'lng' => 13.4,
            ]
        );

        $this->assertStringContainsString('data-map-picker="1"', $html);
        $this->assertStringContainsString('bs-fabric-field-address-map', $html);
        $this->assertStringContainsString('data-geo-key="lat"', $html);
    }

    public function testRenderValueReturnsNullForEmpty(): void
    {
        $this->assertNull($this->field->renderValue([]));
    }

    public function testRenderValueReturnsNormalizedArray(): void
    {
        $result = $this->field->renderValue([
            'street' => 'Parkweg 2',
            'zip' => '20095',
            'city' => 'Hamburg',
            'country' => 'DE',
        ]);

        $this->assertIsArray($result);
        $this->assertSame('Hamburg', $result['city']);
        $this->assertSame('Parkweg 2, 20095 Hamburg', $result['formatted']);
    }

    public function testGetValidationErrorsForRequiredTemplateField(): void
    {
        $errors = $this->field->getValidationErrors([], ['label' => 'Standort'], true);

        $this->assertCount(1, $errors);
    }

    public function testGetValidationErrorsForMissingSubfields(): void
    {
        $errors = $this->field->getValidationErrors(
            [
                'street' => 'Test',
                'zip' => '',
                'city' => 'Berlin',
                'country' => 'DE',
            ],
            [
                'label' => 'Standort',
                'settings' => [
                    'require_street' => true,
                    'require_zip' => true,
                    'require_city' => true,
                ],
            ],
            false
        );

        $this->assertCount(1, $errors);
    }

    public function testSanitizeSettingsNormalizesDefaults(): void
    {
        $settings = $this->field->sanitizeSettings([
            'require_street' => '1',
            'require_zip' => '0',
            'require_city' => '1',
            'show_country' => '1',
            'default_country' => 'de',
        ]);

        $this->assertTrue($settings['require_street']);
        $this->assertFalse($settings['require_zip']);
        $this->assertTrue($settings['require_city']);
        $this->assertTrue($settings['show_country']);
        $this->assertSame('DE', $settings['default_country']);
    }
}
