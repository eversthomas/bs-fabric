# bs-fabric — Architektur & Entwicklungsdokument
> Version 1.1 — Stand Juni 2026 (nach Design System DS-0–DS-3)
>
> **Dokumentations-Hierarchie:** Dieses Dokument ist die **einzige Quelle der Wahrheit**
> für Architektur, Roadmap und Entwicklungsphasen. Ergänzend: `README.md` (Install/API EN),
> `CODE_ANALYSIS.md` (technisches Audit), `Design System/README.md` (UI-Referenz, abgeschlossen).

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

**Langfristige Richtung (Feature-Roadmap B2+):**
Der `code`-Feldtyp öffnet die Möglichkeit, Templates mit Input- und
Output-Code auszustatten — analog zu REDAXO-Modulen (**DesignBlocks**:
wiederverwendbare Input-/Output-Snippets pro Template, ohne Gutenberg).
Das Plugin wird damit zur Grundlage eines leichtgewichtigen Block-Editors
ohne Gutenberg-Komplexität. Details: §5 (geplante Feldtypen), §13 (Roadmap).

---

## 2. Technische Leitplanken

| Thema | Entscheidung |
|---|---|
| PHP-Version | 8.2+ |
| WordPress-Minimum | 7.0 |
| Autoloading | PSR-4 via Composer |
| Storage | Komplett eigene Tabellen — kein Post Meta, kein CPT |
| Admin UI | **Hybrid-Shell:** WP-Admin-Menü links bleibt, Content-Bereich in `.bsf-app` (`AdminShell`) |
| Admin JS | Vanilla JS (`bsf-ui.js`, `bsf-schema.js`, `admin.js`) — **Explorer** = einzige React-Ausnahme |
| Admin CSS | Design System unter `assets/css/bsf-*.css`, scoped auf `.bsf-app` — Referenz: `Design System/` |
| Tabellarische Ansicht | Custom `bsf-tbl` in PHP-Screens; Explorer nutzt React-Bundle + DataViews-Skin |
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
| `bs_fabric_manage_entries` | Administrator + Redakteur | Entries anlegen, bearbeiten, löschen |

**MVP-Hinweis:** Die Doku-Zelle „eigene löschen" beschreibt das **Ziel**.
Aktuell darf jeder Nutzer mit `bs_fabric_manage_entries` **jeden** Entry
bearbeiten/löschen — unabhängig von `created_by`. Ownership-Check → Strang D (§13).

Erweiterbar: Granulare Capabilities pro Template in der Feature-Roadmap
(`bs_fabric_manage_entries_einrichtung` etc.)

---

## 4. Menü- und Navigationsstruktur

```
📦 Fabric                             ← Top-Level (Label konfigurierbar in Konfiguration)
   ├── Übersicht                       ← Dashboard
   ├── Explorer                        ← alle Entries, nach Template filterbar (React-UI)
   ├── Einstellungen                   ← Tabs: Templates | Fields | Doku | Konfiguration
   ├── ── Einrichtungen                 ← dynamisch pro Template (show_in_menu)
   ├── ── Ansprechpartner
   └── …                               ← weitere Template-Listen (Icon + Trennlinien im WP-Menü)
```

- Template-Untermenüpunkte werden dynamisch aus der DB generiert (`TemplateSettings::showInMenu`)
- **Einstellungen** = früher „Schema“ (`page=bs-fabric-schema`); enthält Struktur (Templates/Fields) + Doku + **Konfiguration**
- Template-Listen heben sich im WP-Sidebar-Menü visuell ab (Listen-Icon, Trennlinien) — siehe `assets/css/admin-menu.css`
- Redakteure sehen Übersicht, Explorer, Template-Punkte und Doku — nicht Templates/Fields/Konfiguration
- Legacy-URLs (`bs-fabric-templates`, `bs-fabric-fields`, `bs-fabric-docs`) leiten auf Einstellungen-Tabs um

### Admin-Design (Hybrid-Shell)

