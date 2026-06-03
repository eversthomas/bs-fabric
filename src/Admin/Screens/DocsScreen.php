<?php

declare(strict_types=1);

namespace BsFabric\Admin\Screens;

use BsFabric\Admin\Components\PageHead;
use BsFabric\Admin\DocsExampleRenderer;
use BsFabric\Core\Capabilities;

final class DocsScreen
{
    public const LEGACY_SLUG = 'bs-fabric-docs';

    public function render(): void
    {
        if (! current_user_can(Capabilities::MANAGE_ENTRIES)) {
            wp_die(esc_html__('Du hast keine Berechtigung für diese Seite.', 'bs-fabric'));
        }

        $inSchema = SchemaScreen::isSchemaPage();

        if (! $inSchema) {
            echo '<div class="wrap bs-fabric-wrap bs-fabric-docs">';
            echo '<h1>' . esc_html__('Doku', 'bs-fabric') . '</h1>';
            echo '<p class="description">' . esc_html($this->lede()) . '</p>';
        } else {
            PageHead::render(
                __('Doku', 'bs-fabric'),
                $this->lede()
            );
            echo '<div class="bsf-docs">';
        }

        foreach ($this->sections() as $section) {
            DocsExampleRenderer::renderSection(
                $section['title'],
                $section['description'],
                $section['examples']
            );
        }

        if ($inSchema) {
            echo '</div>';
        } else {
            echo '</div>';
        }
    }

    private function lede(): string
    {
        return __(
            'Kurze Anleitung für Theme-Entwickler: Was der Code macht, wann du ihn brauchst und was zurückkommt. Wichtig: Es gibt einen Unterschied zwischen Daten laden, Felder einzeln ausgeben und fertigem HTML per render(). Beispiele kannst du per Klick kopieren.',
            'bs-fabric'
        );
    }

    public function enqueueAssets(): void
    {
        DocsExampleRenderer::enqueueAssets(true);
    }

