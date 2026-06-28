# bs-fabric — REST-API-Referenz

> **Zielgruppe:** Theme-Entwickler und Headless-Frontend-Autoren  
> **Stand:** Juni 2026 · Phase 1 (read-only)  
> **Plugin-Architektur:** [`ARCHITECTURE.md`](ARCHITECTURE.md) · **Status:** [`ROADMAP.md`](ROADMAP.md)

---

## Einleitung

Die REST-API liefert **published** Fabric-Entries als JSON — unabhängig vom aktiven
WordPress-Theme. Sie ist das Verbindungsglied für:

- JavaScript-Frontends im Theme (ohne PHP-Templates)
- Headless-Clients (React, Next.js, Mobile)
- Externe Integrationen

Die PHP-API `bs_fabric()` bleibt für klassische Theme-Templates die erste Wahl
(serverseitig, kein HTTP-Roundtrip). REST und PHP-API lesen dieselben Tabellen
und nutzen dieselbe Query-Logik (`EntryQuery`).

**Noch nicht verfügbar (geplant):** Schreiben (POST/PATCH/DELETE), Template-Liste,
Seiten-Pagination (`page`), CORS-Konfiguration — siehe [`ROADMAP.md`](ROADMAP.md).

---

## Basis-URL und Namespace

| | |
|---|---|
| **Namespace** | `bs-fabric/v1` |
| **Basis-URL** | `{WordPress-Site}/wp-json/bs-fabric/v1/` |
| **Beispiel** | `https://example.com/wp-json/bs-fabric/v1/templates/einrichtung/entries` |

Permalink-Struktur und Multisite folgen den WordPress-REST-Standards
(`rest_url('bs-fabric/v1/')`).

---

## Authentifizierung

| Szenario | Mechanismus | Nonce nötig? |
|---|---|---|
| **`published` Entries lesen** | Keine — öffentlich | Nein |
| **Draft / Archived / `status=any`** | Eingeloggt mit `bs_fabric_manage_entries` | Ja (Cookie + Nonce) oder Application Password |
| **Einzelner Draft ohne Berechtigung** | — | Antwort **403** |

### Öffentlicher Lesezugriff

```bash
curl -s "https://example.com/wp-json/bs-fabric/v1/templates/einrichtung/entries" \
  -H "Accept: application/json"
```

### Eingeloggter Redakteur (Preview)

WordPress-Session-Cookie + REST-Nonce:

```bash
curl -s "https://example.com/wp-json/bs-fabric/v1/templates/einrichtung/entries?status=draft" \
  -H "Accept: application/json" \
  -H "X-WP-Nonce: DEIN_WP_REST_NONCE" \
  --cookie "wordpress_logged_in_...=..."
```

Nonce im Theme: `wp_create_nonce('wp_rest')`.

### Application Passwords (Headless / extern)

WordPress **Application Passwords** (Benutzerprofil → Anwendungspasswörter) mit
HTTP Basic Auth — Standard für externe REST-Clients.

---

## Endpoints — Übersicht

| Methode | Pfad | Zweck |
|---|---|---|
| `GET` | `/templates/{template}/entries` | Gefilterte Entry-Liste |
| `GET` | `/templates/{template}/entries/{id}` | Einzelner Entry |
| `GET` | `/templates/{template}/entries/count` | Anzahl (gleiche Filter wie Collection) |

`{template}` = Template-**name** (slug, z. B. `einrichtung`).

---

## GET `/templates/{template}/entries`

Liefert ein **Array** von Entry-Objekten. Response-Header **`X-WP-Total`**
enthält die Gesamtanzahl (ohne `per_page`-Limit).

### Parameter