Referenz und Prototyp: `Design System/README.md`

| Ebene | Umsetzung |
|---|---|
| WP-Sidebar | Unverändert (WP-Core-Menü); optionales Styling via `admin-menu.css` |
| Content | `.bsf-app` via `AdminShell` — Topbar, Breadcrumbs, PageHead |
| Komponenten | PHP unter `src/Admin/Components/` (`Button`, `Icon`, `StatusBadge`, …) |
| Assets | `bsf-tokens.css`, `bsf-components.css`, `bsf-wp-bridge.css`, `bsf-schema.css`, `bsf-entries.css` |
| Interaktion | Sheets, Toasts (`bsf-ui.js`), SortableJS im Template-Builder |

Kein Full-SPA — jeder Screen ist eine WP-Admin-Page mit PHP-Rendering + punktuellem JS.
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
Kein `eval()`, keine Ausführung. Feature-Roadmap: REDAXO-artige Module.

**Zu `relation`:** Single/Multi konfigurierbar, AJAX-Suche,
sortierbar via SortableJS, eigene Tabelle `bs_fabric_relations`.

**Zu `text`:** `name` ist der eindeutige slug-basierte Identifier
eines Feldes — nach Anlage nicht mehr änderbar. `label` ist der
Anzeigename und jederzeit editierbar.

### Geplante Feldtypen & Erweiterungen (noch nicht implementiert)

Diese Typen sind **bewusst zurückgestellt** — nicht vergessen, sondern
der Feature-Roadmap (§13) zugeordnet. Der Prototyp (`Design System/prototype/data.js`)
nutzt teils andere Namen (z. B. `media` statt getrennt `image`/`file`).

| Geplant | Beschreibung | Roadmap | Anmerkung |
|---|---|---|---|
| `number` | Numerischer Wert (Min/Max/Einheit) | B1 | Im Prototyp vorhanden |
| `repeater` | Wiederholbare Feldgruppe (Label+Wert oder Sub-Felder) | B2 | Braucht Schema-Erweiterung oder JSON in `entry_values` |
| `reference` | Verweis auf **WordPress-Inhalte** (Seiten, Beiträge, Medien) | B1 | Abgrenzung zu `relation` (Fabric-Entries untereinander) |
| `boolean` | Ja/Nein-Schalter | B1 | Häufiger Bedarf; aktuell über `select` abbildbar |
| `url` / `email` | Validierte Spezial-Textfelder | B1 | Aktuell über `text` + Validierung im Theme |
| `gallery` | Mehrfach-Bilder (Mediathek) | B1 | Prototyp: `media` mit Mehrfachauswahl |
| `multiselect` | Mehrfach-Auswahl aus Optionen | B1 | Erweiterung von `select` |
| **Tiptap-Richtext** | Ersetzt/ergänzt WP-Editor in `richtext` | B2 | Kein neuer Typ-Key nötig — Wechsel der Editor-Engine |
| **DesignBlocks** | `code`-Feldtyp → ausführbare REDAXO-artige Module (Input/Output-Template) | B2 | Langfrist-Vision §1; kein `eval()` im MVP |
| `color` | Farbwähler | Backlog | Nice-to-have |
| `map` / `geo` | Koordinaten / Karte | Backlog | Nice-to-have |

