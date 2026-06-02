<?php

declare(strict_types=1);

namespace BsFabric\Admin\Screens;

use BsFabric\Core\Capabilities;

final class DocsScreen
{
    public const SLUG = 'bs-fabric-docs';

    public function render(): void
    {
        if (! current_user_can(Capabilities::MANAGE_ENTRIES)) {
            wp_die(esc_html__('Du hast keine Berechtigung für diese Seite.', 'bs-fabric'));
        }

        echo '<div class="wrap bs-fabric-wrap bs-fabric-docs">';
        echo '<h1>' . esc_html__('Doku', 'bs-fabric') . '</h1>';
        echo '<p class="description">' . esc_html__(
            'Frontend-API-Referenz für Theme-Entwickler. Code-Beispiele können per Klick kopiert werden.',
            'bs-fabric'
        ) . '</p>';

        foreach ($this->sections() as $section) {
            $this->renderSection($section);
        }

        echo '</div>';
    }

    public function enqueueAssets(): void
    {
        wp_enqueue_style(
            'bs-fabric-prism',
            'https://cdn.jsdelivr.net/npm/prismjs@1.29.0/themes/prism.min.css',
            [],
            '1.29.0'
        );

        wp_enqueue_script(
            'bs-fabric-prism-core',
            'https://cdn.jsdelivr.net/npm/prismjs@1.29.0/components/prism-core.min.js',
            [],
            '1.29.0',
            true
        );

        wp_enqueue_script(
            'bs-fabric-prism-php',
            'https://cdn.jsdelivr.net/npm/prismjs@1.29.0/components/prism-php.min.js',
            ['bs-fabric-prism-core'],
            '1.29.0',
            true
        );

        wp_enqueue_script(
            'bs-fabric-docs-admin',
            BS_FABRIC_URL . 'assets/js/docs-admin.js',
            ['bs-fabric-prism-php'],
            BS_FABRIC_VERSION,
            true
        );

        wp_enqueue_style(
            'bs-fabric-docs-admin',
            BS_FABRIC_URL . 'assets/css/docs-admin.css',
            ['bs-fabric-prism'],
            BS_FABRIC_VERSION
        );
    }

