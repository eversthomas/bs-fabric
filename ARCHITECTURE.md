# bs-fabric — Architektur & Entwicklungsdokument
> Version 1.0 — Finales Konzeptdokument

---

## 1. Produktvision

**Ein Satz:** ProcessWires Template/Field/Entry-Philosophie als WordPress-Plugin —
visuell für Redakteure, sauber für Entwickler.

**Name:** bs-fabric — ein Gewebe aus Daten, Feldern, Relationen.
Alles hängt zusammen, nichts steht für sich.
Namensraum: `BsFabric\` — Prefix: `bs_fabric_` — API: `bs_fabric()`

**Was es ist:**
- Visueller Template-Builder (Templates definieren, Felder zuweisen)
- Globale Felderbibliothek (Felder sind wiederverwendbare Objekte)
- Sauberer Entry-Editor (nur die Felder des Templates, kein WP-Chaos)
- Chainbare PHP-API `bs_fabric()` für Entwickler im Frontend

**Was es nicht ist:**
- Kein Page Builder
- Kein Gutenberg-Ersatz
- Kein No-Code-Tool für Endkunden
- Kein ACF-Clone

**Warum nicht ACF?**
ACF Pro 6.x kommt näher, aber es fehlt fundamental:
1. Kein echtes relationales Denken — bidirektionale Relationen sind Handarbeit
2. Keine Query-API — man baut immer noch WP_Query mit meta_query
3. Kein View Layer — ACF liefert Daten, Darstellung ist Thema-Aufgabe
4. Keine konzeptuelle Trennung zwischen Schema und Instanz

**Langfristige Richtung (Phase 3+):**
Der `code`-Feldtyp öffnet die Möglichkeit, Templates mit Input- und
Output-Code auszustatten — analog zu REDAXO-Modulen. Das Plugin wird
damit zur Grundlage eines leichtgewichtigen Block-Editors ohne
Gutenberg-Komplexität.

---

## 2. Technische Leitplanken

| Thema | Entscheidung |
|---|---|
| PHP-Version | 8.2+ |
| WordPress-Minimum | 7.0 |
| Autoloading | PSR-4 via Composer |
| Storage | Komplett eigene Tabellen — kein Post Meta, kein CPT |
| Admin JS | Vanilla JS + SortableJS (gezielt) — kein React im MVP |
| Admin CSS | WP 7.0 Core Styles als Basis, eigene Erweiterung |
| Tabellarische Ansicht | DataViews API (WP 7.0 nativ) |
| Queries | Direkt via `$wpdb->prepare()` — kein ORM |
| Hooks | Standard WP Actions/Filters als Rückgrat |
| Feldtypen | Erweiterbar via `bs_fabric_field_types` Filter |
| View-Templates | Überschreibbar via Theme (`/bs-fabric/`) |
| Namensraum | `BsFabric\` |
| Prefix | `bs_fabric_` für alle Tabellen, Options, Hooks, Funktionen |

---

## 3. Benutzerrollen & Capabilities

Zwei eigene Capabilities — nicht an WP-Rollen gekoppelt, frei zuweisbar:

| Capability | Standard | Kann |
|---|---|---|
| `bs_fabric_manage_schema` | Administrator | Templates + Fields anlegen/bearbeiten/löschen |
| `bs_fabric_manage_entries` | Administrator + Redakteur | Entries anlegen, bearbeiten, eigene löschen |

Erweiterbar: Granulare Capabilities pro Template in Phase 2
(`bs_fabric_manage_entries_einrichtung` etc.)

---

## 4. Menü- und Navigationsstruktur

```
📦 Fabric                             ← Top-Level-Menüpunkt, eigenes Icon
   ├── Explorer                       ← alle Entries, nach Template filterbar
   ├── ── Einrichtungen               ← dynamisch generiert pro Template
   ├── ── Ansprechpartner
   ├── ── Events
   ├── Templates                      ← Schema-Verwaltung (bs_fabric_manage_schema)
   ├── Fields                         ← globale Felderbibliothek (bs_fabric_manage_schema)
   └── Einstellungen
