<?php

declare(strict_types=1);

namespace BsFabric\Tests\Unit\Frontend;

use BsFabric\Entries\Entry;
use BsFabric\Entries\EntryCollection;
use BsFabric\Fields\FieldRegistry;
use BsFabric\Frontend\Renderer;
use BsFabric\Relations\RelationRepository;
use BsFabric\Tests\Unit\TestCase;

final class RendererTest extends TestCase
{
    private Renderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();

        if (! defined('BS_FABRIC_PATH')) {
            define('BS_FABRIC_PATH', dirname(__DIR__, 3) . '/');
        }

        if (! defined('BS_FABRIC_URL')) {
            define('BS_FABRIC_URL', 'https://example.test/wp-content/plugins/bs-fabric/');
        }

        if (! defined('BS_FABRIC_VERSION')) {
            define('BS_FABRIC_VERSION', '1.0.0');
        }

        $this->renderer = new Renderer();

        \Brain\Monkey\Functions\when('esc_html')->alias(static fn (string $value): string => $value);
        \Brain\Monkey\Functions\when('esc_attr')->alias(static fn (string $value): string => $value);
        \Brain\Monkey\Functions\when('esc_url')->alias(static fn (string $value): string => $value);
        \Brain\Monkey\Functions\when('esc_html__')->alias(static fn (string $value): string => $value);
        \Brain\Monkey\Functions\when('esc_attr__')->alias(static fn (string $value): string => $value);
        \Brain\Monkey\Functions\when('wp_kses_post')->alias(static fn (string $value): string => $value);
        \Brain\Monkey\Functions\when('__')->returnArg(1);
        \Brain\Monkey\Functions\when('apply_filters')->alias(
            static fn (string $hook, mixed $value): mixed => $value
        );
        \Brain\Monkey\Functions\when('sanitize_key')->alias(
            static fn (string $value): string => strtolower(preg_replace('/[^a-z0-9_\-]/', '', $value) ?? '')
        );
        \Brain\Monkey\Functions\when('sanitize_text_field')->alias(
            static fn (string $value): string => trim(strip_tags($value))
        );
        \Brain\Monkey\Functions\when('get_stylesheet_directory')->justReturn('/tmp/theme');
        \Brain\Monkey\Functions\when('is_admin')->justReturn(false);
        \Brain\Monkey\Functions\when('wp_enqueue_style')->justReturn(true);
        \Brain\Monkey\Functions\when('wp_enqueue_script')->justReturn(true);
        \Brain\Monkey\Functions\when('wp_localize_script')->justReturn(true);
    }

    public function testRenderFieldHtmlForAddressIncludesMapAndLink(): void
    {
        $entry = $this->createEntry([
            'standort' => [
                'id' => 9,
                'name' => 'standort',
                'label' => 'Standort',
                'type' => 'address',
                'settings' => [],
            ],
        ], [
            'standort' => [
                'street' => 'Musterstraße 1',
                'zip' => '10115',
                'city' => 'Berlin',
                'country' => 'DE',
                'formatted' => 'Musterstraße 1, 10115 Berlin',
                'lat' => 52.52,
                'lng' => 13.405,
            ],
        ]);

        $html = $this->renderer->renderFieldHtml($entry, 'standort');

        $this->assertStringContainsString('bs-fabric-address', $html);
        $this->assertStringContainsString('Musterstraße 1, 10115 Berlin', $html);
        $this->assertStringContainsString('openstreetmap.org', $html);
        $this->assertStringContainsString('bs-fabric-address-map', $html);
        $this->assertStringContainsString('data-lat="52.52"', $html);
    }

    public function testGetAddressFormattedAndMapsUrlHelpers(): void
    {
        $address = [
            'street' => 'A',
            'zip' => '1',
            'city' => 'B',
            'lat' => 1.5,
            'lng' => 2.5,
        ];

        $this->assertSame('A, 1 B', $this->renderer->getAddressFormatted($address));
        $this->assertNotNull($this->renderer->getAddressMapsUrl($address));
        $this->assertStringContainsString('bs-fabric-address-map', $this->renderer->renderAddressMap($address));
    }

    public function testRenderOutputsCardTemplateHtml(): void
    {
        $entry = $this->createEntry([
            'name' => [
                'id' => 2,
                'name' => 'name',
                'label' => 'Name',
                'type' => 'text',
                'settings' => [],
            ],
        ], [
            'name' => 'Fabric Entry',
        ]);

        $html = $this->renderer->render($entry, 'card');

        $this->assertStringContainsString('bs-fabric-card', $html);
        $this->assertStringContainsString('Fabric Entry', $html);
        $this->assertStringContainsString('Mehr erfahren', $html);
    }

    public function testRenderCollectionWrapsCardOutput(): void
    {
        $collection = new EntryCollection([
            $this->createEntry([], []),
            $this->createEntry([], []),
        ]);

        $html = $this->renderer->renderCollection(
            $collection,
            'card',
            '<div class="grid">',
            '</div>'
        );

        $this->assertStringStartsWith('<div class="grid">', $html);
        $this->assertStringEndsWith('</div>', $html);
        $this->assertSame(2, substr_count($html, 'bs-fabric-card'));
    }

    public function testRenderCollectionUsesListTemplate(): void
    {
        $collection = new EntryCollection([
            $this->createEntry([
                'summary' => [
                    'id' => 4,
                    'name' => 'summary',
                    'label' => 'Summary',
                    'type' => 'textarea',
                    'settings' => [],
                ],
            ], [
                'summary' => 'Kurzbeschreibung',
            ]),
        ]);

        $html = $this->renderer->renderCollection($collection, 'list');

        $this->assertStringContainsString('bs-fabric-list', $html);
        $this->assertStringContainsString('Kurzbeschreibung', $html);
    }

    public function testTemplatePathFilterCanOverrideResolvedTemplate(): void
    {
        $override = sys_get_temp_dir() . '/bs-fabric-card-override-' . uniqid('', true) . '.php';
        file_put_contents($override, '<?php echo "override";');

        \Brain\Monkey\Functions\when('apply_filters')->alias(
            static function (string $hook, mixed $value) use ($override): mixed {
                if ($hook === 'bs_fabric_template_path') {
                    return $override;
                }

                return $value;
            }
        );

        $html = $this->renderer->render($this->createEntry([], []), 'card');

        unlink($override);

        $this->assertSame('override', $html);
    }

    /**
     * @param array<string, array{id: int, name: string, label: string, type: string, settings: array<string, mixed>}> $fields
     * @param array<string, mixed> $values
     */
    private function createEntry(array $fields, array $values): Entry
    {
        return new Entry(
            [
                'id' => 1,
                'template_id' => 1,
                'title' => 'Entry Title',
                'status' => 'published',
                'language' => 'de',
                'created_by' => null,
                'created_at' => '2026-01-01 00:00:00',
                'updated_at' => '2026-01-01 00:00:00',
            ],
            $fields,
            $values,
            new FieldRegistry(),
            new RelationRepository(),
            null,
        );
    }
}