    /**
     * @return list<array{title: string, description: string, examples: list<array{label: string, code: string}>}>
     */
    private function sections(): array
    {
        return [
            [
                'title' => 'bs_fabric()',
                'description' => __(
                    'Globaler Einstiegspunkt der Frontend-API. Gibt einen EntryQuery-Builder oder einen einzelnen Entry zurück.',
                    'bs-fabric'
                ),
                'examples' => [
                    [
                        'label' => __('Query starten', 'bs-fabric'),
                        'code' => <<<'PHP'
$query = bs_fabric('einrichtung');
PHP,
                    ],
                    [
                        'label' => __('Einzelnen Entry laden (#-Syntax)', 'bs-fabric'),
                        'code' => <<<'PHP'
$entry = bs_fabric('einrichtung#42');
PHP,
                    ],
                    [
                        'label' => __('Einzelnen Entry laden (zweites Argument)', 'bs-fabric'),
                        'code' => <<<'PHP'
$entry = bs_fabric('einrichtung', 42);
PHP,
                    ],
                ],
            ],
            [
                'title' => 'EntryQuery',
                'description' => __(
                    'Fluent Builder zum Filtern, Sortieren und Laden von Entries eines Templates.',
                    'bs-fabric'
                ),
                'examples' => [
                    [
                        'label' => __('Kette: where, with, orderBy, limit', 'bs-fabric'),
                        'code' => <<<'PHP'
$entries = bs_fabric('einrichtung')
    ->where('stadt', 'Wesel')
    ->status('published')
    ->with('ansprechpartner')
    ->orderBy('name', 'ASC')
    ->limit(10)
    ->get();
PHP,
                    ],
                    [
                        'label' => __('first() und count()', 'bs-fabric'),
                        'code' => <<<'PHP'
$first = bs_fabric('einrichtung')->where('featured', '1')->first();
$total = bs_fabric('einrichtung')->status('published')->count();
PHP,
                    ],
                ],
            ],
            [
                'title' => 'Entry',
                'description' => __(
                    'Value Object mit Magic-Access auf Meta und Feldwerte. Relationen liefern EntryCollection.',
                    'bs-fabric'
                ),
                'examples' => [
                    [
                        'label' => __('Felder lesen', 'bs-fabric'),
                        'code' => <<<'PHP'
$entry = bs_fabric('einrichtung#42');

echo $entry->title;
echo $entry->name;

foreach ($entry->ansprechpartner as $person) {
    echo $person->name;
}
PHP,
                    ],
                    [
                        'label' => __('Serialisierung und Render-Stub', 'bs-fabric'),
                        'code' => <<<'PHP'
$data = $entry->toArray();
$json = $entry->toJson();
$html = $entry->render('card');
PHP,
                    ],
                ],
            ],
            [
                'title' => 'EntryCollection',
                'description' => __(
                    'Iterable Sammlung von Entries mit Hilfsmethoden für Ausgabe und Serialisierung.',
                    'bs-fabric'
                ),
                'examples' => [
                    [
                        'label' => __('Iteration und Render', 'bs-fabric'),
                        'code' => <<<'PHP'
$collection = bs_fabric('einrichtung')
    ->where('featured', true)
    ->get();

foreach ($collection as $entry) {
    echo esc_html((string) $entry->title);
}

echo $collection->render('card');
echo $collection->render('card', '<div class="cards">', '</div>');
PHP,
                    ],
                    [
                        'label' => __('first(), count(), toArray()', 'bs-fabric'),
                        'code' => <<<'PHP'
$first = $collection->first();
$count = $collection->count();
$rows = $collection->toArray();
PHP,
                    ],
                ],
            ],
            [
                'title' => __('Hooks', 'bs-fabric'),
                'description' => __(
                    'WordPress Actions und Filters zur Erweiterung von Speichervorgängen, Queries und Rendering.',
                    'bs-fabric'
                ),
                'examples' => [
                    [
                        'label' => __('Hooks registrieren', 'bs-fabric'),
                        'code' => <<<'PHP'
add_filter('bs_fabric_field_types', $callback);
add_filter('bs_fabric_query_args', $callback);
add_filter('bs_fabric_template_path', $callback);

add_action('bs_fabric_before_entry_save', fn (int $id, array $data) => ..., 10, 2);
add_action('bs_fabric_after_entry_save', fn (int $id, array $data) => ..., 10, 2);
add_action('bs_fabric_before_entry_delete', fn (int $id) => ...);
add_action('bs_fabric_after_entry_delete', fn (int $id) => ...);
PHP,
                    ],
                ],
            ],
            [
                'title' => __('View Templates', 'bs-fabric'),
                'description' => __(
                    'Render-Ausgabe erfolgt über View-Templates (Session 10). Im Theme überschreibbar unter /bs-fabric/.',
                    'bs-fabric'
                ),
                'examples' => [
                    [
                        'label' => __('Im Theme rendern', 'bs-fabric'),
                        'code' => <<<'PHP'
<?= bs_fabric('einrichtung')
    ->where('featured', true)
    ->limit(3)
    ->get()
    ->render('card') ?>
PHP,
                    ],
                ],
            ],
            [
                'title' => __('Feldtypen erweitern', 'bs-fabric'),
                'description' => __(
                    'Eigene Feldtypen über FieldTypeInterface und den Filter bs_fabric_field_types registrieren.',
                    'bs-fabric'
                ),
                'examples' => [
                    [
                        'label' => __('Custom Field Type', 'bs-fabric'),
                        'code' => <<<'PHP'
add_filter('bs_fabric_field_types', function (array $types): array {
    $types['rating'] = RatingField::class;

    return $types;
});
PHP,
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array{title: string, description: string, examples: list<array{label: string, code: string}>} $section
     */
    private function renderSection(array $section): void
    {
        echo '<section class="bs-fabric-docs-section">';
        echo '<h2>' . esc_html($section['title']) . '</h2>';
        echo '<p>' . esc_html($section['description']) . '</p>';

        foreach ($section['examples'] as $example) {
            echo '<div class="bs-fabric-docs-example">';
            echo '<div class="bs-fabric-docs-example-header">';
            echo '<h3>' . esc_html($example['label']) . '</h3>';
            echo '<button type="button" class="button bs-fabric-docs-copy">';
            echo esc_html__('Kopieren', 'bs-fabric') . '</button>';
            echo '</div>';
            echo '<pre class="bs-fabric-docs-code"><code class="language-php">';
            echo esc_html(trim($example['code']));
            echo '</code></pre>';
            echo '</div>';
        }

        echo '</section>';
    }
}
