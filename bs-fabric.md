# bs-fabric — Cursor Skill
> Lade diese Datei mit @bs-fabric am Anfang jeder Session

---

## Projekt-Snapshot

```
Plugin:      bs-fabric
Namespace:   BsFabric\
Prefix:      bs_fabric_
API:         bs_fabric()
PHP:         8.2+
WP-Min:      7.0
Storage:     6 eigene Tabellen (kein Post Meta, kein CPT)
```

---

## Datenbankstruktur (Kurzreferenz)

```
bs_fabric_fields          id, name*, label, type, settings(JSON)
bs_fabric_templates       id, name*, label, title_field, settings(JSON)
bs_fabric_template_fields id, template_id, field_id, sort_order, is_required
bs_fabric_entries         id, template_id, title, status, language, created_by
bs_fabric_entry_values    id, entry_id, field_id, value(LONGTEXT/JSON)
bs_fabric_relations       id, from_entry_id, to_entry_id, field_id, sort_order

* = eindeutiger slug-Identifier, nach Anlage nicht änderbar
```

---

## Feldtypen (alle 9)

```
text, textarea, richtext, image, file, date, select, relation, code
```

---

## API-Referenz

```php
// Einstieg
bs_fabric('template')              // → EntryQuery
bs_fabric('template#42')           // → Entry
bs_fabric('template', 42)          // → Entry

// Query
bs_fabric('einrichtung')
    ->where('feld', 'wert')
    ->with('relation_feld')
    ->orderBy('name', 'ASC')
    ->limit(10)
    ->get()                         // → EntryCollection
    ->first()                       // → ?Entry
    ->count()                       // → int

// Entry
$e->feldname                        // Feldwert via Magic __get
$e->bild->url                       // Image-Objekt
$e->bild->alt
$e->datum->format('d.m.Y')          // Date-Objekt
$e->relation_feld                   // EntryCollection
$e->render('card')                  // → string
$e->toArray()
$e->toJson()

// Collection
$c->render('card')
$c->render('card', '<div>', '</div>')
$c->first()
$c->count()
$c->toArray()
foreach ($c as $entry) { ... }
```

---

## Capabilities

```
bs_fabric_manage_schema    → Templates + Fields verwalten
bs_fabric_manage_entries   → Entries verwalten
```

---

## Hooks (alle)

```php
bs_fabric_field_types              // Filter: Feldtypen erweitern
bs_fabric_before_entry_save        // Action: (int $id, array $data)
bs_fabric_after_entry_save         // Action: (int $id, array $data)
bs_fabric_before_entry_delete      // Action: (int $id)
bs_fabric_after_entry_delete       // Action: (int $id)
bs_fabric_query_args               // Filter: Query modifizieren
bs_fabric_template_path            // Filter: View-Pfad überschreiben
```

---

## FieldTypeInterface

```php
interface FieldTypeInterface {
    public function getType(): string;
    public function getLabel(): string;
    public function getDefaultSettings(): array;
    public function sanitizeValue(mixed $value): mixed;
    public function renderInput(array $field, mixed $value): string;
    public function renderSettings(array $field): string;
    public function renderValue(mixed $value): mixed;
}
```

---

## ServiceContainer — Registrierte Services

```php
$container->get('field.repository')     // FieldRepository
$container->get('field.registry')       // FieldRegistry
$container->get('template.repository')  // TemplateRepository
$container->get('entry.repository')     // EntryRepository
$container->get('relation.repository')  // RelationRepository
$container->get('renderer')             // Renderer
```

---

## Session-Vorlage

```
@bs-fabric

Session-Ziel:   [ZIEL DIESER SESSION]
Neue Dateien:   [DATEILISTE]
Bereits fertig: [VORHANDENE DATEIEN]
Tests:          [WELCHE TESTS GRÜN SEIN MÜSSEN]

Abweichungen von ARCHITECTURE.md bitte explizit begründen.
```

---

## Definition of Done (Checkliste)

Vor dem Session-Abschluss prüfen:

- [ ] Alle Session-Dateien existieren und laden ohne PHP-Fehler
- [ ] `declare(strict_types=1)` in jeder neuen PHP-Datei
- [ ] Keine `$wpdb->query()` ohne `prepare()`
- [ ] Keine AJAX-Handler ohne Nonce-Prüfung
- [ ] Keine Admin-Aktionen ohne Capability-Prüfung
- [ ] Zugehörige Tests geschrieben und grün
- [ ] Manuelle Checkliste aus ARCHITECTURE.md abgehakt
- [ ] Keine vorgreifenden Implementierungen aus späteren Sessions

---

## Häufige Fehler — vermeiden

```php
// FALSCH — kein prepare()
$wpdb->query("SELECT * FROM {$wpdb->prefix}bs_fabric_entries WHERE id = $id");

// RICHTIG
$wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}bs_fabric_entries WHERE id = %d",
    $id
));

// FALSCH — Post Meta
update_post_meta($postId, 'bs_fabric_name', $value);

// RICHTIG — eigene Tabelle
$this->entryValueRepository->set($entryId, $fieldId, $value);

// FALSCH — kein Escaping
echo $entry->name;

// RICHTIG
echo esc_html($entry->name);
```

---

## View-Templates überschreiben (im Theme)

```
/wp-content/themes/mein-theme/bs-fabric/card.php
/wp-content/themes/mein-theme/bs-fabric/list.php
/wp-content/themes/mein-theme/bs-fabric/detail.php
```

Eigene Views registrieren:
```php
add_filter('bs_fabric_template_path', function(string $path, string $view): string {
    // Eigenen Pfad zurückgeben
    return $path;
}, 10, 2);
```
