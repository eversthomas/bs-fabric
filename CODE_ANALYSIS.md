# bs-fabric — Vollständige Code-Analyse

> **Stand:** August 2026 · Audit-Baseline für Production-Härtung (Strang D) + Audit-Delta (Claude Code Review + Cursor-Analyse)
>
> **Quelle der Wahrheit für Roadmap & Prioritäten:** [`ROADMAP.md`](ROADMAP.md) (Sofort-Fixes + Härtung Runde 2) und [`ARCHITECTURE.md`](ARCHITECTURE.md) §13 und §16.
> Dieses Dokument bleibt als detailliertes technisches Audit erhalten. Neue Befunde (Aug 2026) stehen in [Abschnitt 8](#8-audit-delta-august-2026-claude-code-review--cursor-analyse).
>
> **Deltas seit Erstellung der Analyse (Juni 2026):**
> - Admin-Notices → zentralisiert in `SchemaNotices` (A-4 teilweise behoben)
> - `uninstall.php` → Tabellen nur bei `PluginSettings::DELETE_ON_UNINSTALL` **und** Capabilities/Rolle `bs_fabric_mitarbeiter` werden entfernt (S-6 **vollständig behoben**, verifiziert Aug 2026 — ARCHITECTURE.md §11 widersprach sich dazu und wurde korrigiert)
> - `is_admin() || wp_doing_ajax()`-Fix umgesetzt (S-2 behoben, verifiziert Aug 2026)
> - Design System DS-0–DS-3 abgeschlossen
> - JSON-Schema-Export implementiert (`SchemaExporter`)
> - Globale `bs_fabric()` Namespace-Fix (bereits vor Audit behoben)
> - **Strang D (Audit A–F):** N+1, Transaktionen, Explorer-Pagination, Indizes, Tests — siehe [`ROADMAP.md`](ROADMAP.md); C-6 (Transaktionen), P-1/P-2 (N+1/Explorer), P-5 (Indizes), S-5 (`sanitizeSettings()`) damit **behoben**, verifiziert Aug 2026
> - Feldtypen `boolean`, `number`, `address` (A1); Default-Statusfilter `published`
> - **CSV-Formula-Injection im Export behoben** (Aug 2026, siehe Abschnitt 8)
> - **Test-Suite lief seit unbekannter Zeit nur zu ~74 % durch und meldete fälschlich Erfolg** — behoben (Aug 2026, siehe Abschnitt 8)
>
> Offene P0-Blocker aus dem Juni-Audit sind erledigt; verbleibende Altpunkte (z. B. S-3 Ownership — **bewusst nicht geplant**, siehe ROADMAP.md „Bewusst nicht geplant“) und alle neuen Befunde → [Abschnitt 8](#8-audit-delta-august-2026-claude-code-review--cursor-analyse) und [`ROADMAP.md`](ROADMAP.md).

---

## 1. SICHERHEIT

### Kritisch

**[S-1] XSS-Risiko bei Third-Party-Feldtypen — FieldsScreen.php:276, EntryEditScreen.php:225**

```php
// FieldsScreen.php:276 — ungefilterte Ausgabe:
echo '<td>' . $fieldType->renderSettings($field) . '</td></tr>';

// EntryEditScreen.php:482 — kommentiert als bewusst:
echo $input; // phpcs:ignore ...EscapeOutput.OutputNotEscaped
```

Die internen Feldtypen escapen korrekt. Aber der `bs_fabric_field_types`-Filter erlaubt externe Feldtypen — ein externer Entwickler der `renderSettings()` oder `renderInput()` unsauber implementiert, öffnet XSS direkt im Admin. Es fehlt ein Vertrags-Docblock oder eine Sanitizer-Pflicht in `FieldTypeInterface`.

**Lösung:** `FieldTypeInterface` mit `@return-pure-html` Docblock und Hinweis ergänzen. Alternativ: `wp_kses()` mit einem erlaubten HTML-Set als Wrapper, oder eine explizite Regel im Interface-Docblock die Pflicht zur Ausgabe-Escaping festschreibt.

**[S-2] ✓ BEHOBEN (Aug 2026) — bs_fabric() nicht in Admin-AJAX-Kontext verfügbar — Plugin.php:207-213**

```php
if (! is_admin()) {
    add_action('plugins_loaded', [$this, 'registerFrontend'], 20);
}
```

`is_admin()` gibt `true` für `admin-ajax.php` zurück. Die globale `bs_fabric()`-Funktion steht in AJAX-Handlern, Widget-Callbacks und Cron-Jobs (wenn diese aus dem Admin-Kontext laufen) nicht zur Verfügung. Das ist ein stiller Fehler: die API wirft keinen Fehler, sondern ist schlicht undefiniert.

**Lösung:**

```php
if (! is_admin() || wp_doing_ajax()) {
    add_action('plugins_loaded', [$this, 'registerFrontend'], 20);
}
```

**[S-3] Keine Ownership-Prüfung beim Entry-Löschen — EntryAjax.php:27-43, EntryEditScreen.php:128-160**

Jeder Nutzer mit `bs_fabric_manage_entries` kann per Entry-ID jeden Entry löschen oder überschreiben — unabhängig von `created_by`. Im Architektur-Dokument ist dies mit "Phase 2: Granulare Capabilities pro Template" vermerkt, aber der Mangel sollte explizit dokumentiert sein, da er im MVP aktiv ist.

**Lösung kurzfristig:** Zumindest dokumentieren. Langfristig: `created_by`-Check falls Nutzer kein `manage_schema` hat.

**[S-4] RuntimeException in handleCreate()/handleUpdate() führt zu undefinierter Variable — EntryEditScreen.php:108-126, TemplatesScreen.php:87-103**

```php
try {
    $entryId = $this->entryRepository->create([...]); // wirft ggf. RuntimeException
} catch (TemplateNotFoundException) { wp_die(...); }
  catch (InvalidArgumentException) { $this->redirectWithNotice(...); } // exit

$this->redirectWithNotice('success', ..., $entryId); // $entryId potenziell uninitialisiert
```

Wenn `create()` eine `RuntimeException` wirft (DB-Fehler), propagiert diese ungefangen nach oben und produziert einen weißen Bildschirm statt einer nutzerfreundlichen Fehlermeldung.

**Lösung:**

```php
} catch (\RuntimeException $e) {
    $this->redirectWithNotice('error', $e->getMessage(), $templateId, 0, true);
}
```

**[S-5] ✓ BEHOBEN (Strang D) — FieldsScreen::collectSettingsFromPost() — keine Tiefensanitierung — FieldsScreen.php:289-313**

```php
$rawSettings = wp_unslash($_POST['bs_fabric_field_settings']); // nur unslash, kein sanitize
...
return $this->mergeGlobalSettingsFromPost(array_merge($defaults, $rawSettings[$fieldName]));
```

Die Settings-Werte werden direkt aus POST übernommen, durch `array_merge()` mit Defaults gemischt und als JSON gespeichert. Für String-Werte in Settings (z. B. der template-Selector in `RelationField`) fehlt eine `sanitize_key()`/`sanitize_text_field()`-Prüfung vor dem Speichern.

**Lösung:** Jeder Feldtyp sollte `sanitizeSettings(array $raw): array` im Interface implementieren.

### Wichtig

**[S-6] ✓ VOLLSTÄNDIG BEHOBEN (verifiziert Aug 2026) — Capabilities beim Uninstall nicht entfernt — uninstall.php**

`uninstall.php` entfernt Tabellen und `bs_fabric_db_version`, aber nicht die `bs_fabric_manage_schema`/`bs_fabric_manage_entries` Capabilities aus den Benutzerrollen. Nach Plugin-Löschung bleiben Geister-Capabilities in der `wp_usermeta`-Tabelle.

**Lösung:** Am Ende von `uninstall.php`:

```php
foreach (['administrator', 'editor'] as $roleName) {
    $role = get_role($roleName);
    if ($role) {
        $role->remove_cap('bs_fabric_manage_schema');
        $role->remove_cap('bs_fabric_manage_entries');
    }
}
```

**[S-7] Capabilities::register() bei jedem Request — Plugin.php:240-241**

```php
public function onPluginsLoaded(): void
{
    Capabilities::register(); // läuft bei JEDEM Request
}
```

`add_cap()` schreibt in die DB (`wp_user_roles` Option) und ist zwar idempotent, aber kostet einen DB-Read pro Request. Das Muster sollte nur bei Aktivierung/Migration laufen.

**Lösung:** In `Capabilities::register()` prüfen ob die Cap bereits existiert:

```php
if ($administrator && ! $administrator->has_cap(self::MANAGE_SCHEMA)) {
    $administrator->add_cap(self::MANAGE_SCHEMA);
}
```

**[S-8] getTemplateId() in TemplateAjax — potenziell irreführendes Return-after-Exit — TemplateAjax.php:163-170**

```php
private function getTemplateId(): int
{
    ...
    if ($templateId <= 0) {
        wp_send_json_error(..., 400); // ruft wp_die() → exit auf
    }
    return $templateId; // statisch unerreichbar nach wp_send_json_error
}
```

Funktioniert in der Praxis, aber statische Analyse-Tools (PHPStan, Psalm) flaggen dies als Kontrollflussproblem. Besser: `never` Return-Type für eine private `sendErrorAndDie()` Methode.

---

## 2. WORDPRESS BEST PRACTICES (WP 7.0)

### Kritisch

**[WP-1] Assets werden auf ALLEN Admin-Seiten geladen — MenuManager.php:43-50**

```php
public function enqueueAdminMenuAssets(): void
{
    wp_enqueue_style(
        'bs-fabric-admin-menu',
        BS_FABRIC_URL . 'assets/css/admin-menu.css',
        [], BS_FABRIC_VERSION
    ); // Kein Check ob wir auf einer Fabric-Seite sind!
}
```

`admin-menu.css` wird auf jeder Admin-Seite geladen (kein `isFabricPage()`-Guard). Das ist ein Performance- und Namespace-Problem.

**Lösung:**

```php
public function enqueueAdminMenuAssets(string $hookSuffix): void
{
    if (! $this->isFabricPage($hookSuffix)) {
        return;
    }
    ...
}
```

### Wichtig

**[WP-2] plugin.php::registerAdmin() auf init, aber SchemaScreen/EntryListScreen registrieren sich selbst über admin_init — Plugin.php:222-230**

`registerAdmin()` registriert `MenuManager`, `FieldsScreen`, `TemplatesScreen`, `EntryEditScreen` und die AJAX-Handler. `MenuManager::register()` hookt sich dann in `admin_menu` und `admin_enqueue_scripts` ein. Das ist korrekt. Aber `FieldsScreen::register()` und `TemplatesScreen::register()` hookt auf `admin_init`. Da `registerAdmin()` auf `init` läuft, werden diese Hooks korrekt eingeklinkt. Okay — aber verwirrend.

**[WP-3] DataViews korrekt genutzt? — ExplorerScreen.php**

Der Explorer nutzt einen React-Build der die DataViews API wraps. Die `enqueueAssets()`-Methode (Z. 34-71) lädt korrekt mit `.asset.php`-Manifest. Verbesserungspotenzial: Der `bsFabricExplorer`-Config übergibt alle Entries auf einmal (kein AJAX-Pagination) — bei wachsenden Datenmengen wird dies ein Problem (siehe Performance-Abschnitt).

**[WP-4] is_readable() Check für Composer — bs-fabric.php:30**

```php
if (! is_readable($autoload)) {
```

Korrekt. Die Admin-Notice prüft auch korrekt auf `activate_plugins`. ✓

**[WP-5] Textdomain laden — Plugin.php:233-237**

```php
load_plugin_textdomain('bs-fabric', false, ...);
```

Korrekt, auf `plugins_loaded`. ✓ Aber: Die meisten UI-Strings sind Deutsch — das Plugin hat eine `bs-fabric.pot`-Datei, aber keine fertigen `.po`/`.mo`-Dateien. Für eine Production-Release fehlt zumindest eine `de_DE.po`.

---

## 3. ARCHITEKTUR & MODULARITÄT

### Kritisch

**[A-1] Side Effects in Read-Methoden — EntryRepository.php:341-355, EntryRepository.php:630-640**

`getExplorerRows()` und `hydrateEntry()` rufen beide `maybeBackfillStoredTitle()` auf, welches unter Umständen ein UPDATE auf die Entries-Tabelle ausführt:

```php
// In getExplorerRows() — READ-Methode:
$this->maybeBackfillStoredTitle($entryId, $storedTitle, $title); // kann UPDATE triggern!

// In hydrateEntry() — READ-Methode:
$this->maybeBackfillStoredTitle($entryId, $storedTitle, $resolvedTitle); // auch hier!
```

Das verletzt das CQS-Prinzip (Command-Query-Separation) fundamental. Ein Lese-Aufruf triggert N unsichtbare Writes. Das ist besonders problematisch in:

- Read-Only-Datenbankverbindungen
- Test-Szenarien mit Read-Only-Expectations
- Caching-Schichten die Reads cachen aber Writes invalidieren müssen

**Lösung:** `maybeBackfillStoredTitle()` aus Read-Methoden entfernen. Stattdessen: `syncTitle()` explizit in `create()`/`update()` aufrufen (es läuft bereits dort), und für die Explorer-Ansicht einen separaten `syncAllTitlesForTemplate()`-Befehl anbieten.

**[A-2] getTemplateFieldsByName() hat versteckten N+1 — EntryRepository.php:720-741**

```php
private function getTemplateFieldsByName(int $templateId): array
{
    foreach ($this->templateRepository->getFields($templateId) as $assignment) {
        $field = $this->fieldRepository->getById($assignment['field_id']); // N Queries!
```

`getFields()` macht bereits einen JOIN mit der Fields-Tabelle und gibt `name`, `label`, `type` zurück — aber die Settings fehlen im Result. Deshalb wird `fieldRepository->getById()` für jedes Feld nochmal aufgerufen. Das ist ein Design-Mismatch zwischen `TemplateRepository::getFields()` (gibt kein `settings` zurück) und `EntryRepository` (braucht `settings`).

**Lösung:** `TemplateRepository::getFields()` um `settings` erweitern:

```php
// Im SQL-Query:
SELECT tf.id AS assignment_id, tf.field_id, tf.sort_order, tf.is_required,
       f.name, f.label, f.type, f.settings  // settings hinzufügen
```

### Wichtig

**[A-3] TemplateRepository::getFields() macht immer 2 Queries — TemplateRepository.php:319-360**

```php
public function getFields(int $templateId): array
{
    // Query 1:
    if ($this->getById($templateId) === null) { // SELECT id, name, ...
        throw new TemplateNotFoundException(...);
    }
    // Query 2: SELECT tf.id, ... JOIN
```

`getById()` lädt das gesamte Template-Objekt nur um zu prüfen ob es existiert. `COUNT(*)`-Query wäre ausreichend — oder besser: den Existence-Check entfernen und die Exception auf einen leeren Resultset reagieren lassen (DB-FK tut dies bereits implizit).

**[A-4] Duplicate renderAdminNotices() — FieldsScreen.php:378-394, TemplatesScreen.php:464-480, EntryEditScreen.php:537-552**

Exakt dieselbe Implementierung in drei Klassen kopiert. Ein `AdminNoticesTrait` oder eine `AdminNoticeHelper`-Klasse würde dies auflösen.

**[A-5] Plugin::registerHooks() — Admin-Services werden auf init statt admin_init registriert — Plugin.php:211-214**

```php
if (is_admin()) {
    add_action('init', [$this, 'registerAdmin']);
}
```

`init` läuft auch für öffentliche Requests wenn `is_admin()` zufällig zutrifft (z. B. cron). `admin_init` wäre präziser und semantisch korrekt.

**[A-6] EntryAjax::findTemplateByName() iteriert alle Templates — EntryAjax.php:78-87**

```php
private function findTemplateByName(string $name): ?Template
{
    foreach ($this->templateRepository->getAll() as $template) {
        if ($template->name === $name) {
```

`TemplateRepository::getByName()` existiert bereits — aber wird hier nicht genutzt. Redundante Iteration über alle Templates.

**Lösung:** `return $this->templateRepository->getByName($name);`

---

## 4. PERFORMANCE

### Kritisch

**[P-1] ✓ BEHOBEN (Strang D, verifiziert Aug 2026) — O(N×M) Queries beim Hydratisieren von Entry-Collections — EntryRepository.php:657-665**

```
getByTemplate(N entries)
  → hydrateCollection()
    → hydrateEntry() × N
      → getTemplateFieldsByName()
        → getFields() [2 queries]
        → getById() × M [M = field count]
      → loadFieldValues()
        → loadStoredFieldValue() × M [M queries]
```

Bei 50 Entries mit je 10 Feldern: 50 × (2 + 10 + 10) = 1.100 Queries. Das ist MVP-kritisch.

**Lösung:**

1. Batch-Load aller Entry-Values für eine Collection in einem Query:

```sql
SELECT entry_id, field_id, value FROM wp_bs_fabric_entry_values 
WHERE entry_id IN (1, 2, 3, ...)
```

2. `getTemplateFieldsByName()` gecacht (gleiche `template_id` = gleiche Felder):

```php
/** @var array<int, array<string, mixed>> */
private array $templateFieldsCache = [];

private function getTemplateFieldsByName(int $templateId): array
{
    return $this->templateFieldsCache[$templateId] 
        ??= $this->loadTemplateFieldsByName($templateId);
}
```

**[P-2] ✓ BEHOBEN (Strang D, verifiziert Aug 2026) — Explorer lädt alle Entries in PHP-Memory und übergibt alles ans Frontend — ExplorerScreen.php:95-120**

> Hinweis Aug 2026: Die Explorer-Ansicht selbst ist behoben (`queryExplorerRows()`, DB-seitige Pagination). Die **Entry-Liste je Template** (`EntryListScreen`) hat aber denselben Fehler weiterhin — siehe Abschnitt 8, „Entry-Liste je Template ohne DB-Pagination“.

```php
foreach ($this->entryRepository->getExplorerRows() as $row) {
    $entries[] = [...]; // ALLE Entries
}
// wp_localize_script(..., ['entries' => $entries]) → riesiges JSON
```

Bei 1.000+ Entries: massiver Memory-Verbrauch, langes initiales Laden, großes `<script>`-JSON-Blob. DataViews unterstützt serverseitige Paginierung via AJAX.

**Lösung:** REST-API-Endpoint für paginiertes Laden der Explorer-Daten, DataViews auf AJAX-Mode umstellen.

**[P-3] updateFieldOrder() macht N einzelne UPDATE-Queries — TemplateRepository.php:373-395**

```php
foreach (array_values($fieldIds) as $index => $fieldId) {
    $wpdb->query($wpdb->prepare("UPDATE ... SET sort_order = %d ...", $index, ...));
}
```

Bei 20 Feldern: 20 separate UPDATE-Statements in einer Schleife ohne Transaktion.

**Lösung:** `$wpdb->query("START TRANSACTION")` + Batch-Update + COMMIT, oder ein CASE-WHEN-Statement.

**[P-4] maybeBackfillStoredTitle() triggert N UPDATE-Queries in der Explorer-Liste — EntryRepository.php:577-588, getExplorerRows():341**

Jeder Entry dessen `title`-Spalte leer ist, triggert einen UPDATE bei jedem Aufruf von `getExplorerRows()`. Bei 100 neuen Entries ohne Titel: 100 UPDATEs bei jedem Explorer-Page-Load.

### Wichtig

**[P-5] ✓ BEHOBEN (Migration 1.0.2, verifiziert Aug 2026) — Fehlende Datenbankindexes**

Kein Index auf `bs_fabric_entries.status` (häufig gefilterter WHERE-Clause). Kein Index auf `bs_fabric_entries.created_at` (ORDER BY). Der `getExplorerRows()`-Query ohne LIMIT über `created_at DESC` ist ein Full-Table-Scan.

Ergänzung in `Installer.php`:

```sql
CREATE INDEX idx_entries_template_status ON {prefix}bs_fabric_entries (template_id, status);
CREATE INDEX idx_entries_created_at ON {prefix}bs_fabric_entries (created_at);
CREATE INDEX idx_relations_from_field ON {prefix}bs_fabric_relations (from_entry_id, field_id);
```

**[P-6] TemplatesScreen::renderList() — N+1 auf Template-Liste — TemplatesScreen.php:180**

```php
foreach ($templates as $template) {
    $fieldCount = count($this->templateRepository->getFields($template->getId())); // Query pro Template!
```

Bei 10 Templates: 10 zusätzliche Queries (+ die 2 internen pro `getFields()`-Aufruf = 20 Queries).

**Lösung:** Einen `getFieldCounts(): array<int, int>` Methode mit GROUP BY-Query.

---

## 5. CODE-QUALITÄT & MODERNITÄT

### Wichtig

**[C-1] decodeValueFromStorage() — ambige Typ-Koersion — EntryRepository.php:1035-1051**

```php
if (is_numeric($value)) {
    return str_contains($value, '.') ? (float) $value : (int) $value;
}
return $value; // string
```

Strings die zufällig numerisch sind ("42", "3.14") werden zu int/float konvertiert. Das bedeutet: ein Text-Feld mit Inhalt "42" liefert `int(42)` zurück. Das kann in Templates zu Typ-Fehlern führen (`$e->telefon` ist plötzlich ein int).

**Lösung:** Type-Information mitspeichern oder konsequent als String zurückgeben und nur für numerische Feldtypen konvertieren.

**[C-2] FieldTypeInterface::renderValue() — Return-Typ mixed schränkt statische Analyse ein — FieldTypeInterface.php**

```php
public function renderValue(mixed $value): mixed;
```

`ImageField::renderValue()` gibt `array|null` zurück, `TextField::renderValue()` gibt `string|null`. Ein einheitlicherer Ansatz mit `FieldValue` Value-Object oder zumindest engere Union-Types würden statische Analyse verbessern.

**[C-3] FieldsScreen::collectSettingsFromPost() — Komplexität — FieldsScreen.php:289-313**

Die Methode hat 4 Rückgabepfade und 3 verschachtelte if-Blöcke. Die Logik ist schwer zu folgen. Refaktor auf ein klares early return-Muster würde dies verbessern.

**[C-4] ServiceContainer — keine Typisierung — Core/ServiceContainer.php**

Nicht gesehen im Detail, aber die `get()`-Methode gibt wahrscheinlich `mixed` zurück. Die Aufrufe in `Plugin.php` wie `$this->container->get(FieldRegistry::SERVICE_ID)` brauchen manuelle Type-Casts oder `/** @var FieldRegistry */`-Annotationen.

**[C-5] PHP 8.2 Features — weitgehend korrekt genutzt**

Positiv: `readonly` Properties, `match`, `declare(strict_types=1)` überall, nullsafe operator, first-class callables. PSR-4 durchgängig.

Verbesserungspotenzial: `enum` für Status (`'draft'|'published'|'archived'`) statt `ALLOWED_STATUSES`-Konstante würde Typsicherheit erhöhen:

```php
enum EntryStatus: string {
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
```

**[C-6] ✓ BEHOBEN (Strang D, verifiziert Aug 2026) — Keine Transaktion bei Entry-Erstellung — EntryRepository.php:67-113**

```php
$wpdb->query($wpdb->prepare("INSERT INTO ... entries ..."));
// Kein START TRANSACTION
$this->saveFieldValues($entryId, $templateId, $fields); // mehrere weitere Queries
$this->syncTitle($entryId, $templateId);
```

Wenn `saveFieldValues()` fehlschlägt, bleibt ein halb-geschriebener Entry ohne Felder in der DB.

**Lösung:**

```php
$wpdb->query('START TRANSACTION');
try {
    // alle Writes
    $wpdb->query('COMMIT');
} catch (\Throwable $e) {
    $wpdb->query('ROLLBACK');
    throw $e;
}
```

---

## 6. VORBEREITUNG PHASE 2-4

### Bidirektionale Relationen

**Bottleneck:** `RelationRepository` speichert nur einseitige Relationen (`from_entry_id` → `to_entry_id`). Für bidirektionale Relationen müsste entweder:

- Ein `direction`-Feld in `bs_fabric_relations` hinzukommen, oder
- Beide Richtungen als separate Rows gespeichert werden (mit entsprechender Logik beim Löschen)

Die Tabellenstruktur ist erweiterbar, die Repository-API aber nicht — `RelationRepository::set()` überschreibt alle Relations für ein Entry/Field-Paar, ohne bidirektionale Konsistenz zu garantieren.

### Erweiterte Query Engine (whereIn, whereBetween, OR)

**Bottleneck:** `buildFrontendQuerySql()` (Z. 868-926) verwendet INNER JOINs pro WHERE-Klausel. Das Pattern funktioniert für AND-Bedingungen, ist aber nicht erweiterbar für OR ohne fundamentale Umstrukturierung.

Kritisches Problem bei custom `order_by`:

```php
$joins[] = "LEFT JOIN {$valuesTable} ev_order ON ev_order.entry_id = e.id";
$orderSql = "ev_order.value {$direction}";
```

Mit `SELECT DISTINCT e.id ... ORDER BY ev_order.value` — MySQL im Standard-Mode wirft hier einen Error (non-aggregated column in ORDER BY mit DISTINCT). Müsste GROUP BY oder Subquery werden.

### Granulare Capabilities pro Template

Gut vorbereitet: `Capabilities::register()` ist isoliert, `MenuManager` nutzt `Capabilities::MANAGE_ENTRIES`. Ein `bs_fabric_manage_entries_{template_name}` Pattern wäre erweiterbar ohne große Refactors.

### WP AI Client (WP 7.0)

Keine Blockaden. Die Plugin-Architektur (ServiceContainer, Hooks) würde einen neuen `AiService` sauber aufnehmen.

### REST API Layer

**Stand:** Phase 1 (read-only) implementiert unter `src/Rest/`.

| Komponente | Status |
|---|---|
| `RestBootstrap` + `EntriesController` | ✓ Collection, Single, Count |
| `RestQueryParser` → `EntryQuery` | ✓ `where`, `orderby`, `per_page`, `status`, `with` |
| `EntrySerializer` + `bs_fabric_rest_entry` | ✓ `renderValue()`-Semantik, Relation-Embed 1 Ebene |
| `RestPermissionChecker` | ✓ `published` öffentlich; sonst `manage_entries` |
| Unit-/Integration-Tests | ✓ `RestQueryParserTest`, `EntrySerializerTest`, `EntriesControllerTest` |

**Offen (Phase 2):** `page`/offset-Pagination, `TemplatesController`, CORS.  
**Offen (Phase 4):** Write-Endpoints (POST/PATCH/DELETE), Schema-CRUD.

Repositories (`EntryRepository`, `TemplateRepository`, `FieldRepository`) bleiben
die Datenquelle — kein paralleler Query-Pfad nötig.

Der frühere Hinweis „kein WP_REST_Controller-Grundgerüst" ist obsolet;
`EntriesController extends WP_REST_Controller` ist vorhanden.

### Repeater-Feldtyp

**Bottleneck:** Das Schema hat keine `parent_entry_id` oder `sort_order` in `entry_values`. Repeater würde entweder:

- Ein neues `bs_fabric_repeater_values`-Schema erfordern, oder
- JSON-Encoding in `value` (LONGTEXT) des `entry_values`-Eintrags — einfach aber schlechte Querybarkeit

### Mehrsprachigkeit

Gut vorbereitet: `language`-Spalte in `bs_fabric_entries` existiert bereits. `EntryQuery` hat noch keine `language()`-Methode — 10-Minuten-Aufgabe.

### Tiptap als Richtext

Kein Blocker. `RichtextField` würde einfach sein JS austauschen.

### Headless API / code-Feldtyp als REDAXO-Module

Kein technischer Blocker, aber der `code`-Feldtyp braucht einen Execution-Context — das ist eine bewusste Phase-3-Entscheidung.

---

## 7. TESTS

### Kritisch

**[T-1] Keine Tests für AJAX-Handler**

`EntryAjax`, `FieldAjax`, `TemplateAjax` sind ohne Tests. Das sind die sicherheitskritischsten Pfade (Capability-Check, Nonce-Verification).

**[T-2] EntryRepository::findByFrontendQuery() — kein Test mit WHERE-Klauseln**

Die komplexe `buildFrontendQuerySql()`-Methode ist unkritisch schwer zu testen ohne Integration-DB. Zumindest der SQL-String-Builder sollte unit-testbar gemacht werden (Methode sichtbarer machen).

### Wichtig

**[T-3] Integration Tests löschen Daten — kein DB-Isolation via Rollback**

Laut ARCHITECTURE.md leeren Integration Tests alle Rows per `setUp`. Das ist sicher durch die Env-Variable-Guards, aber transaktionsbasiertes Rollback wäre cleaner und schneller.

**[T-4] Fehlende Unit-Tests:**

- `EntryRepository::decodeValueFromStorage()` — der ambige Typ-Koersions-Fall (Problem C-1)
- `EntryRepository::buildFrontendQuerySql()` — kritische Business-Logik
- `TemplateRepository::updateFieldOrder()` — N-Query-Loop
- Alle FieldType `sanitizeValue()` Methoden (außer Text/Relation laut ARCHITECTURE.md)

---

## 8. AUDIT-DELTA AUGUST 2026 (Claude Code Review + Cursor-Analyse)

> Quelle: Zwei unabhängige Analysen (Claude Code, Zeilen-Level-Review von 4 Subagenten; Cursor, Produkt-/Doku-Ebene). Ergebnisse abgeglichen, Widersprüche geprüft. Vollständige Einzelbefunde im Chat-Verlauf der jeweiligen Session; hier nur die konsolidierte, für die Roadmap relevante Fassung. Umsetzungsstatus siehe [`ROADMAP.md`](ROADMAP.md) → „Härtung Runde 2“.

### Sofort behoben (Aug 2026)

**[AD-1] ✓ BEHOBEN — CSV-Formula-Injection im Export — EntryListExporter.php:64 (`escapeCell()`)**

Zellwerte, die mit `=`, `+`, `-`, `@`, Tab oder CR beginnen, wurden ungeprüft in die CSV-Datei geschrieben. Ein Nutzer mit nur `bs_fabric_manage_entries` (Rolle „Fabric Mitarbeiter“) konnte einen Feldwert wie `=HYPERLINK(...)` speichern; öffnet ein Admin den Export in Excel/LibreOffice, wird die Formel ausgeführt (Datenexfiltration möglich). Fix nach OWASP-Empfehlung: gefährliche Startzeichen werden mit einem führenden `'` entschärft, bevor die bestehende Quoting-Logik greift. Regressionstest ergänzt (`EntryListExporterTest::testToCsvPrefixesFormulaLikeValuesToPreventCsvInjection`).

**[AD-2] ✓ BEHOBEN — Test-Suite brach lautlos ab und meldete falschen Erfolg — tests/bootstrap.php**

`templates/default/*.php` beginnen mit `defined('ABSPATH') || exit;`. In der Testumgebung war `ABSPATH` nie definiert — der erste Test, der `Renderer::render()` aufruft (`RendererTest::testRenderOutputsCardTemplateHtml`), beendete den kompletten PHPUnit-Prozess sofort per `exit()`, mit Exit-Code 0. Effekt: `composer test:unit` lief nur zu ~74 % durch (96 von 130 Tests) und sah für ein CI-Skript wie ein sauberer Lauf aus — alle Tests alphabetisch nach `Frontend/RendererTest` (Geo, Query, Rest, Templates) wurden nie geprüft. Fix: `ABSPATH`-Konstante in `tests/bootstrap.php` definiert. Die Suite läuft jetzt vollständig (130/130 Tests werden ausgeführt).

**Aktueller, jetzt verlässlicher Ist-Zustand der Unit-Suite (Aug 2026):** 130 Tests, 293 Assertions, **8 Errors**, **1 Failure**, 4 Risky. Das ist kein neuer Rückschritt — diese Fehler existierten vorher bereits, waren aber unsichtbar, weil die Suite sie nie erreichte.

### Offen — kein Blocker, aber jetzt sichtbar (Priorisierung siehe ROADMAP „Härtung Runde 2“)

**[AD-3] `WP_REST_Request`-Klasse fehlt in der Testumgebung — RestQueryParserTest (8 Errors)**

Kein WP-Core-Stub-Paket (z. B. `php-stubs/wordpress-stubs`) in `composer.json` `require-dev`. Betroffene Tests sind vermutlich noch nie erfolgreich gelaufen.

**[AD-4] Echter Bug: Relation-Embed liefert leeres Array statt IDs — EntrySerializerTest::testRelationWithEmbedSerializesAsNestedObjects (1 Failure)**

Erwartet `[15]`, erhält `[]`. Betrifft die REST-Auslieferung von `with`-eingebetteten Relationen — relevant für Headless-Nutzung.

**[AD-5] Migrator ohne Fehlerbehandlung — Database/Migrator.php:37-113**

Kein DDL-Statement wird auf Erfolg geprüft; `bs_fabric_db_version` wird trotzdem hochgesetzt, auch wenn eine `ALTER TABLE`/`ADD CONSTRAINT` scheitert (z. B. fehlende DB-Rechte auf Shared Hosting). Ein fehlgeschlagener Schritt läuft danach **nie wieder** und bleibt unsichtbar. Zusätzlich laufen Migrationen bei jedem `plugins_loaded`-Request, nicht nur im Admin-Kontext.

**[AD-6] Foreign-Key-Erstellung ungeprüft, kein explizites `ENGINE=InnoDB` — Database/Installer.php:198-282**

`EntryRepository::delete()` verlässt sich vollständig auf `ON DELETE CASCADE`. Schlägt `ensureForeignKeys()` unbemerkt fehl, bleiben beim Löschen von Entries dauerhaft verwaiste Zeilen in `entry_values`/`relations` zurück.

**[AD-7] Entry-Liste je Template ohne DB-seitige Pagination — EntryRepository::getByTemplate(), EntryListScreen.php**

Im Gegensatz zum (bereits gehärteten) Explorer lädt die normale Entry-Listenansicht pro Template weiterhin den kompletten Datensatz in den PHP-Speicher, bevor in PHP gefiltert/sortiert wird. Skaliert nicht mit wachsender Entry-Zahl.

**[AD-8] `resyncTitlesForTemplate()` — synchrones N+1 auf Admin-POST — EntryRepository.php:843-868**

Bei jeder Änderung des Titel-Feldes eines Templates: eine SELECT+UPDATE-Query pro Entry, ohne Batching. Timeout-Risiko bei großen Templates.

**[AD-9] Fehlende Tests für sicherheits-/integritätskritische Klassen**

`RestPermissionChecker` (die REST-Berechtigungsprüfung selbst), `Database/Installer.php` (Schema-Erstellung), `RelationRepository` — alle drei ohne jeden Unit- oder Integrationstest.

**[AD-10] `EntryRepository` als God-Class (Debt, kein akuter Bug) — 1773 Zeilen, ~50 Methoden**

Kombiniert CRUD, Transaktionen, Titel-Resolution, Query-Building, Caching. Größter Refactoring-Kandidat, wenn die Klasse weiterwächst.

**[AD-11] Feldtyp-Boilerplate dupliziert (Debt) — 13 von 15 Feldtyp-Klassen**

`mergeSettings()`/`fieldName()`/`settingsName()` sind byte-identisch in 13 Klassen kopiert statt in `SanitizesFieldSettings` zusammengeführt. Ändert sich die `bs_fabric_fields[...]`-Namenskonvention, ist eine vergessene Kopie ein latenter Bug.

**[AD-12] Öffentliche REST-Daten ohne Auth — Produktentscheidung, kein Bug**

`published`-Entries sind laut Design ohne Authentifizierung über REST lesbar (§3 REST-API, korrekt implementiert). Werden in einem Template personenbezogene Daten gepflegt (z. B. `address` mit Geokoordinaten) und auf `published` gesetzt, sind diese automatisch öffentlich abrufbar — unabhängig davon, ob ein Theme die REST-API überhaupt nutzt. Vor Produktivbetrieb mit Personendaten zu entscheiden: pauschal so lassen, oder ein Template-Flag „REST-sichtbar“ einführen (siehe ROADMAP → REST-API Phase 2).

**[AD-13] Keine echten Permalinks/SEO-Meta — Produktlücke, kein Bug**

`Renderer::getEntryLink()` defaultet auf `#bs-fabric-entry-{id}` (Fragment-Anker); nur über den Filter `bs_fabric_entry_url` auf echte URLs umstellbar. Kein Sitemap-Feed, kein JSON-LD-Generator — beides ist bewusst Theme-Aufgabe, sollte aber vor Go-Live mit öffentlich sichtbaren Entries eingeplant werden.

**[AD-14] ARCHITECTURE.md §11 widersprach sich selbst zum Uninstall-Verhalten — behoben**

Zeile 653 behauptete „Capabilities werden nicht entfernt“, während der Datei-Tree-Kommentar in §16 (Session R2) korrekt „Cap + Rolle entfernen“ auswies. Der tatsächliche Code (`uninstall.php`) entfernt beides korrekt — Zeile 653 wurde entsprechend korrigiert.

---

## Gesamteinschätzung: Produktionsreife

**Produktionsreife: ~80 % — für einen begrenzten Redaktionsbetrieb (wenige gleichzeitige Bearbeiter, moderates Datenvolumen) nach den Aug-2026-Sofortfixes einsatzbereit; für Skalierung/Headless-Write noch nicht.**

Das Plugin hat eine hervorragende Grundarchitektur: saubere PSR-4-Struktur, ServiceContainer, konsistentes Exception-Handling, `declare(strict_types=1)` überall, gute Nonce/Capability-Abdeckung in AJAX-Handlern, solider und (seit Aug 2026) tatsächlich vollständig laufender Test-Grundstock. Die drei P0-Blocker aus dem Juni-Audit (N+1-Queries, `bs_fabric()` im AJAX-Kontext, fehlende Transaktionen) sind behoben und verifiziert.

### Verbleibende Punkte vor Produktivbetrieb mit wachsendem Datenvolumen/mehreren Bearbeitern:

| Priorität | Problem | Aufwand | Ref. |
|---|---|---|---|
| P1 | `WP_REST_Request`-Stub einbinden (Tests aktuell nicht lauffähig) | klein | AD-3 |
| P1 | Relation-Embed-Bug in EntrySerializer fixen | klein-mittel | AD-4 |
| P1 | Migrator: Fehlerbehandlung bei DDL-Fehlern | mittel | AD-5 |
| P1 | REST-Sichtbarkeit personenbezogener Daten entscheiden (Produktentscheidung) | Entscheidung | AD-12 |
| P2 | FK-Erstellung prüfen + `ENGINE=InnoDB` explizit setzen | klein | AD-6 |
| P2 | Tests für RestPermissionChecker/Installer/RelationRepository | mittel | AD-9 |
| P2 | Entry-Liste je Template auf DB-Pagination umstellen | mittel | AD-7 |
| P2 | `resyncTitlesForTemplate()` batchen | klein | AD-8 |
| P3 | EntryRepository aufteilen (Debt) | groß | AD-10 |
| P3 | Feldtyp-Boilerplate deduplizieren (Debt) | klein-mittel | AD-11 |
| P3 | CI-Pipeline + statische Analyse (PHPStan) einführen | mittel | — |

Die Phase-2-4-Vorbereitung ist gut gelungen — die Architektur erlaubt die meisten geplanten Erweiterungen ohne Fundamentalumbau. Die größten Umbauten betreffen weiterhin den Query-Builder (OR-Logik, DISTINCT-ORDER-BY-Bug) und die Relation-Infrastruktur für bidirektionale Links.

---

*Behobene Issues sind mit ✓ markiert. Neue Befunde ergänzen Abschnitt 8 (nicht separate Dateien) — siehe Pflege-Hinweis in [`ROADMAP.md`](ROADMAP.md).*
