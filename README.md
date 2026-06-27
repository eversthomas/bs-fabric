# bs-fabric

ProcessWire-inspired templates, fields, and entries for WordPress — schema and content as first-class objects, stored in dedicated tables (not post meta), with a chainable `bs_fabric()` API for theme developers.

**Namespace:** `BsFabric\` · **Prefix:** `bs_fabric_` · **Author:** [bezugssysteme.de](https://bezugssysteme.de)

---

## Requirements

| Requirement | Version |
|---|---|
| PHP | 8.2+ |
| WordPress | 7.0+ |
| Composer | 2.x (for autoloading and tests) |

Node.js is only required if you rebuild the Explorer admin bundle (`npm install && npm run build`).

---

## Installation

1. Clone or copy the plugin into `wp-content/plugins/bs-fabric/`.
2. Install PHP dependencies:

```bash
cd wp-content/plugins/bs-fabric
composer install
```

3. Activate **bs-fabric** in the WordPress admin.
4. On activation, six custom tables are created via `dbDelta`. Capabilities and roles are assigned:
   - `bs_fabric_manage_schema` — Administrators (Templates + Fields + Config)
   - `bs_fabric_manage_entries` — Administrators, Editors, **Mitarbeiter** (Entries in lists)
   - `bs_fabric_assign_template_fields` — Administrators + Editors (assign fields from library when allowed per template)
   - WP role **`Fabric Mitarbeiter`** (`bs_fabric_mitarbeiter`) — entries only, no field assignment

---

## Quick start

The global function `bs_fabric()` is loaded on the **frontend** (`!is_admin()`).
It is **not** available during `admin-ajax.php` requests yet — see Strang D in `ARCHITECTURE.md`.

```php
// Query: all published entries of a template
$entries = bs_fabric('article')
    ->status('published')
    ->orderBy('created_at', 'DESC')
    ->get();

foreach ($entries as $entry) {
    echo esc_html((string) $entry->title);
}
```

```php
// Single entry by hash syntax
$entry = bs_fabric('article#42');
echo esc_html((string) $entry->title);
```

```php
// Render a collection with the card view and a wrapper
echo bs_fabric('article')
    ->where('featured', '1')
    ->limit(3)
    ->get()
    ->render('card', '<div class="cards">', '</div>');
```

Admin UI: **Fabric** → Übersicht (Dashboard), Explorer, template submenus, **Einstellungen** (Templates, Fields, Docs, Configuration).

> **Architecture:** [`ARCHITECTURE.md`](ARCHITECTURE.md) (German, specs & schema).  
> **Roadmap (status checklist):** [`ROADMAP.md`](ROADMAP.md).

---

## Field types

Twelve built-in types, registered via `FieldRegistry` and extensible through `bs_fabric_field_types`.

| Type | Purpose |
|---|---|
| `text` | Single-line text (names, URLs, phone, email) |
| `textarea` | Multi-line plain text |
| `richtext` | Rich text (WordPress editor) |
| `image` | Single image via Media Library |
| `file` | File attachment via Media Library |
| `date` | Date, optionally with time |
| `select` | Fixed option list |
| `boolean` | Yes/no toggle |
| `number` | Numeric value with optional min/max/step/unit |
| `address` | Structured postal address (street, zip, city, country) as JSON |
| `relation` | Link to entries of another template (single/multi, sortable) |
| `code` | Code storage with CodeMirror (no execution) |

Field `name` is the immutable slug identifier; `label` is the editable display name.

---

## API reference

### `bs_fabric(string $selector, ?int $id = null): EntryQuery|Entry|null`

| Call | Returns |
|---|---|
| `bs_fabric('template')` | `EntryQuery` |
| `bs_fabric('template#42')` | `Entry` or `null` |
| `bs_fabric('template', 42)` | `Entry` or `null` |

### `EntryQuery`

Fluent builder for a single template. Methods return a new instance (immutable chaining).

| Method | Description |
|---|---|
| `where(string $field, mixed $value)` | Filter by field value in `bs_fabric_entry_values` |
| `with(string $relationField)` | Eager-load relation field (batch, no N+1) |
| `orderBy(string $field, string $direction = 'ASC')` | Sort by entry column or field value |
| `limit(int $n)` | Limit result count |
| `status(string $status)` | Filter by entry status (`draft`, `published`, `archived`) |
| `withAnyStatus()` | Include draft/archived entries (disables default `published` filter) |
| `get()` | `EntryCollection` |
| `first()` | `?Entry` |
| `count()` | `int` |

Query arguments pass through `bs_fabric_query_args`.

### `Entry`

Value object with magic property access for meta and field values.

```php
$entry->title;          // meta
$entry->body;           // field value (sanitized/rendered)
$entry->contacts;       // relation → EntryCollection
$entry->toArray();
$entry->toJson();
$entry->getFields();    // field definitions
$entry->render('detail');
$entry->render('card', '<div>', '</div>');
```

### `EntryCollection`

Implements `IteratorAggregate` and `Countable`.

```php
foreach ($collection as $entry) { … }
$collection->first();   // ?Entry
$collection->count();   // int
$collection->toArray();
$collection->render('list');
$collection->render('card', '<div class="grid">', '</div>');
```

### `render()`

Rendering delegates to `BsFabric\Frontend\Renderer` and PHP view templates.

| View | Scope | Output |
|---|---|---|
| `card` | Single entry | Title, first text field, first image, link |
| `list` | Collection | `<ul>` with title + short description |
| `detail` | Single entry | All populated fields |

Built-in templates: `templates/default/{view}.php`.

---

## Theme overrides for view templates

Resolution order:

1. `{active-theme}/bs-fabric/{view}.php`
2. `{active-theme}/bs-fabric/default/{view}.php`
3. `{plugin}/templates/default/{view}.php`

Templates receive `$entry` or `$collection` and `$renderer` (`BsFabric\Frontend\Renderer`).

Override or redirect via filter:

```php
add_filter('bs_fabric_template_path', function (
    string $path,
    string $view,
    array $context
): string {
    if ($view === 'card') {
        return get_stylesheet_directory() . '/bs-fabric/my-card.php';
    }
    return $path;
}, 10, 3);
```

Entry URLs in templates use `bs_fabric_entry_url` (default: `#bs-fabric-entry-{id}`).

