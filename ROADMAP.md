# bs-fabric — Roadmap

> **Stand:** Juli 2026  
> **Sync:** Diese Datei ist die **schnelle Checkliste** (Status ✓/◻).  
> Spezifikationen, Sessions und Architektur-Details → [`ARCHITECTURE.md`](ARCHITECTURE.md)  
> **Pflege-Regel:** Status hier ändern; bei neuen Features kurz in ARCHITECTURE §5 / §14 nachziehen.

---

## Als Nächstes (priorisiert)

| Prio | Item | Session / Phase | Hinweis |
|:---:|---|---|---|
| 1 | Feldtyp `address` **A3** Admin-Karte (Leaflet) | **Session A3** · Phase 2 | Spec: ARCHITECTURE §5.1 Stufe A3 |
| 2 | Feldtyp `reference` (WP-Posts/Seiten/Medien) | Phase 2 | |
| 3 | Gutenberg-Blöcke (`entry-list`, `single-entry`) | Phase 2 | |

---

## Zurückgestellt (bewusst)

| Item | Grund |
|---|---|
| Granulare Capabilities pro Template (`bs_fabric_manage_entries_*`) | Nicht nötig; R1/R2 mit drei Caps + Template-Setting ausreichend |

---

## Offen — Phase 1 (Stabilität)

- [x] **Session R2** — Rolle Mitarbeiter + Capability `assign_template_fields`
  - [x] WP-Rolle `bs_fabric_mitarbeiter` (nur Listen pflegen)
  - [x] Redakteur: `assign_template_fields`; Feld-Zuweisung nur mit Cap + Template-Freigabe
- [x] **Session R1** — Redaktionsmodell & Audit
  - [x] Template-Setting: Redakteure dürfen Felder aus Bibliothek zuweisen
  - [x] `updated_by` + Entry-Audit-Log (created / updated / deleted) — Migration 1.0.3
- [x] Default-Statusfilter Frontend-Queries (`published` standardmäßig, `withAnyStatus()` für alle)
- [x] SQL-Performance-Indizes (`status`, `created_at`, Relations) — Migration 1.0.2
- [x] **REST-API Phase 1 (read-only)**
  - [x] `GET /templates/{template}/entries` — Collection mit `where`, `orderby`, `per_page`, `with`
  - [x] `GET /templates/{template}/entries/{id}` — Single Entry
  - [x] `GET /templates/{template}/entries/count` — Anzahl
  - [x] Auth: `published` öffentlich; `draft`/`archived`/`any` nur mit `manage_entries`
  - [x] Relation-Embed via `with` (1 Ebene)
  - [x] Filter-Hook `bs_fabric_rest_entry`
  - Doku: [`REST-API.md`](REST-API.md)

## Offen — Phase 2 (UX & Feldtypen)

- [ ] Gutenberg-Blöcke (`entry-list`, `single-entry`) + ServerSideRender
- [x] Feldtyp `boolean`
- [x] Feldtyp `number`
- [ ] Feldtyp `reference`
- [x] Feldtyp `address` **A1** Basis
- [x] Feldtyp `address` **A2** Geocoding (Lat/Lng beim Save)
- [ ] Feldtyp `address` **A3** Admin-Karte (Leaflet)
- [ ] Feldtyp `address` **A4** Frontend-Karte in Views
- [x] Explorer serverseitige Pagination & Filter *(Strang D)*
- [ ] Feldtypen `url` / `email`, `gallery`, `multiselect` *(Backlog Phase 2)*

### REST-API Phase 2

- [ ] Pagination: `page` + `offset` in `EntryQuery` (REST-Parameter `page`)
- [ ] `GET /templates` — `TemplatesController` (Template-Metadaten auflisten)
- [ ] CORS-Header für externe Headless-Domains (konfigurierbar)

## Offen — Phase 3 (Query & Struktur)

- [ ] Query: `whereIn()`, `orWhere()`, Frontend-Pagination
- [ ] `address` **A5** Filter Stadt/PLZ/Land
- [ ] `address` **A6** Umkreissuche (`whereNear`)
- [ ] Bidirektionale Relationen
- [ ] JSON-Schema-Import (Web-UI)

## Offen — Phase 4 (Skalierung)

- [ ] Repeater-Feldtyp
- [ ] DesignBlocks (`code`-Feld, REDAXO-artig)
- [ ] REST-API Write-Endpoints (Headless CRUD) — *Read-only Phase 1 bereits unter `src/Rest/`*
- [ ] Tiptap statt WP-Editor in `richtext`

## Backlog (nice-to-have)

- [ ] Feldtyp `color`
- [ ] Explorer-Export
- [ ] AI-Feldvorschläge (WP AI Client)

---

## Erledigt ✓

