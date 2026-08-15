<?php

declare(strict_types=1);

namespace BsFabric\Tests\Unit\Admin;

use BsFabric\Admin\EntryListExporter;
use BsFabric\Entries\Entry;
use BsFabric\Fields\FieldRegistry;
use BsFabric\Relations\RelationRepository;
use BsFabric\Tests\Unit\TestCase;
use Brain\Monkey\Functions;

final class EntryListExporterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Functions\when('__')->returnArg(1);
        Functions\when('apply_filters')->alias(static fn (string $hook, mixed $value): mixed => $value);
        Functions\when('esc_html')->alias(static fn (string $value): string => $value);
        Functions\when('wp_date')->alias(static fn (string $format, int $timestamp): string => gmdate($format, $timestamp));
    }

    public function testToCsvIncludesUtf8BomAndEscapesCommas(): void
    {
        $entry = new Entry(
            [
                'id' => 12,
                'template_id' => 1,
                'title' => 'Titel, mit Komma',
                'status' => 'published',
                'language' => 'de',
                'created_by' => 1,
                'created_at' => '2026-01-15 10:30:00',
                'updated_at' => '2026-01-15 10:30:00',
            ],
            [
                'subtitle' => [
                    'id' => 2,
                    'name' => 'subtitle',
                    'label' => 'Untertitel',
                    'type' => 'text',
                    'settings' => [],
                ],
            ],
            [
                'subtitle' => 'Wert',
            ],
            new FieldRegistry(),
            new RelationRepository(),
            null,
        );

        $exporter = new EntryListExporter();
        $csv = $exporter->toCsv([$entry], [
            [
                'id' => 2,
                'name' => 'subtitle',
                'label' => 'Untertitel',
                'type' => 'text',
                'settings' => [],
            ],
        ]);

        self::assertStringStartsWith("\xEF\xBB\xBF", $csv);
        self::assertStringContainsString('"Titel, mit Komma"', $csv);
        self::assertStringContainsString('12', $csv);
        self::assertStringContainsString('Wert', $csv);
        self::assertStringContainsString('Veröffentlicht', $csv);
        self::assertStringContainsString('2026-01-15 10:30:00', $csv);
    }

    public function testToCsvPrefixesFormulaLikeValuesToPreventCsvInjection(): void
    {
        $entry = new Entry(
            [
                'id' => 5,
                'template_id' => 1,
                'title' => '=HYPERLINK("http://evil.example","x")',
                'status' => 'published',
                'language' => 'de',
                'created_by' => 1,
                'created_at' => '2026-01-15 10:30:00',
                'updated_at' => '2026-01-15 10:30:00',
            ],
            [
                'subtitle' => [
                    'id' => 2,
                    'name' => 'subtitle',
                    'label' => 'Untertitel',
                    'type' => 'text',
                    'settings' => [],
                ],
            ],
            [
                'subtitle' => '+cmd|somecmd!A0',
            ],
            new FieldRegistry(),
            new RelationRepository(),
            null,
        );

        $exporter = new EntryListExporter();
        $csv = $exporter->toCsv([$entry], [
            [
                'id' => 2,
                'name' => 'subtitle',
                'label' => 'Untertitel',
                'type' => 'text',
                'settings' => [],
            ],
        ]);

        self::assertStringNotContainsString(',=HYPERLINK', $csv);
        self::assertStringContainsString('"\'=HYPERLINK(""http://evil.example"",""x"")"', $csv);
        self::assertStringContainsString(',\'+cmd|somecmd!A0,', $csv);
    }
}