| Parameter | Typ | Default | Beschreibung |
|---|---|---|---|
| `template` | string | *Pfad* | Template-Name (Pflicht, im URL-Pfad) |
| `where[feldname]` | string | — | Feldfilter; mehrere = **AND** |
| `orderby` | string | `created_at` | Sortierung: Meta-Spalte oder Feldname |
| `order` | `asc` \| `desc` | `desc` | Sortierrichtung |
| `per_page` | int | `10` | Max. Treffer (1–100) |
| `status` | string | `published` | `published`, `draft`, `archived`, `any` |
| `with` | string (CSV) | — | Relation-Felder als verschachtelte Objekte einbetten |

**Meta-Spalten für `orderby`:** `id`, `title`, `status`, `created_at`, `updated_at`

### Request (curl)

```bash
curl -s "https://example.com/wp-json/bs-fabric/v1/templates/einrichtung/entries\
?where[stadt]=Wesel\
&orderby=name\
&order=asc\
&per_page=10\
&with=ansprechpartner" \
  -H "Accept: application/json"
```

### Response (200)

```json
[
  {
    "id": 42,
    "template": "einrichtung",
    "title": "Stadtwerke Wesel",
    "status": "published",
    "language": "de",
    "created_at": "2026-01-15 10:00:00",
    "updated_at": "2026-06-01 14:30:00",
    "_links": {
      "self": [
        {
          "href": "https://example.com/wp-json/bs-fabric/v1/templates/einrichtung/entries/42"
        }
      ],
      "collection": [
        {
          "href": "https://example.com/wp-json/bs-fabric/v1/templates/einrichtung/entries"
        }
      ]
    },
    "name": "Stadtwerke Wesel",
    "stadt": "Wesel",
    "logo": {
      "id": 105,
      "url": "https://example.com/wp-content/uploads/logo.png",
      "alt": "Stadtwerke Logo"
    },
    "ansprechpartner": [
      {
        "id": 12,
        "template": "person",
        "title": "Dr. Anna Müller",
        "status": "published",
        "language": "de",
        "created_at": "2026-01-10 09:00:00",
        "updated_at": "2026-01-10 09:00:00",
        "_links": {
          "self": [
            {
              "href": "https://example.com/wp-json/bs-fabric/v1/templates/person/entries/12"
            }
          ],
          "collection": [
            {
              "href": "https://example.com/wp-json/bs-fabric/v1/templates/person/entries"
            }
          ]
        },
        "name": "Dr. Anna Müller",
        "email": "a.mueller@example.com"
      }
    ]
  }
]
```

### Fehlerfälle

| HTTP | Code | Ursache |
|---|---|---|
| **403** | `rest_forbidden` | `status=draft|archived|any` ohne `manage_entries` |
| **404** | — | Unbekanntes Template (leeres Array, `X-WP-Total: 0`) |

---

## GET `/templates/{template}/entries/{id}`

Einzelner Entry. `{id}` = numerische Entry-ID.

### Parameter

| Parameter | Typ | Default | Beschreibung |
|---|---|---|---|
| `template` | string | *Pfad* | Template-Name (Pflicht) |
| `id` | int | *Pfad* | Entry-ID (Pflicht) |
| `with` | string (CSV) | — | Relation-Embed (siehe Collection) |

### Request (curl)

```bash
curl -s "https://example.com/wp-json/bs-fabric/v1/templates/einrichtung/entries/42\
?with=ansprechpartner" \
  -H "Accept: application/json"
```

### Response (200)

Einzelnes JSON-Objekt — Struktur wie ein Element aus der Collection oben.

Ohne `with`:

```json
{
  "id": 42,
  "template": "einrichtung",
  "title": "Stadtwerke Wesel",
  "status": "published",
  "language": "de",
  "created_at": "2026-01-15 10:00:00",
  "updated_at": "2026-06-01 14:30:00",
  "_links": {
    "self": [{ "href": "https://example.com/wp-json/bs-fabric/v1/templates/einrichtung/entries/42" }],
    "collection": [{ "href": "https://example.com/wp-json/bs-fabric/v1/templates/einrichtung/entries" }]
  },
  "name": "Stadtwerke Wesel",
  "adresse": {
    "street": "Hauptstraße 1",
    "zip": "46483",
    "city": "Wesel",
    "country": "DE"
  },
  "ansprechpartner": [12]
}
```