### MVP & Design System
- [x] Plugin-Bootstrap, PSR-4, ServiceContainer, Capabilities
- [x] 6 DB-Tabellen, Migrator, Uninstall-Option
- [x] 12 Basis-Feldtypen + FieldRegistry + Fields/Templates CRUD
- [x] Entry-Editor, Entry-Listen, Relationen, Template-Builder
- [x] Menü (Dashboard, Explorer, Einstellungen, dynamische Template-Punkte)
- [x] Frontend-API `bs_fabric()`, EntryQuery, Renderer, Theme-Templates
- [x] Design System DS-0 … DS-3 (Hybrid-Shell, Sheets, Schema-UI)
- [x] React-Explorer (DataViews) + Admin-Skin

### Production Hardening (Strang D)
- [x] N+1-Fixes EntryRepository (Hydration, Explorer, Recently Updated)
- [x] DB-Transaktionen create/update/deleteAll
- [x] Explorer `queryExplorerRows()` + serverseitige Pagination
- [x] DB-Indizes Migration 1.0.2
- [x] `sanitizeSettings()` pro Feldtyp
- [x] Code-Audit Blöcke A–F (Bugfixes, Tests, Performance)

### Phase 1 / Redaktion (Session R1 + R2)
- [x] Redaktionsmodell: gemeinsame Listenpflege, kein Ownership
- [x] Template-Setting `editors_may_manage_fields` + Editor-Zugang Template-Builder
- [x] Entry-Audit: `updated_by`, Tabelle `bs_fabric_entry_log`, Verlauf im Entry-Editor
- [x] Capability `bs_fabric_assign_template_fields` + Rolle **Mitarbeiter** (nur `manage_entries`)

### Phase 1 / 2 (Kern-Erweiterungen)
- [x] Default-Statusfilter: `bs_fabric()->get()` nur `published`, `->withAnyStatus()` für Entwürfe/Archiv
- [x] Feldtyp `boolean` (Ja/Nein, Hidden+Checkbox, Settings)
- [x] Feldtyp `number` (Min/Max/Step/Einheit)
- [x] Feldtyp `address` **A1** (Straße, PLZ, Ort, Land, JSON, formatierte Ausgabe)
- [x] Feldtyp `address` **A2** (Geocoding beim Save, Provider-Setting, Lat/Lng-Cache, Admin-Hinweis)

### Redaktion & Listen (Strang E, teilweise)
- [x] Daten-Lebenszyklus-Hinweise (Dashboard + Konfiguration)
- [x] Excel-Export je Template-Liste
- [x] Karten-Ansicht + Bulk-Aktionen (publish, duplicate, delete)
- [x] Sheet-Scrollfix (Field/Template-Sidebar)

### Schema-UI & Admin-Polish
- [x] Plugin-Menü bleibt auf Fabric-Seiten ausgeklappt
- [x] Switch „In Listenansicht“ im Field-Sheet (Doppel-Binding behoben)
- [x] Feld aus Template entfernen (AJAX, Warnung, Entry-Werte bereinigen)
- [x] Feld-Löschen: betroffene Templates in Fehlermeldung auflisten

### REST-API Phase 1 (read-only)
- [x] Endpoints: Collection, Single Entry, Count (`bs-fabric/v1`)
- [x] Query: `where`, `orderby`, `order`, `per_page`, `status`, `with`
- [x] Auth: published öffentlich; draft/archived mit `manage_entries`
- [x] Relation-Embed (1 Ebene), Hook `bs_fabric_rest_entry`
- [x] Doku: `REST-API.md`, README, ARCHITECTURE

### Bewusst nicht geplant
- Full-SPA-Admin · Post Meta / CPT-Storage · Gutenberg als Entry-Editor-Ersatz  
- Status `review` · `eval()` für `code` · eigener Feldtyp `map`/`geo` (→ `address`)  
- **Entry-Ownership** (`created_by`-Sperre) — gemeinsame Listenpflege mit Vertretung; Audit statt Sperre

---

## Cursor-Sessions

| Session | Status | Thema |
|---|---|---|
| 01–10, UX A/B, Doku, DS-0–DS-3 | ✓ | MVP + Design System |
| **R1** | ✓ | Redaktionsmodell + Entry-Audit |
| **R2** | ✓ | Mitarbeiter-Rolle + assign_template_fields |
| **A1** | ✓ | Feldtyp `address` Basis |
| **A2** | ✓ | Geocoding |
| **A3** | ◻ | Admin-Karte (Leaflet) — **nächste Session** |
| A4 | ◻ | Frontend-Karte |
| A5–A6 | ◻ | Geo-Queries — Phase 3 |

Details (Dateien, Tests, DoD): ARCHITECTURE §14