---

## Hooks

### Actions

| Hook | Arguments | When |
|---|---|---|
| `bs_fabric_before_entry_save` | `int $id`, `array $data` | Before entry create/update |
| `bs_fabric_after_entry_save` | `int $id`, `array $data` | After entry create/update |
| `bs_fabric_before_entry_delete` | `int $id` | Before entry delete |
| `bs_fabric_after_entry_delete` | `int $id` | After entry delete |

### Filters

| Hook | Arguments | Purpose |
|---|---|---|
| `bs_fabric_field_types` | `array $types` | Register custom field type classes |
| `bs_fabric_query_args` | `array $args` | Modify frontend query before execution |
| `bs_fabric_template_path` | `string $path`, `string $view`, `array $context` | Override resolved view template path |
| `bs_fabric_entry_url` | `string $url`, `Entry $entry`, `string $view` | Entry link URL in templates |

---

## Extending field types

Implement `BsFabric\Fields\Types\FieldTypeInterface` and register the class:

```php
add_filter('bs_fabric_field_types', function (array $types): array {
    $types['rating'] = \MyPlugin\RatingField::class;
    return $types;
});
```

Required methods: `getType()`, `getLabel()`, `getDefaultSettings()`, `sanitizeValue()`, `sanitizeSettings()`, `renderInput()`, `renderSettings()`, `renderValue()`.

---

## Roadmap

Quick status and priorities: **[ROADMAP.md](ROADMAP.md)**. Field-type specs and sessions: **[ARCHITECTURE.md](ARCHITECTURE.md)**.

### Completed ✓

| Track | Scope |
|---|---|
| **MVP Backend** | 6 tables, 12 field types, repositories, admin CRUD, Explorer, `bs_fabric()` API, renderer |
| **UX + Docs** | Dynamic menus, list columns, Docs screen, code snippets |
| **Design System (DS-0–DS-3)** | Hybrid admin shell (`.bsf-app`), Dashboard, redesigned Fields/Templates/Entries, configuration tab, JSON schema export |
| **Production hardening (Strang D)** | N+1 fixes, DB transactions, Explorer server pagination, indexes, audit blocks A–F |
| **Core extensions** | Default `published` query filter, field types `boolean`, `number`, `address` (A1) |
| **Editor & lists (Strang E, partial)** | Excel export, card view, bulk actions, lifecycle hints |

### In progress / planned

| Track | Priority items |
|---|---|
| **Address A2–A4** | Geocoding, admin map, frontend map (see ARCHITECTURE.md §5.1) |
| **B1** | Bidirectional relations, query engine (`whereIn`, `orWhere`), WP AI Client, granular capabilities, `reference` / `gallery` field types |
| **B2** | JSON import (Web UI), REST API, **DesignBlocks** (code → REDAXO-style modules), Tiptap richtext, Gutenberg blocks, repeater |
| **B3** | Multilingual entries, headless API |