### Fehlerfälle

| HTTP | Code | Ursache |
|---|---|---|
| **404** | `bs_fabric_entry_not_found` | ID existiert nicht oder gehört nicht zum Template |
| **403** | `bs_fabric_rest_forbidden` | Entry ist `draft`/`archived`, Nutzer ohne `manage_entries` |
| **400** | `bs_fabric_rest_invalid_params` | Ungültige Template-/ID-Parameter |

---

## GET `/templates/{template}/entries/count`

Liefert `{ "count": n }` — dieselben Filter wie Collection (`where`, `status`, …),
aber **ohne** `per_page`-Limit.

### Parameter

Wie Collection (ohne `per_page` relevant für die Antwort).

### Request (curl)

```bash
curl -s "https://example.com/wp-json/bs-fabric/v1/templates/einrichtung/entries/count\
?where[stadt]=Wesel" \
  -H "Accept: application/json"
```

### Response (200)

```json
{
  "count": 17
}
```

### Fehlerfälle

| HTTP | Code | Ursache |
|---|---|---|
| **403** | `rest_forbidden` | Unveröffentlichter `status`-Filter ohne Berechtigung |

---

## Feldtyp-Serialisierung

Werte entsprechen `$entry->{$feldname}` in der PHP-API — aufbereitet via
`FieldTypeInterface::renderValue()`, nicht als Roh-DB-Werte.

| Feldtyp | JSON-Typ / Form | Beispiel |
|---|---|---|
| `text` | `string` \| `null` | `"Stadtwerke Wesel"` |
| `textarea` | `string` \| `null` | `"Kurzbeschreibung…"` |
| `richtext` | `string` (HTML) | `"<p>Absatz</p>"` |
| `number` | `number` \| `null` | `42.5` |
| `boolean` | `boolean` | `true` |
| `date` | `string` | `"2026-06-15"` |
| `select` | `string` | `"option_a"` |
| `code` | `string` | `"<div>…</div>"` |
| `image` | Objekt \| `null` | `{ "id": 105, "url": "…", "alt": "…" }` |
| `file` | Objekt \| `null` | `{ "id": 88, "url": "…", "filename": "…" }` |
| `address` | Objekt \| `null` | `{ "street": "…", "zip": "…", "city": "…", "country": "DE" }` |
| `relation` | siehe unten | `[12, 15]` oder embedded Array |

Leere Felder: `null`, leeres Array (Relation) oder fehlender Wert je nach Typ.

---

## Relation-Embed (`with`)

Relation-Felder verweisen auf Entries eines ** anderen Templates** (konfiguriert
im Feld-Setting `template`).

| Request | JSON für Relation-Feld |
|---|---|
| ohne `with` | **ID-Array:** `[12, 15]` |
| `?with=ansprechpartner` | **Objekt-Array:** voll serialisierte Related Entries |
| `?with=ansprechpartner,dokumente` | Mehrere Felder embedden (CSV) |

### Embed-Regeln

1. **Eine Ebene** — verschachtelte Relationen in embeddeten Entries bleiben ID-Arrays.
2. **`with` auf Collection** ruft intern `EntryQuery::with()` auf → Batch-Load
   (`eagerLoadRelations`), kein N+1 bei Listen.
3. Feldname muss exakt dem Template-Feld entsprechen (`ansprechpartner`, nicht der
   Template-Label-Text).

### Beispiel: IDs vs. Objekte

**Ohne Embed:**

```json
"ansprechpartner": [12, 15]
```

**Mit `?with=ansprechpartner`:**

```json
"ansprechpartner": [
  {
    "id": 12,
    "template": "person",
    "title": "Dr. Anna Müller",
    "status": "published",
    "name": "Dr. Anna Müller",
    "email": "a.mueller@example.com",
    "telefon": "+49 281 12345"
  }
]
```

