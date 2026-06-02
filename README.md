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
4. On activation, six custom tables are created via `dbDelta`. Capabilities are assigned:
   - `bs_fabric_manage_schema` — Administrators (Templates + Fields)
   - `bs_fabric_manage_entries` — Administrators + Editors (Entries)

---

## Quick start

The global function `bs_fabric()` is loaded on the **frontend only** (`!is_admin()`).

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

Admin UI: **Fabric** → Explorer (all entries), template submenus (per-template lists), **Doku** (API reference).

---

## Field types

Nine built-in types, registered via `FieldRegistry` and extensible through `bs_fabric_field_types`.

| Type | Purpose |
|---|---|
| `text` | Single-line text (names, URLs, phone, email) |
| `textarea` | Multi-line plain text |
| `richtext` | Rich text (WordPress editor) |
| `image` | Single image via Media Library |
| `file` | File attachment via Media Library |
| `date` | Date, optionally with time |
| `select` | Fixed option list |
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

Required methods: `getType()`, `getLabel()`, `getDefaultSettings()`, `sanitizeValue()`, `renderInput()`, `renderSettings()`, `renderValue()`.

---

## Roadmap

### Phase 1 — MVP ✓

- ✓ Plugin bootstrap (PSR-4, ServiceContainer)
- ✓ Database installer (6 tables, migrations)
- ✓ 9 field types + `FieldTypeInterface`
- ✓ Field / Template / Entry / Relation repositories
- ✓ Admin: Fields, Templates, Entry list, Entry editor
- ✓ Admin: Explorer (DataViews), Docs screen
- ✓ Dynamic menu structure + capabilities
- ✓ Frontend: `bs_fabric()`, `EntryQuery`, `Entry`, `EntryCollection`
- ✓ Renderer + view templates (`card`, `list`, `detail`)
- ✓ Unit + integration tests (core)

### Phase 2 — planned

- Bidirectional relations
- Extended query engine (filters, joins, pagination)
- WP AI Client integration (field suggestions from descriptions)

### Phase 3 — roadmap

- `code` field → REDAXO-style modules (input/output templates)
- Tiptap as richtext field type
- REST API layer
- Gutenberg block
- Template import/export (JSON)
- Repeater field type

### Phase 4 — roadmap

- Multilingual entries
- Headless API layer

---

## Tests

Requires Composer dev dependencies and a WordPress test environment for integration tests.

```bash
composer test              # unit + integration
composer test:unit         # Brain Monkey, no WP DB
composer test:integration  # WP test suite + real DB
```

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