**Abgelehnt / bewusst anders:**
- Status `review` („In Prüfung") — Prototyp only; Produktion: `draft` \| `published` \| `archived`
- Prototyp-Feld `media` — in Produktion aufgeteilt in `image` + `file`

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
│   │   ├── Capabilities.php
│   │   ├── PluginSettings.php
│   │   └── ServiceContainer.php
│   ├── Database/
│   │   ├── Installer.php
│   │   ├── Migrator.php
│   │   └── LegacyDataCleanup.php
│   ├── Fields/ …
│   ├── Templates/ …
│   ├── Entries/ …
│   ├── Relations/ …
│   ├── Query/ …
│   ├── Admin/
│   │   ├── MenuManager.php
│   │   ├── SchemaExporter.php
│   │   ├── DocsExampleRenderer.php
│   │   ├── Components/          ← AdminShell, Button, Icon, PageHead, …
│   │   ├── Ajax/
│   │   └── Screens/
│   │       ├── DashboardScreen.php
│   │       ├── SchemaScreen.php       ← Einstellungen (Tabs)
│   │       ├── FieldsScreen.php
│   │       ├── TemplatesScreen.php
│   │       ├── DocsScreen.php
│   │       ├── PluginSettingsScreen.php
│   │       ├── EntryListScreen.php
│   │       ├── EntryEditScreen.php
│   │       └── ExplorerScreen.php
│   ├── Frontend/
│   │   └── Renderer.php
│   └── Api/
│       ├── PublicApi.php
│       └── bs_fabric.php
│
├── Design System/               ← Prototyp + Handoff (Referenz, kein Produktionscode)
│   ├── README.md
│   ├── tokens.css
│   └── prototype/
│
├── assets/
│   ├── css/
│   │   ├── bsf-tokens.css
│   │   ├── bsf-components.css
│   │   ├── bsf-wp-bridge.css
│   │   ├── bsf-schema.css
│   │   ├── bsf-entries.css
│   │   ├── admin-menu.css
│   │   └── admin.css            ← Legacy / Explorer-Reste
│   ├── js/
│   │   ├── bsf-ui.js
│   │   ├── bsf-schema.js
│   │   ├── admin.js
│   │   ├── entry-admin.js
│   │   └── sortable.min.js
│   └── build/explorer/          ← React-Explorer (DataViews)
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
| Entry-Ownership | MVP: jeder mit `manage_entries` darf alle Entries ändern/löschen |
| Uninstall | Tabellen nur bei Option `delete_on_uninstall`; Capabilities werden nicht entfernt |
| Third-Party-Feldtypen | `renderInput()`/`renderSettings()` — Escaping-Verantwortung beim Entwickler (Interface-Docblock offen) |

---

## 12. WordPress 7.0

| Feature | Nutzung |
|---|---|
| DataViews API | Explorer (React-Bundle), gestylt via `bsf-entries.css` |
| WP AI Client | Feature B1: Felder aus Beschreibung vorschlagen |
| Admin UI | Hybrid-Shell + Design System (`bsf-*`), WP-Menü unverändert |

---

## 13. Roadmap — Gesamtübersicht

> **Legende:** ✓ erledigt · ◐ teilweise · ○ geplant · — bewusst zurückgestellt

### Übersicht aller Stränge

| Strang | Inhalt | Status |
|---|---|---|
| **C — MVP Backend** | Tabellen, Repositories, 9 Feldtypen, Frontend-API, Admin (funktional) | ✓ |
| **UX + Doku** | Menü/Listen, ImageField-Polish, DocsScreen | ✓ |
| **A — Design System** | Hybrid-Shell, `bsf-*` CSS/JS, DS-0–DS-3 | ✓ |
| **D — Production-Härtung** | Performance, Sicherheit, Stabilität (Code-Audit) | ○ |
| **E — Design-Nachzügler** | Prototyp-Lücken im Admin-UI | ○ |
| **B — Feature-Roadmap** | B1 → B2 → B3 (Backend + Feldtypen + Integrationen) | ○ |

**Empfohlene Reihenfolge:** D (Blocker) → wahlweise E oder B1 → B2 → B3

---

### Strang C — MVP Backend ✓

- [x] Plugin-Bootstrap + PSR-4 + ServiceContainer
- [x] Installer: 6 Tabellen + Migrator + LegacyDataCleanup (1.0.1)
- [x] 9 Feldtypen + FieldTypeInterface + FieldRegistry
- [x] FieldRepository, TemplateRepository, EntryRepository, RelationRepository
- [x] Admin: Fields, Templates, Entry List/Edit, Explorer (React), Capabilities
- [x] Frontend: `bs_fabric()`, EntryQuery, Entry, EntryCollection, Renderer
- [x] View-Templates: card, list, detail
- [x] Unit- + Integration-Tests (Kern; Integration per Env-Vars)
- [x] uninstall.php (bedingtes Löschen via `PluginSettings::DELETE_ON_UNINSTALL`)

### UX + Doku ✓

- [x] Schema-Tabs, `show_in_list`, `show_in_menu`, dynamische Listen-Spalten
- [x] wp.media pro Bild/Datei, Entry-Editor-Polish
- [x] DocsScreen, TemplateCodeSnippets, Datenzugriff vs. `render()`

---

### Strang A — Design System ✓ (DS-0–DS-3)

Referenz: `Design System/README.md` · Prototyp: `Design System/prototype/`

| Phase | Ziel | Ergebnis |
|---|---|---|
| **DS-0** | Shell + Dashboard | `AdminShell`, Tokens, Dashboard als Startseite |
| **DS-1** | Schema-Bereich | Fields + Templates (Sheets, Builder, Karten) |
| **DS-2** | Entry-Workflow | Listen, Editor, Doku, Explorer-Skin |
| **DS-3** | Konfiguration | PluginSettings, JSON-Export, Menü-Polish |

**Fixierte Design-Entscheidungen:** Hybrid-Shell · Explorer = React · Status ohne `review` ·
Einstellungen = Templates + Fields + Doku + Konfiguration

---

### Strang D — Production-Härtung ✓ (aus CODE_ANALYSIS.md)

Priorität vor produktivem Einsatz mit vielen Entries.

#### P0 — Blocker

| # | Thema | Aufwand |
|---|---|---|
| D-1 | N+1 / O(N×M) Queries beim Entry-Laden (Batch-Values, Template-Fields-Cache) | 4–8 h | ✓ |
| D-2 | `bs_fabric()` in Admin-AJAX-Kontext verfügbar machen | ~30 min | ✓ |
| D-3 | DB-Transaktionen bei Entry Create/Update | ~2 h | ✓ |

#### P1 — wichtig

| # | Thema |
|---|---|
| D-4 | Side-Effects in Read-Methoden (`maybeBackfillStoredTitle` aus Reads entfernen) | ✓ |
| D-5 | Explorer: serverseitige Pagination statt alles in `wp_localize_script` | ✓ |
| D-6 | DB-Indexes (`status`, `created_at`, Relations) | ✓ |
| D-7 | `RuntimeException` in EntryEdit/TemplatesScreen abfangen | ✓ |
| D-8 | Field-Settings: `sanitizeSettings()` pro Feldtyp | ✓ |

#### P2 — Tech-Debt

| # | Thema |
|---|---|
| D-9 | Capabilities bei Uninstall entfernen + `has_cap`-Check in `register()` | ✓ |
| D-10 | `admin-menu.css` nur auf Fabric-Seiten laden | ✓ |
| D-11 | AJAX-Handler-Tests (EntryAjax, FieldAjax, TemplateAjax) | ✓ |
| D-12 | `decodeValueFromStorage()` — keine auto int/float-Koersion für Text | ✓ |
| D-13 | Übersetzungen: `de_DE.po` aus `.pot` | ✓ |

---

### Strang E — Design-Nachzügler ○ (Prototyp vs. Produktion)

Aus `Design System/prototype/` — UI/UX, keine neuen Feldtypen.

- [ ] Entry-Einzelansicht (read-only Detail, `EntryScreen` im Prototyp)
- [ ] Listen-Ansicht: Karten-Modus neben Tabelle
- [ ] Bulk-Aktionen in Entry-Listen (Veröffentlichen, Duplizieren, Löschen)
- [ ] Interaktive Rollen-Matrix in Konfiguration (aktuell: Info-Text)
- [ ] CSV-Export (JSON-Export existiert bereits)
- [ ] Autosave-Option (Prototyp-Einstellung; Backend noch offen)
- [ ] Legacy `admin.css` schrittweise entfernen
- [ ] **Redakteur-Hinweise Daten-Lebenszyklus** — was Deaktivieren vs. Löschen für Einträge/Listen bedeutet (Dashboard-Karte + Konfiguration; siehe §15)
- [ ] **Excel-Export pro Template-Liste** — sichtbare Listenspalten + Kernfelder als `.xlsx` oder Excel-kompatibles CSV (UTF-8 BOM); Button in `EntryListScreen` (siehe §15)

---

### Strang B — Feature-Roadmap ○

#### B1 — Kern-Erweiterungen (früher „Phase 2")

- [ ] Bidirektionale Relationen
- [ ] Explorer: Filter + Pagination (Backend; UI teils mit D-5)
- [ ] Erweiterte Query Engine (`whereIn`, OR, Pagination; DISTINCT+ORDER-BY-Fix)
- [ ] WP AI Client — Felder aus Beschreibung vorschlagen
- [ ] Granulare Capabilities pro Template + Entry-Ownership
- [ ] Feldtypen: `number`, `reference`, `boolean`, `gallery`, `multiselect`
- [ ] Default-Statusfilter in Frontend-Queries (Dokumentation + optional API-Default)
- [ ] `EntryQuery::language()` (Spalte `language` existiert)

#### B2 — Integration & Module (früher „Phase 3")

- [ ] JSON-**Import** (Export ✓ in Konfiguration)
- [ ] REST API Layer (`bs-fabric/v1`)
- [ ] **DesignBlocks:** `code`-Feldtyp → REDAXO-artige Module (Input/Output-Code)
- [ ] Tiptap als Richtext-Editor (Ersatz für WP-Editor in `richtext`)
- [ ] Gutenberg Block für Fabric-Entries
- [ ] Repeater-Feldtyp
- [ ] Konfigurierbare card-View-Felder (aktuell: erstes Text- + Bildfeld)

#### B3 — Skalierung (früher „Phase 4")

- [ ] Mehrsprachigkeit (Entries pro Sprache, Query-Filter)
- [ ] Headless API Layer
- [ ] Backlog-Feldtypen: `color`, `map`/`geo`

---

### Was bewusst **nicht** geplant ist

- Full-SPA-Admin (Hybrid-Shell bleibt)
- Post Meta / CPT als Storage
- Gutenberg als Ersatz für den Entry-Editor
- Status `review` / „In Prüfung"
- `eval()` für `code`-Felder im MVP

---

**Nächster Schritt (Empfehlung):** Strang E — Redakteur-Hinweise + Excel-Export, oder Strang B1.
---

## 14. Cursor-Sessions

### Session-Übersicht

Phase-1-Sessions (01–10), UX/Doku-Sessions und Design-System-Phasen (DS-0–DS-3) sind abgeschlossen.

```
Session 01 — Plugin-Bootstrap ✓
  Ziel:    Plugin lädt, PSR-4 funktioniert, keine Fehler
  Dateien: bs-fabric.php, composer.json, src/Core/Plugin.php,
           src/Core/ServiceContainer.php
  Tests:   Manuell: Plugin aktivieren ohne Fehler

Session 02 — Datenbankinstallation ✓
  Ziel:    Alle 6 Tabellen korrekt angelegt
  Dateien: src/Database/Installer.php, src/Database/Migrator.php
  Tests:   Integration: InstallerTest.php  ← geplant, noch nicht angelegt

Session 03 — Field Type System ✓
  Ziel:    FieldTypeInterface + alle 9 Feldtypen
  Dateien: src/Fields/Types/*
  Tests:   Unit: TextFieldTest.php, RelationFieldTest.php

Session 04 — Field Repository + Fields Screen ✓
  Ziel:    Felder CRUD im Admin funktioniert
  Dateien: src/Fields/FieldRepository.php, src/Fields/FieldRegistry.php,
           src/Admin/Screens/FieldsScreen.php
  Tests:   Integration: FieldRepositoryTest.php
           Manuell: Feld anlegen, bearbeiten, löschen

Session 05 — Template Repository + Templates Screen ✓
  Ziel:    Templates anlegen, Felder zuweisen via Drag&Drop
  Dateien: src/Templates/*, src/Admin/Screens/TemplatesScreen.php
  Tests:   Integration: TemplateRepositoryTest.php
           Manuell: Template mit 3 Feldern anlegen, Reihenfolge ändern

Session 06 — Entry Repository + Relations ✓
  Ziel:    Entries mit Feldwerten und Relationen speichern/laden
  Dateien: src/Entries/*, src/Relations/RelationRepository.php
  Tests:   Integration: EntryRepositoryTest.php
           Unit: EntryTest.php, EntryCollectionTest.php

Session 07 — Entry Editor Screen ✓
  Ziel:    Redakteur kann Entry anlegen, befüllen, Relationen setzen
  Dateien: src/Admin/Screens/EntryEditScreen.php,
           src/Admin/Screens/EntryListScreen.php,
           src/Admin/Ajax/EntryAjax.php
  Tests:   Manuell: Entry mit allen 9 Feldtypen anlegen und speichern

Session 08 — Menüstruktur + Capabilities ✓
  Ziel:    Dynamische Menüpunkte, Rollen greifen korrekt
  Dateien: src/Admin/MenuManager.php
  Tests:   Manuell: Als Redakteur einloggen — Templates/Fields unsichtbar

Session 09 — Frontend Query API ✓
  Ziel:    bs_fabric() im Theme verwendbar
  Dateien: src/Query/EntryQuery.php, src/Api/PublicApi.php
  Tests:   Unit: EntryQueryTest.php
           Manuell: bs_fabric('template')->get() im Theme ausgeben

Session 10 — Renderer + View Templates ✓
  Ziel:    ->render() und collection->render() funktionieren
  Dateien: src/Frontend/Renderer.php, templates/default/*
  Tests:   Manuell: card, list, detail im Theme ausgeben
  Status:  ✓ abgeschlossen

UX-Session A — Menü + Listen ✓
  Ziel:    Schema-Tabs, show_in_list, show_in_menu, Entry-Listen-Spalten
  Dateien: SchemaScreen.php, MenuManager.php, FieldSettings.php,
           TemplateSettings.php, EntryListScreen.php
  Status:  ✓ abgeschlossen

UX-Session B — ImageField + Polish ✓
  Ziel:    Medien-Upload pro Feld, Editor-Stabilität, UX-Polish
  Dateien: entry-admin.js, ImageField.php, FileField.php,
           EntryEditScreen.php, EntryListScreen.php
  Status:  ✓ abgeschlossen

Doku-Session — Entwickler-Doku ✓
  Ziel:    DocsScreen + template-spezifische Code-Snippets
  Dateien: DocsScreen.php, DocsExampleRenderer.php,
           TemplateCodeSnippets.php, TemplatesScreen.php
  Status:  ✓ abgeschlossen

DS-0 — Shell + Dashboard ✓
  Ziel:    Hybrid-Shell, Tokens, Dashboard als Startseite
  Dateien: AdminShell.php, DashboardScreen.php, bsf-tokens.css,
           bsf-components.css, bsf-wp-bridge.css, bsf-ui.js
  Status:  ✓ abgeschlossen

DS-1 — Fields + Templates ✓
  Ziel:    Schema-Bereich im neuen Design (Sheets, Builder, Karten)
  Dateien: FieldsScreen.php, TemplatesScreen.php, bsf-schema.css/js,
           FieldTypeCatalog.php, FormField.php, …
  Status:  ✓ abgeschlossen

DS-2 — Entry-Workflow ✓
  Ziel:    Listen, Editor, Doku, Explorer-Skin
  Dateien: EntryListScreen.php, EntryEditScreen.php, DocsScreen.php,
           bsf-entries.css, admin-menu.css
  Status:  ✓ abgeschlossen

DS-3 — Konfiguration ✓
  Ziel:    Plugin-Einstellungen, JSON-Export, Menü-Polish
  Dateien: PluginSettingsScreen.php, PluginSettings.php, SchemaExporter.php,
           SchemaScreen.php (Tab Konfiguration), LegacyDataCleanup.php
  Status:  ✓ abgeschlossen
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
- `Migrator`, `PluginSettings`: Versionsvergleich / Optionen
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

### Daten-Sicherheit & Plugin-Lebenszyklus

**Wichtig:** Fabric-Entries und Template-Listen liegen **ausschließlich** in den
sechs `bs_fabric_*`-Tabellen — nicht in WordPress-Beiträgen. Es gibt kein
automatisches „Mit-exportieren" über WP-Standardtools.

| Aktion | Einträge / Listen | Admin-UI | Theme `bs_fabric()` |
|---|---|---|---|
| **Deaktivieren** | Bleiben in der DB | Nicht erreichbar | **Funktioniert nicht** (Plugin-Code lädt nicht) → Theme kann Fatal Error werfen |
| **Reaktivieren** | Unverändert | Wieder da | Wieder aktiv |
| **Löschen**, Option *Daten beim Deinstallieren löschen* = **aus** | Tabellen **bleiben** (verwaiste Daten); Options `bs_fabric_db_version` + `bs_fabric_plugin_settings` werden entfernt | Weg | Weg — Re-Install sieht ggf. alte Daten wieder |
| **Löschen**, Option = **an** | Alle Tabellen **DROP** — Templates, Felder, **alle Einträge unwiderruflich weg** | Weg | Weg |
| Code-Update / `composer test` (Unit) | Keine Änderung | — | — |
| `composer test:all` (Test-DB) | Alle Zeilen gelöscht | — | — |

**Geplante Maßnahmen (Strang E):**
- **E-a** Redakteur-Hinweise im Dashboard (für `manage_entries`) und erweiterter Text in Konfiguration → Erweitert
- **E-b** Excel-Export je Template-Liste vor risikoreichen Aktionen (Backup-Workflow für Redakteure)
- Optional später: Explorer-Export, mysqldump-Hinweis für Admins

**Backup-Empfehlung (heute schon möglich):** JSON-Schema-Export (nur Struktur, keine Einträge) in Konfiguration → API & Export; für **Einträge** bis E-b: DB-Dump der sechs Tabellen (§15 Snapshot-Befehl) oder manuell.

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

## 16. Bekannte Issues & Production-Readiness

Stand: Juni 2026 — nach DS-0–DS-3. Vollständiges Audit: `CODE_ANALYSIS.md`.
Geschätzte Production-Readiness: **~65 %** (ohne Strang D).

Prioritäten: **P0** = Blocker · **P1** = bald · **P2** = Tech-Debt

### P0 — Blocker (Strang D)

| Issue | Beschreibung | Status |
|---|---|---|
| N+1 Entry-Hydration | `getByTemplate()` → O(N×M) Queries | ✓ D-1 |
| `bs_fabric()` in AJAX | `registerFrontend()` nur bei `!is_admin()` — API in `admin-ajax.php` undefiniert | ✓ D-2 |
| Keine Entry-Transaktionen | Halb-geschriebene Entries bei DB-Fehler in `saveFieldValues()` | ✓ D-3 |
| Integration-Tests falsche DB | Env-Vars-Pflicht; Fehlkonfiguration = Risiko | Mitigiert |

### P1 — wichtig

| Issue | Beschreibung | Strang |
|---|---|---|
| Side-Effects in Reads | `maybeBackfillStoredTitle()` in Lese-Methoden | D-4 |
| Explorer ohne Pagination | Alle Entries im JSON-Blob | D-5 |
| Fehlende DB-Indexes | Full-Table-Scans bei wachsenden Daten | D-6 |
| Kein Default-Statusfilter | `get()` liefert auch Entwürfe ohne `->status()` | B1 |
| Entry-Ownership fehlt | Jeder Redakteur darf alle Entries löschen | B1 |
| Uneinheitlicher Datenzugriff | `$entry->feld` vs. `toArray()` — formatted vs. raw | Doku ✓, API-Trennung offen |
| Prototyp-Lücken | Einzelansicht, Karten-Listenmodus | E |

### P2 — Tech-Debt

| Issue | Beschreibung |
|---|---|
| Capabilities bei Uninstall | Geister-Caps in Rollen nach Plugin-Löschung |
| Capabilities::register() | Läuft bei jedem Request ohne `has_cap`-Check |
| admin-menu.css global | Wird auf allen Admin-Seiten geladen |
| Third-Party XSS | Externe Feldtypen: kein Escaping-Vertrag im Interface |
| Field-Settings POST | Keine Tiefensanitierung in `collectSettingsFromPost()` |
| decodeValueFromStorage | Numerische Strings → int/float |
| Legacy admin.css | Schrittweise durch `bsf-*` ersetzen |
| API-Beispiele §8 | `$e->titelbild->url` — Image liefert Array, kein Objekt |
| InstallerTest | In Session-Übersicht erwähnt, Datei existiert nicht |
| Übersetzungen | `.pot` vorhanden, keine `de_DE.po` |

### Behobene Issues (Referenz)

- Globale `bs_fabric()` — Namespace-Fix (`src/Api/bs_fabric.php`)
- TemplateCodeSnippets — `sprintf`/`%`-Kollision
- EntryEdit — `wp_kses_post` strippte Formular-HTML
- MenuManager — Fatal im Schema-Doku-Tab
- Explorer-Titel — `resolveDisplayTitle()` + Resync
- Post-Meta/CPT — ausgeschlossen
- Legacy-Template Test-Leak — Migration 1.0.1
- Admin-Notices — zentralisiert in `SchemaNotices` (Toast)

---

## 17. `.cursorrules` — Abgleich mit Ist-Stand

Die Cursor Rules (`.cursorrules`) gelten weiterhin. Folgende **Ergänzungen**
klären Widersprüche zwischen Regeln und aktuellem Code — bei Konflikt
gilt dieses Dokument plus `.cursorrules` zusammen:

| Regel in `.cursorrules` | Ist-Stand / Anpassung |
|---|---|
| „Keine globalen Funktionen außer PublicApi.php" | **`bs_fabric()`** liegt in `src/Api/bs_fabric.php` — erlaubte zweite globale Funktion |
| „Kein React im MVP" | **Explorer** nutzt React/wp-components/DataViews — dokumentierte Ausnahme (§2, §4) |
| „AJAX via wp_ajax_, nicht REST im MVP" | REST ist **geplant (B2)**; bis dahin wp_ajax_ |
| „CSS-Klassen mit `bs-fabric-` Prefix" | **Admin Design System** nutzt `bsf-*` unter `.bsf-app`; Frontend/Templates weiter `bs-fabric-*` |
| „Kein Inline-CSS in PHP" | Minimale strukturelle `style=` in Screens vermeiden; bestehende Ausnahmen schrittweise entfernen |
| „Screens nur Rendering" | Screens enthalten Form-Handling (`handleCreate` etc.) — akzeptiert für WP-Admin-Pattern; Business-Logik bleibt in Repositories |
| Session-Disziplin „Ziel in ARCHITECTURE.md" | Roadmap §13 ist die Session-Quelle; Strang D/E/B vor Anfang festlegen |

**Kein Widerspruch** bei Storage (eigene Tabellen), Nonces, Capabilities, PSR-4, PHP 8.2.

---

*Stand: Juni 2026 — Design System DS-0–DS-3 ✓ · MVP Backend ✓*
*Plugin: bs-fabric | Namespace: BsFabric\ | API: bs_fabric()*
*Nächster Schritt: Strang E (Design-Nachzügler) oder Strang B1*