---

## Theme-Integration

### PHP: REST-Basis registrieren

```php
add_action('wp_enqueue_scripts', function (): void {
    wp_enqueue_script(
        'mein-theme-fabric',
        get_stylesheet_directory_uri() . '/assets/js/fabric-entries.js',
        [],
        '1.0.0',
        true
    );

    wp_localize_script('mein-theme-fabric', 'bsFabricRest', [
        'root'  => esc_url_raw(rest_url('bs-fabric/v1/')),
        'nonce' => wp_create_nonce('wp_rest'),
    ]);
});
```

`bsFabricRest.root` — Basis-URL für alle fetch-Aufrufe.  
`bsFabricRest.nonce` — nur für Draft-Preview oder eingeloggte Requests nötig.

### JavaScript: Collection laden und rendern

```javascript
(async function loadEinrichtungen() {
  const root = window.bsFabricRest?.root ?? '/wp-json/bs-fabric/v1/';
  const url = new URL(`${root}templates/einrichtung/entries`);
  url.searchParams.set('where[stadt]', 'Wesel');
  url.searchParams.set('per_page', '20');
  url.searchParams.set('with', 'ansprechpartner');

  const response = await fetch(url.toString(), {
    method: 'GET',
    headers: { Accept: 'application/json' },
  });

  if (!response.ok) {
    console.error('REST-Fehler', response.status);
    return;
  }

  const total = response.headers.get('X-WP-Total');
  const entries = await response.json();
  const list = document.getElementById('einrichtungen-list');

  if (!list) {
    return;
  }

  list.innerHTML = entries.map((entry) => {
    const contacts = (entry.ansprechpartner ?? [])
      .map((person) => escapeHtml(person.name ?? person.title ?? ''))
      .filter(Boolean)
      .join(', ');

    const image = entry.logo?.url
      ? `<img src="${escapeHtml(entry.logo.url)}" alt="${escapeHtml(entry.logo.alt ?? '')}" loading="lazy" />`
      : '';

    return `
      <article class="einrichtung-card">
        ${image}
        <h2>${escapeHtml(entry.title ?? entry.name ?? '')}</h2>
        <p>${contacts}</p>
      </article>
    `;
  }).join('');

  if (total !== null) {
    const meta = document.getElementById('einrichtungen-total');
    if (meta) {
      meta.textContent = `${entries.length} von ${total} Einträgen`;
    }
  }
})();

function escapeHtml(value) {
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}
```

---

## Erweiterung: Filter-Hook `bs_fabric_rest_entry`

Analog zu `bs_fabric_query_args` für die PHP-API. Wird **nach** der Serialisierung,
**vor** der JSON-Antwort aufgerufen.

```php
add_filter('bs_fabric_rest_entry', function (
    array $data,
    \BsFabric\Entries\Entry $entry,
    string $templateName
): array {
    // Internes Feld nicht an Frontend exposen
    unset($data['interne_notiz']);

    // Computed-Feld ergänzen
    if (isset($data['adresse']) && is_array($data['adresse'])) {
        $data['adresse_kurz'] = trim(
            ($data['adresse']['zip'] ?? '') . ' ' . ($data['adresse']['city'] ?? '')
        );
    }

    return $data;
}, 10, 3);
```

| Argument | Typ | Beschreibung |
|---|---|---|
| `$data` | `array` | Serialisiertes Entry (Meta + Felder + `_links`) |
| `$entry` | `Entry` | Quell-Value-Object |
| `$templateName` | `string` | Template-Name slug |

---

## Siehe auch

- PHP-API: [`README.md`](README.md) → Abschnitt **API reference**
- Architektur & Auth-Details: [`ARCHITECTURE.md`](ARCHITECTURE.md) §3, §8
- Geplante Erweiterungen: [`ROADMAP.md`](ROADMAP.md) → REST Phase 2 / Phase 4

---

*bs-fabric · [bezugssysteme.de](https://bezugssysteme.de)*