### Built-in field types today

Twelve types: `text`, `textarea`, `richtext`, `image`, `file`, `date`, `select`, `boolean`, `number`, `address`, `relation`, `code`.

**Planned** (not yet implemented): `reference`, `repeater`, `gallery`, `multiselect`, `url`/`email`, Tiptap for `richtext`, DesignBlocks evolution of `code`, address geo stages A2–A6. See [`ARCHITECTURE.md`](ARCHITECTURE.md) §5 and [`ROADMAP.md`](ROADMAP.md).

---

## Development workflow

Daily plugin development should keep **manual QA data** (Templates, Fields, Entries) separate from automated integration tests.

### Running tests

| Command | What it does | Safe on dev site? |
|---|---|---|
| `composer test` | Unit tests only (Brain Monkey, no DB) | **Yes** — run anytime |
| `composer test:unit` | Same as `composer test` | **Yes** |
| `composer test:integration` | Integration suite (skipped without env vars) | **Yes** — skipped by default |
| `composer test:all` | Unit + integration | **Only** with env vars on a **separate test database** |

`composer test` is the default for day-to-day work. It never connects to your WordPress database.

Integration tests **delete all rows** from every `bs_fabric_*` table before each test case. They are **disabled by default**. To run the full suite:

```bash
export BS_FABRIC_INTEGRATION_TESTS=1
export BS_FABRIC_TEST_DB_NAME=wordpress_test   # must match DB_NAME in wp-config.php of the test install
composer test:all
```

Both env vars are required. `BS_FABRIC_TEST_DB_NAME` must exactly match the database of the WordPress instance loaded by the tests — otherwise PHPUnit skips the integration suite instead of wiping the wrong database.

### Recommended setup

1. **Dev site** — your main local WordPress (MAMP, Local, etc.) for Templates, Fields, Entries, and manual QA. Use `composer test` freely.
2. **Test database** — a second WordPress install or [wp-env](https://github.com/WordPress/gutenberg/tree/trunk/packages/env) container with its own `DB_NAME`. Point `BS_FABRIC_TEST_DB_NAME` at that database only.
3. **Snapshots** — before risky schema experiments or bulk changes, dump the six plugin tables:

```bash
mysqldump -u root wordpress \
  wp_bs_fabric_fields \
  wp_bs_fabric_templates \
  wp_bs_fabric_template_fields \
  wp_bs_fabric_entries \
  wp_bs_fabric_entry_values \
  wp_bs_fabric_relations \
  > bs-fabric-backup-$(date +%Y%m%d).sql
```

Replace `wordpress` and the `wp_` table prefix if your install differs.

### Data safety

| Action | Effect on `bs_fabric_*` data |
|---|---|
| Code change | No effect |
| Plugin **deactivate / reactivate** | No effect — `Migrator` runs `dbDelta` only when `bs_fabric_db_version` is outdated |
| `composer test` (unit) | No effect |
| `composer test:all` with env vars on test DB | **Deletes all rows** before each integration test |
| Plugin **delete** from WP admin | See ARCHITECTURE.md §15 — tables only if “delete data on uninstall” enabled; entries always remain on **deactivate** but theme API breaks |

> **Warning:** Deleting the plugin from the WordPress admin permanently removes all Templates, Fields, and Entries. Deactivating is safe; deleting is not.

---

## Tests

Requires Composer dev dependencies. See [Development workflow](#development-workflow) for commands, env vars, and data-safety rules.

```bash
composer test              # unit tests only (safe for daily dev)
composer test:unit         # same as above
composer test:integration  # skipped unless env vars are set
composer test:all          # unit + integration (requires BS_FABRIC_* env vars)
```

**Unit tests** cover `FieldRegistry`, field-type `sanitizeValue()`, `EntryQuery`, `Entry` / `EntryCollection`, `Renderer`, `PublicApi`, and `Migrator` version checks — no WordPress database required.

**Integration tests** cover `Installer`, `FieldRepository`, `TemplateRepository`, and `EntryRepository` against a real MySQL database when explicitly enabled.

---

## Storage

All content lives in custom tables (`bs_fabric_*`). No post meta, no custom post types.

```
bs_fabric_fields
bs_fabric_templates
bs_fabric_template_fields
bs_fabric_entries
bs_fabric_entry_values
bs_fabric_relations
```

---

## License

GPL-2.0-or-later — see [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html).

**bs-fabric** · [bezugssysteme.de](https://bezugssysteme.de)