```

- Template-Untermenüpunkte werden dynamisch aus der DB generiert
- Redakteure sehen nur Explorer + Template-Punkte, nicht Templates/Fields
- Orientierung an ProcessWire: Setup und Content als gleichwertige Objekte

---

## 5. Feldtypen

9 Basis-Feldtypen — bewusst minimal, maximal vielfältig einsetzbar.
Erweiterbar via `bs_fabric_field_types` Filter.

| Typ | Beschreibung | Typische Verwendung |
|---|---|---|
| `text` | Einzeiliges Textfeld, labelbar | Name, Titel, URL, Telefon, E-Mail |
| `textarea` | Mehrzeiliger Text, kein Markup | Kurzbeschreibung, Notizen |
| `richtext` | Editor (WP-nativ, später Tiptap) | Fließtext, Artikel |
| `image` | Einzelbild via WP Medienmanager | Titelbild, Portrait |
| `file` | Datei via WP Medienmanager | PDF, Download |
| `date` | Datum + optional Uhrzeit | Events, Fristen |
| `select` | Auswahl aus definierten Optionen | Status, Kategorie, Typ |
| `relation` | Verknüpfung zu Entries anderer Templates | Kontakt → Einrichtung |
| `code` | Code-Editor mit Syntaxhighlighting | HTML-Snippets, PHP, CSS |

**Zu `code` (MVP):** Reines Speicherfeld mit CodeMirror-Editor.
Kein `eval()`, keine Ausführung. Phase 3: REDAXO-artige Module.

**Zu `relation`:** Single/Multi konfigurierbar, AJAX-Suche,
sortierbar via SortableJS, eigene Tabelle `bs_fabric_relations`.

**Zu `text`:** `name` ist der eindeutige slug-basierte Identifier
eines Feldes — nach Anlage nicht mehr änderbar. `label` ist der
Anzeigename und jederzeit editierbar.

---

## 6. Datenbankschema

```sql
CREATE TABLE {prefix}bs_fabric_fields (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL UNIQUE,
    label       VARCHAR(200) NOT NULL,
    type        VARCHAR(50)  NOT NULL,
    settings    JSON,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE {prefix}bs_fabric_templates (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL UNIQUE,
    label       VARCHAR(200) NOT NULL,
    title_field VARCHAR(100),
    settings    JSON,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE {prefix}bs_fabric_template_fields (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    template_id INT UNSIGNED NOT NULL,
    field_id    INT UNSIGNED NOT NULL,
    sort_order  INT UNSIGNED DEFAULT 0,
    is_required TINYINT(1)   DEFAULT 0,
    UNIQUE KEY unique_assignment (template_id, field_id),
    FOREIGN KEY (template_id) REFERENCES {prefix}bs_fabric_templates(id) ON DELETE CASCADE,
    FOREIGN KEY (field_id)    REFERENCES {prefix}bs_fabric_fields(id)    ON DELETE RESTRICT
);

CREATE TABLE {prefix}bs_fabric_entries (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    template_id INT UNSIGNED NOT NULL,
    title       VARCHAR(500),
    status      ENUM('draft','published','archived') DEFAULT 'draft',
    language    VARCHAR(10)  DEFAULT 'de',
    created_by  BIGINT UNSIGNED,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (template_id) REFERENCES {prefix}bs_fabric_templates(id) ON DELETE RESTRICT
);

CREATE TABLE {prefix}bs_fabric_entry_values (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entry_id    INT UNSIGNED NOT NULL,
    field_id    INT UNSIGNED NOT NULL,
    value       LONGTEXT,
    UNIQUE KEY unique_value (entry_id, field_id),
    FOREIGN KEY (entry_id) REFERENCES {prefix}bs_fabric_entries(id)  ON DELETE CASCADE,
    FOREIGN KEY (field_id) REFERENCES {prefix}bs_fabric_fields(id)   ON DELETE RESTRICT
);

CREATE TABLE {prefix}bs_fabric_relations (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    from_entry_id   INT UNSIGNED NOT NULL,
    to_entry_id     INT UNSIGNED NOT NULL,
    field_id        INT UNSIGNED NOT NULL,
    sort_order      INT UNSIGNED DEFAULT 0,
    FOREIGN KEY (from_entry_id) REFERENCES {prefix}bs_fabric_entries(id) ON DELETE CASCADE,
    FOREIGN KEY (to_entry_id)   REFERENCES {prefix}bs_fabric_entries(id) ON DELETE CASCADE,
    FOREIGN KEY (field_id)      REFERENCES {prefix}bs_fabric_fields(id)  ON DELETE RESTRICT
);
```

---

## 7. Ordnerstruktur

```
bs-fabric/
│
├── bs-fabric.php                  ← Plugin-Header, Bootstrap
├── composer.json
├── composer.lock
├── uninstall.php
├── ARCHITECTURE.md
├── .cursorrules
├── .cursor/
│   └── bs-fabric.md               ← Cursor Skill
│
├── src/
│   ├── Core/
│   │   ├── Plugin.php
│   │   └── ServiceContainer.php
│   ├── Database/
│   │   ├── Installer.php
│   │   └── Migrator.php
│   ├── Fields/
│   │   ├── FieldRepository.php
│   │   ├── FieldRegistry.php
│   │   └── Types/
│   │       ├── FieldTypeInterface.php
│   │       ├── TextField.php
│   │       ├── TextareaField.php
│   │       ├── RichtextField.php
│   │       ├── ImageField.php
│   │       ├── FileField.php
│   │       ├── DateField.php
│   │       ├── SelectField.php
│   │       ├── RelationField.php
│   │       └── CodeField.php
│   ├── Templates/
│   │   ├── TemplateRepository.php
│   │   └── Template.php
│   ├── Entries/
│   │   ├── EntryRepository.php
│   │   ├── Entry.php
│   │   └── EntryCollection.php
│   ├── Relations/
│   │   └── RelationRepository.php
│   ├── Query/
│   │   └── EntryQuery.php
│   ├── Admin/
│   │   ├── MenuManager.php
│   │   ├── Ajax/
│   │   │   ├── FieldAjax.php
│   │   │   ├── TemplateAjax.php
│   │   │   └── EntryAjax.php
│   │   └── Screens/
│   │       ├── FieldsScreen.php
│   │       ├── TemplatesScreen.php
│   │       ├── EntryListScreen.php
│   │       ├── EntryEditScreen.php
│   │       └── ExplorerScreen.php
│   ├── Frontend/
│   │   └── Renderer.php
│   └── Api/
│       └── PublicApi.php
│
├── templates/
│   └── default/
│       ├── card.php
│       ├── list.php
│       └── detail.php
│
├── assets/
│   ├── css/
│   │   └── admin.css
│   └── js/
│       ├── admin.js
│       └── sortable.min.js
│
├── tests/
│   ├── bootstrap.php
│   ├── Unit/
│   │   ├── Fields/
│   │   │   ├── FieldRegistryTest.php
│   │   │   └── Types/
│   │   │       ├── TextFieldTest.php
│   │   │       └── RelationFieldTest.php
│   │   ├── Query/
│   │   │   └── EntryQueryTest.php
│   │   └── Entries/
│   │       ├── EntryTest.php
│   │       └── EntryCollectionTest.php
│   └── Integration/
│       ├── Database/
│       │   └── InstallerTest.php
│       ├── Fields/
│       │   └── FieldRepositoryTest.php
│       ├── Templates/
│       │   └── TemplateRepositoryTest.php
│       └── Entries/
│           └── EntryRepositoryTest.php
│
└── languages/
    └── bs-fabric.pot
```

---

## 8. Frontend-API

### bs_fabric() — globaler Einstiegspunkt

```php
bs_fabric('einrichtung')          // → EntryQuery
bs_fabric('einrichtung#42')       // → Entry
bs_fabric('einrichtung', 42)      // → Entry (Alternativ-Syntax)
```

### EntryQuery — Fluent Builder

```php
bs_fabric('einrichtung')
    ->where('stadt', 'Wesel')
    ->where('status', 'published')
    ->with('ansprechpartner')
    ->orderBy('name', 'ASC')
    ->limit(10)
    ->get()                        // → EntryCollection

bs_fabric('einrichtung')->first()  // → ?Entry
bs_fabric('einrichtung')->count()  // → int
```

### Entry — Value Object

```php
$e = bs_fabric('einrichtung#42');

echo $e->name;
echo $e->titelbild->url;
echo $e->titelbild->alt;
echo $e->datum->format('d.m.Y');

foreach ($e->ansprechpartner as $person) {
    echo $person->name;
    echo $person->email;
}

echo $e->render('card');
$e->toArray();
$e->toJson();
```

### EntryCollection

```php
$collection = bs_fabric('einrichtung')
    ->where('aktiv', true)
    ->get();

foreach ($collection as $entry) { ... }

echo $collection->render('card');
echo $collection->render('card', '<div class="cards">', '</div>');

$collection->toArray();
$collection->count();
$collection->first();
```

### Im Theme

```php
<?= bs_fabric('einrichtung')
    ->where('featured', true)
    ->limit(3)
    ->get()
    ->render('card') ?>
```

---

## 9. FieldTypeInterface

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

## 10. Hooks

```php
add_filter('bs_fabric_field_types',       $callback);
add_action('bs_fabric_before_entry_save', fn(int $id, array $data) => ..., 10, 2);
add_action('bs_fabric_after_entry_save',  fn(int $id, array $data) => ..., 10, 2);
add_action('bs_fabric_before_entry_delete', fn(int $id) => ...);
add_action('bs_fabric_after_entry_delete',  fn(int $id) => ...);
add_filter('bs_fabric_query_args',        $callback);
add_filter('bs_fabric_template_path',     $callback);
```

---

## 11. Sicherheit

| Thema | Maßnahme |
|---|---|
| Formulare + AJAX | Nonces überall, ohne Ausnahme |
| Capabilities | Jede Aktion prüft `bs_fabric_manage_schema` oder `bs_fabric_manage_entries` |
| DB-Queries | Ausschließlich `$wpdb->prepare()` |
| Ausgabe | `esc_html()`, `esc_attr()`, `wp_kses_post()` kontextabhängig |
| `code`-Feldtyp | Nur Speicherung — kein `eval()` im MVP |
| Uninstall | `uninstall.php` entfernt alle Tabellen und Options |

---

## 12. WordPress 7.0

| Feature | Nutzung |
|---|---|
| DataViews API | Explorer + Entry-Listen |
| WP AI Client | Phase 2+: Felder aus Beschreibung vorschlagen |
| Admin UI Refresh | Eigene Styles auf WP 7.0 Core aufbauen |

---

## 13. Entwicklungsphasen

### Phase 1 — MVP
- [ ] Plugin-Bootstrap + PSR-4
- [ ] Installer: 6 Tabellen
- [ ] 9 Feldtypen + FieldTypeInterface
- [ ] FieldRepository + FieldRegistry
- [ ] TemplateRepository
- [ ] EntryRepository + RelationRepository
- [ ] Admin: Fields, Templates, Entry List, Entry Editor
- [ ] Admin: Dynamische Menüstruktur
- [ ] Capabilities
- [ ] Frontend: EntryQuery, Entry, EntryCollection
- [ ] Frontend: bs_fabric() API
- [ ] View-Templates: card, list, detail
- [ ] Unit + Integration Tests (Kern)

### Phase 2
- [ ] Bidirektionale Relationen
- [ ] Explorer Screen (DataViews)
- [ ] Erweiterte Query Engine
- [ ] WP AI Client Integration

### Phase 3
- [ ] `code`-Feldtyp zu REDAXO-Modul erweitern
- [ ] Tiptap als Richtext-Feldtyp
- [ ] REST API Layer
- [ ] Gutenberg Block
- [ ] Import/Export Templates (JSON)
- [ ] Repeater-Feldtyp

### Phase 4
- [ ] Mehrsprachigkeit
- [ ] Headless API Layer

---

## 14. Cursor-Sessions

### Session-Übersicht

```
Session 01 — Plugin-Bootstrap
  Ziel:    Plugin lädt, PSR-4 funktioniert, keine Fehler
  Dateien: bs-fabric.php, composer.json, src/Core/Plugin.php,
           src/Core/ServiceContainer.php
  Tests:   Manuell: Plugin aktivieren ohne Fehler

Session 02 — Datenbankinstallation
  Ziel:    Alle 6 Tabellen korrekt angelegt
  Dateien: src/Database/Installer.php, src/Database/Migrator.php
  Tests:   Integration: InstallerTest.php

Session 03 — Field Type System
  Ziel:    FieldTypeInterface + alle 9 Feldtypen
  Dateien: src/Fields/Types/*
  Tests:   Unit: TextFieldTest.php, RelationFieldTest.php

Session 04 — Field Repository + Fields Screen
  Ziel:    Felder CRUD im Admin funktioniert
  Dateien: src/Fields/FieldRepository.php, src/Fields/FieldRegistry.php,
           src/Admin/Screens/FieldsScreen.php
  Tests:   Integration: FieldRepositoryTest.php
           Manuell: Feld anlegen, bearbeiten, löschen

Session 05 — Template Repository + Templates Screen
  Ziel:    Templates anlegen, Felder zuweisen via Drag&Drop
  Dateien: src/Templates/*, src/Admin/Screens/TemplatesScreen.php
  Tests:   Integration: TemplateRepositoryTest.php
           Manuell: Template mit 3 Feldern anlegen, Reihenfolge ändern

Session 06 — Entry Repository + Relations
  Ziel:    Entries mit Feldwerten und Relationen speichern/laden
  Dateien: src/Entries/*, src/Relations/RelationRepository.php
  Tests:   Integration: EntryRepositoryTest.php
           Unit: EntryTest.php, EntryCollectionTest.php

Session 07 — Entry Editor Screen
  Ziel:    Redakteur kann Entry anlegen, befüllen, Relationen setzen
  Dateien: src/Admin/Screens/EntryEditScreen.php,
           src/Admin/Screens/EntryListScreen.php,
           src/Admin/Ajax/EntryAjax.php
  Tests:   Manuell: Entry mit allen 9 Feldtypen anlegen und speichern

Session 08 — Menüstruktur + Capabilities
  Ziel:    Dynamische Menüpunkte, Rollen greifen korrekt
  Dateien: src/Admin/MenuManager.php
  Tests:   Manuell: Als Redakteur einloggen — Templates/Fields unsichtbar

Session 09 — Frontend Query API
  Ziel:    bs_fabric() im Theme verwendbar
  Dateien: src/Query/EntryQuery.php, src/Api/PublicApi.php
  Tests:   Unit: EntryQueryTest.php
           Manuell: bs_fabric('template')->get() im Theme ausgeben

Session 10 — Renderer + View Templates
  Ziel:    ->render() und collection->render() funktionieren
  Dateien: src/Frontend/Renderer.php, templates/default/*
  Tests:   Manuell: card, list, detail im Theme ausgeben
```

### Definition of Done (pro Session)

Jede Session gilt als abgeschlossen wenn:
1. Alle definierten Dateien existieren und laden ohne PHP-Fehler
2. Zugehörige Tests grün
3. Manuelle Checkliste vollständig abgehakt
4. Keine direkten `$wpdb->query()` ohne `prepare()`
5. Keine fehlenden Nonce-Prüfungen in AJAX-Handlern

---

## 15. Test-Strategie

### Was getestet wird

**Unit Tests** — isolierte Logik ohne DB:
- `FieldRegistry`: Typen registrieren, abrufen, unbekannten Typ abfangen
- `EntryQuery`: Query-Builder-Logik, where/orderBy/limit korrekt
- `Entry`: Magic `__get`, `toArray()`, `toJson()`
- `EntryCollection`: Iteration, `first()`, `count()`, `render()`
- `Renderer`, `PublicApi`: Frontend-Ausgabe und API-Einstieg
- `Migrator`: Versionsvergleich — `install()` nur bei veralteter `bs_fabric_db_version`
- Alle Feldtypen: `sanitizeValue()` mit validen + invaliden Eingaben

**Integration Tests** — gegen echte Test-DB (WordPress `wp-load.php`):
- `InstallerTest`: Alle 6 Tabellen existieren nach Aktivierung
- `FieldRepositoryTest`: CRUD vollständig, unique constraint auf `name`
- `TemplateRepositoryTest`: Template anlegen, Felder zuweisen, sortieren
- `EntryRepositoryTest`: Entry anlegen, Werte speichern, laden, löschen

**Manuelle Checklisten** — pro Session in Session-Übersicht definiert

### Was nicht getestet wird (bewusst)
- Admin-Screen-HTML (zu fragil)
- CSS/JS (manuell)
- View-Templates (manuell)
- WP-Core-Verhalten

### Integration Tests — standardmäßig deaktiviert

Integration Tests leeren **vor jedem Testfall alle Zeilen** in den sechs `bs_fabric_*`-Tabellen.
Sie laufen **nicht automatisch** mit `composer test`, damit die Dev-Site nicht versehentlich
geleert wird.

Aktivierung nur über Umgebungsvariablen in `tests/Integration/TestCase.php`:

| Variable | Wert | Zweck |
|---|---|---|
| `BS_FABRIC_INTEGRATION_TESTS` | `1` | Integration-Suite explizit erlauben |
| `BS_FABRIC_TEST_DB_NAME` | z. B. `wordpress_test` | Muss exakt `DB_NAME` der Test-Installation sein |

Fehlt eine Variable oder stimmt der DB-Name nicht, werden alle Integration Tests per `markTestSkipped` übersprungen — kein Datenverlust auf der falschen Datenbank.

```bash
export BS_FABRIC_INTEGRATION_TESTS=1
export BS_FABRIC_TEST_DB_NAME=wordpress_test
composer test:all
```

### Empfohlener lokaler Workflow

```
┌─────────────────────┐     ┌─────────────────────┐
│  Dev-Site (MAMP/    │     │  Test-Installation  │
│  Local, wp-env …)   │     │  (eigene DB_NAME)   │
├─────────────────────┤     ├─────────────────────┤
│ Templates, Fields,  │     │ Nur für Integration │
│ Entries, manuelles  │     │ Tests — wird vor    │
│ QA                  │     │ jedem Test geleert  │
├─────────────────────┤     ├─────────────────────┤
│ composer test  ✓    │     │ composer test:all   │
│ (Unit, sicher)      │     │ (nur mit Env-Vars)  │
└─────────────────────┘     └─────────────────────┘
```

1. **Dev-Site** — Haupt-WordPress für Entwicklung und manuelle Checks. `composer test` jederzeit ohne DB-Risiko.
2. **Test-DB getrennt** — zweite WP-Instanz oder wp-env-Container; `BS_FABRIC_TEST_DB_NAME` auf deren `DB_NAME` setzen.
3. **Snapshot vor Risiko** — mysqldump der sechs Tabellen (Prefix anpassen):

```bash
mysqldump -u root wordpress \
  wp_bs_fabric_fields \
  wp_bs_fabric_templates \
  wp_bs_fabric_template_fields \
  wp_bs_fabric_entries \
  wp_bs_fabric_entry_values \
  wp_bs_fabric_relations \
  > bs-fabric-backup.sql
```

### Daten-Sicherheit (Plugin-Lebenszyklus)

| Aktion | Auswirkung auf Daten |
|---|---|
| Code-Änderung | Keine |
| Deaktivieren / Reaktivieren | Keine — `Migrator` ruft `dbDelta` nur bei veralteter Version auf |
| `composer test` (Unit) | Keine |
| `composer test:all` auf Test-DB | Alle Zeilen in `bs_fabric_*` werden gelöscht |
| Plugin **löschen** (nicht deaktivieren) | `uninstall.php` droppt alle sechs Tabellen + Option `bs_fabric_db_version` |

### Test-Setup

```json
// composer.json (Ausschnitt)
{
  "require-dev": {
    "phpunit/phpunit": "^11.0",
    "brain/monkey": "^2.6"
  },
  "scripts": {
    "test": "phpunit --testsuite unit",
    "test:unit": "phpunit --testsuite unit",
    "test:integration": "phpunit --testsuite integration",
    "test:all": "phpunit --testsuite unit && phpunit --testsuite integration"
  }
}
```

```php
// phpunit.xml
<testsuites>
  <testsuite name="unit">
    <directory>tests/Unit</directory>
  </testsuite>
  <testsuite name="integration">
    <directory>tests/Integration</directory>
  </testsuite>
</testsuites>
```

Details und englische Kurzfassung: `README.md` → Abschnitt **Development workflow**.

---

*Stand: Version 1.0 — Finales Konzeptdokument*
*Plugin: bs-fabric | Namespace: BsFabric\ | API: bs_fabric()*
*Nächster Schritt: Session 01 — Plugin-Bootstrap*