    /**
     * @return list<array{
     *     title: string,
     *     description: string,
     *     examples: list<array{label: string, intro: string, code: string}>
     * }>
     */
    private function sections(): array
    {
        return [
            [
                'title' => 'bs_fabric()',
                'description' => __(
                    'Das ist dein Einstieg im Theme. Mit bs_fabric() startest du eine Abfrage oder lädst einen einzelnen Eintrag — ähnlich wie du es von WP_Query gewohnt bist, nur für Fabric-Entries.',
                    'bs-fabric'
                ),
                'examples' => [
                    [
                        'label' => __('Abfrage starten', 'bs-fabric'),
                        'intro' => __(
                            'Gib den Template-Namen an (den slug aus Einstellungen → Templates, z. B. „einrichtung“). Du bekommst einen EntryQuery-Builder zurück, an den du Filter wie where() oder status() anhängen kannst.',
                            'bs-fabric'
                        ),
                        'code' => <<<'PHP'
$query = bs_fabric('einrichtung');
PHP,
                    ],
                    [
                        'label' => __('Einen Entry per ID laden (#-Syntax)', 'bs-fabric'),
                        'intro' => __(
                            'Kombiniere Template-Name und ID mit einem Hash. Ergebnis ist ein einzelner Entry — praktisch auf Detailseiten, wenn du die ID aus der URL kennst.',
                            'bs-fabric'
                        ),
                        'code' => <<<'PHP'
$entry = bs_fabric('einrichtung#42');
PHP,
                    ],
                    [
                        'label' => __('Einen Entry per ID laden (zweites Argument)', 'bs-fabric'),
                        'intro' => __(
                            'Dasselbe wie die #-Syntax, nur als zwei getrennte Argumente. Beides liefert einen einzelnen Entry oder null, wenn die ID nicht existiert.',
                            'bs-fabric'
                        ),
                        'code' => <<<'PHP'
$entry = bs_fabric('einrichtung', 42);
PHP,
                    ],
                ],
            ],
            [
                'title' => 'EntryQuery',
                'description' => __(
                    'Mit EntryQuery filterst, sortierst und begrenzt du Einträge eines Templates. Methoden kannst du aneinanderhängen — am Ende rufst du get(), first() oder count() auf.',
                    'bs-fabric'
                ),
                'examples' => [
                    [
                        'label' => __('Filtern, sortieren und limitieren', 'bs-fabric'),
                        'intro' => __(
                            'Typischer Alltagsfall: nur veröffentlichte Einträge, nach Stadt filtern, verknüpfte Ansprechpartner mitladen, sortieren und maximal 10 Ergebnisse. get() liefert eine EntryCollection.',
                            'bs-fabric'
                        ),
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
                        'label' => __('Nur den ersten Treffer oder die Anzahl', 'bs-fabric'),
                        'intro' => __(
                            'first() gibt dir genau einen Entry zurück (oder null). count() zählt Treffer, ohne alle Datensätze vollständig zu laden — gut für Badges oder „X Ergebnisse“.',
                            'bs-fabric'
                        ),
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
                    'Ein Entry ist ein einzelner Datensatz. Der Titel steht in $entry->title. Weitere Felder liest du per $entry->feldname — dabei liefert Fabric je nach Feldtyp bereits aufbereitete Werte (z. B. escaped Text, HTML bei Richtext, Array bei Bildern). Für Rohdaten aus der Datenbank nutze toArray().',
                    'bs-fabric'
                ),
                'examples' => [
                    [
                        'label' => __('Felder und Relationen lesen', 'bs-fabric'),
                        'intro' => __(
                            'Greif auf einzelne Felder zu. Text ist escaped, Richtext enthält HTML, Bilder liefern ein Array mit url und alt. Relationen sind EntryCollections — vorher in der Query with("feldname") setzen, sonst sind sie leer.',
                            'bs-fabric'
                        ),
                        'code' => <<<'PHP'
$entry = bs_fabric('einrichtung#42');

echo esc_html((string) $entry->title);
echo esc_html((string) $entry->name);

// Bild-Feld (Array mit url, alt):
if (is_array($entry->titelbild)) {
    printf(
        '<img src="%s" alt="%s" />',
        esc_url((string) $entry->titelbild['url']),
        esc_attr((string) $entry->titelbild['alt'])
    );
}

foreach ($entry->ansprechpartner as $person) {
    echo esc_html((string) $person->name);
}
PHP,
                    ],
                    [
                        'label' => __('Rohdaten mit toArray()', 'bs-fabric'),
                        'intro' => __(
                            'Wenn du das Markup selbst bauen willst, hol dir die unverarbeiteten Werte. toArray() liefert alle Meta-Felder plus Rohwerte — Relationen als ID-Liste. Ideal für eigene Theme-Logik ohne Plugin-HTML.',
                            'bs-fabric'
                        ),
                        'code' => <<<'PHP'
$data = $entry->toArray();

echo esc_html((string) ($data['title'] ?? ''));
echo esc_html((string) ($data['name'] ?? ''));

// Relationen: Liste von Entry-IDs, z. B. [3, 7, 12]
$relatedIds = $data['ansprechpartner'] ?? [];
PHP,
                    ],
                    [
                        'label' => __('JSON oder fertiges HTML (render)', 'bs-fabric'),
                        'intro' => __(
                            'toJson() für APIs oder Debugging. render() ist optional und liefert fertiges HTML aus View-Templates — card zeigt nur einen Teaser (Titel, erstes Text- und Bildfeld), detail alle nicht-leeren Felder mit Labels.',
                            'bs-fabric'
                        ),
                        'code' => <<<'PHP'
$json = $entry->toJson();

// Teaser-Karte (nicht alle Felder):
echo $entry->render('card');

// Alle Felder mit Label (dl/dt/dd-Markup):
echo $entry->render('detail');
PHP,
                    ],
                ],
            ],
            [
                'title' => __('Datenzugriff und Ausgabe', 'bs-fabric'),
                'description' => __(
                    'Drei Wege im Theme — wähle bewusst: (1) Felder einzeln ausgeben = maximale Freiheit, du kontrollierst HTML und Escaping. (2) toArray() = Rohdaten für eigene Logik. (3) render() = schnelle Standard-Ausgabe mit Plugin-Markup, Container drumherum legst du selbst fest.',
                    'bs-fabric'
                ),
                'examples' => [
                    [
                        'label' => __('Eigenes Markup — alle Felder durchlaufen', 'bs-fabric'),
                        'intro' => __(
                            'So gibst du alle Template-Felder aus, ohne render() zu nutzen. getFields() liefert die Felddefinitionen, $entry->{$name} die aufbereiteten Werte. Leere Felder überspringst du selbst — du entscheidest über HTML-Struktur und CSS-Klassen.',
                            'bs-fabric'
                        ),
                        'code' => <<<'PHP'
$entries = bs_fabric('einrichtung')->status('published')->get();

foreach ($entries as $entry) {
    echo '<article class="einrichtung-card">';
    echo '<h2>' . esc_html((string) $entry->title) . '</h2>';

    foreach ($entry->getFields() as $name => $field) {
        $value = $entry->{$name};

        if ($value === null || $value === '') {
            continue;
        }

        if ($field['type'] === 'image' && is_array($value)) {
            printf(
                '<img src="%s" alt="%s" />',
                esc_url((string) ($value['url'] ?? '')),
                esc_attr((string) ($value['alt'] ?? ''))
            );
            continue;
        }

        if ($field['type'] === 'richtext' && is_scalar($value)) {
            echo wp_kses_post((string) $value);
            continue;
        }

        if (is_scalar($value)) {
            echo '<p>' . esc_html((string) $value) . '</p>';
        }
    }

    echo '</article>';
}
PHP,
                    ],
                    [
                        'label' => __('render() — Teaser vs. Detail', 'bs-fabric'),
                        'intro' => __(
                            'render("card") ist ein Kurz-Layout: Titel, erstes Textfeld, erstes Bild, Link. render("detail") zeigt alle befüllten Felder mit Label. Beides liefert HTML mit bs-fabric-*-Klassen — überschreibbar im Theme unter /bs-fabric/.',
                            'bs-fabric'
                        ),
                        'code' => <<<'PHP'
// Card: Teaser, nicht der komplette Datensatz
echo bs_fabric('einrichtung')->limit(3)->get()->render('card');

// Detail: alle Felder des Eintrags
echo bs_fabric('einrichtung#42')->render('detail');
PHP,
                    ],
                    [
                        'label' => __('Container selbst setzen', 'bs-fabric'),
                        'intro' => __(
                            'Bei render() bestimmst du den äußeren Wrapper — z. B. ein div mit deiner Grid-Klasse. Innen kommt das Plugin-Layout (Karten oder Detail-Liste), außen bleibt dein Theme-Container.',
                            'bs-fabric'
                        ),
                        'code' => <<<'PHP'
echo bs_fabric('einrichtung')
    ->where('featured', '1')
    ->get()
    ->render('card', '<div class="cards-grid">', '</div>');
PHP,
                    ],
                ],
            ],
            [
                'title' => 'EntryCollection',
                'description' => __(
                    'get() liefert eine Sammlung von Entries. Du kannst sie durchlaufen, den ersten Eintrag holen oder die ganze Liste auf einmal rendern.',
                    'bs-fabric'
                ),
                'examples' => [
                    [
                        'label' => __('Schleife — nur Titel ausgeben', 'bs-fabric'),
                        'intro' => __(
                            'Nur Daten laden reicht nicht — du musst selbst echo-en. Dieses Beispiel gibt pro Eintrag nur den Titel aus. Für alle Felder siehe Abschnitt „Datenzugriff und Ausgabe“ oder nutze render().',
                            'bs-fabric'
                        ),
                        'code' => <<<'PHP'
$collection = bs_fabric('einrichtung')
    ->where('featured', true)
    ->get();

foreach ($collection as $entry) {
    echo '<p>' . esc_html((string) $entry->title) . '</p>';
}
PHP,
                    ],
                    [
                        'label' => __('render() mit Container-Wrapper', 'bs-fabric'),
                        'intro' => __(
                            'Optional: fertiges Card-HTML vom Plugin. card zeigt nur Titel, erstes Text- und Bildfeld — kein Ersatz für eine vollständige Feldliste. Den äußeren div-Container legst du selbst fest.',
                            'bs-fabric'
                        ),
                        'code' => <<<'PHP'
$collection = bs_fabric('einrichtung')
    ->where('featured', true)
    ->get();

echo $collection->render('card', '<div class="cards">', '</div>');
PHP,
                    ],
                    [
                        'label' => __('Ersten Eintrag, Anzahl und Array', 'bs-fabric'),
                        'intro' => __(
                            'first() liefert den ersten Entry der Sammlung. count() gibt die Anzahl zurück. toArray() wandelt alles in ein normales PHP-Array um — z. B. für eigene Template-Logik.',
                            'bs-fabric'
                        ),
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
                    'Über WordPress-Hooks kannst du Fabric erweitern: eigene Feldtypen, angepasste Query-Logik oder Reaktionen beim Speichern und Löschen.',
                    'bs-fabric'
                ),
                'examples' => [
                    [
                        'label' => __('Wichtige Hooks', 'bs-fabric'),
                        'intro' => __(
                            'Registriere die Hooks in functions.php deines Themes oder in einem kleinen Plugin. Filter ändern Daten vor der Ausgabe, Actions laufen zu festen Zeitpunkten (z. B. nach dem Speichern).',
                            'bs-fabric'
                        ),
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
                    'render() ist der Shortcut für fertiges HTML. Standard-Views: card (Teaser-Liste), detail (alle Felder), list (Collection-Wrapper). Templates liegen im Plugin und können im Theme unter /bs-fabric/ überschrieben werden. Für volle Markup-Kontrolle lieber Felder einzeln oder toArray() nutzen.',
                    'bs-fabric'
                ),
                'examples' => [
                    [
                        'label' => __('card vs. detail — was wird ausgegeben?', 'bs-fabric'),
                        'intro' => __(
                            'card: Titel, erstes Textfeld, erstes Bild, Link — gut für Übersichtsseiten. detail: alle nicht-leeren Felder mit Label in dl/dt/dd — gut für Einzelansichten. Beides liefert HTML mit bs-fabric-*-Klassen.',
                            'bs-fabric'
                        ),
                        'code' => <<<'PHP'
// Teaser (nicht alle Felder):
<?= bs_fabric('einrichtung')->limit(3)->get()->render('card') ?>

// Vollständige Feldliste eines Eintrags:
<?= bs_fabric('einrichtung#42')->render('detail') ?>
PHP,
                    ],
                    [
                        'label' => __('Mit eigenem Container im Theme', 'bs-fabric'),
                        'intro' => __(
                            'Der zweite und dritte Parameter von render() sind dein Wrapper — z. B. ein Grid-div. Innen kommt das Plugin-Layout, außen dein Theme-Markup.',
                            'bs-fabric'
                        ),
                        'code' => <<<'PHP'
<?= bs_fabric('einrichtung')
    ->where('featured', true)
    ->limit(3)
    ->get()
    ->render('card', '<div class="cards">', '</div>') ?>
PHP,
                    ],
                    [
                        'label' => __('Eigenes Template im Theme', 'bs-fabric'),
                        'intro' => __(
                            'Lege eine PHP-Datei im Theme ab, um das Aussehen anzupassen. Alternativ kannst du per Filter den Pfad zur View-Datei umleiten — ohne Plugin-Dateien zu ändern.',
                            'bs-fabric'
                        ),
                        'code' => <<<'PHP'
// Pfad (höchste Priorität):
// wp-content/themes/aktives-theme/bs-fabric/card.php

// Alternativ:
// wp-content/themes/aktives-theme/bs-fabric/default/card.php

// Plugin-Fallback:
// wp-content/plugins/bs-fabric/templates/default/card.php

add_filter('bs_fabric_template_path', function (string $path, string $view, array $context): string {
    if ($view === 'card') {
        return get_stylesheet_directory() . '/bs-fabric/custom-card.php';
    }

    return $path;
}, 10, 3);
PHP,
                    ],
                ],
            ],
            [
                'title' => __('Feldtypen erweitern', 'bs-fabric'),
                'description' => __(
                    'Wenn die mitgelieferten Feldtypen nicht reichen, registrierst du eigene Typen über eine Klasse und einen Filter.',
                    'bs-fabric'
                ),
                'examples' => [
                    [
                        'label' => __('Eigenen Feldtyp registrieren', 'bs-fabric'),
                        'intro' => __(
                            'Deine Klasse implementiert FieldTypeInterface (Speichern, Anzeige im Admin, Ausgabe im Frontend). Über den Filter trägst du sie unter einem slug-Namen ein — danach ist der Typ in Einstellungen → Fields wählbar.',
                            'bs-fabric'
                        ),
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
}
