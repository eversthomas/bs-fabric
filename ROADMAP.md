# bs-fabric — Roadmap

> **Stand:** August 2026 (Audit-Konsolidierung: Claude Code Review + Cursor-Analyse)  
> **Sync:** Diese Datei ist die **schnelle Checkliste** (Status ✓/◻).  
> Spezifikationen, Sessions und Architektur-Details → [`ARCHITECTURE.md`](ARCHITECTURE.md)  
> Technische Einzelbefunde des Aug-2026-Audits → [`CODE_ANALYSIS.md`](CODE_ANALYSIS.md) Abschnitt 8 (AD-1 … AD-14)  
> **Pflege-Regel:** Status hier ändern; bei neuen Features kurz in ARCHITECTURE §5 / §14 nachziehen. Neue Audit-Befunde ergänzen CODE_ANALYSIS.md Abschnitt 8 und werden hier einsortiert — **keine neuen losen Analyse-Dateien im Plugin-Root anlegen.**

---

## Als Nächstes (priorisiert)

| Prio | Item | Session / Phase | Hinweis |
|:---:|---|---|---|
| 1 | Gutenberg-Blöcke (`entry-list`, `single-entry`) | Phase 2 | |
| 2 | Query: `whereIn()`, `orWhere()`, Frontend-Pagination | Phase 3 | |
| 3 | REST Phase 2 (Pagination, Templates-Liste, CORS) | Phase 2 | |

Bewusste Entscheidung (Aug 2026): Die unten stehende „Härtung Runde 2“ blockiert Punkt 1–3 **nicht** — die dortigen Punkte betreffen Skalierung/Testinfrastruktur, die im aktuellen Solo-/Testbetrieb nicht akut sind. Ausnahme: AD-3/AD-4 (Test-Suite lauffähig machen) sind trivial und sollten vor der nächsten größeren Feature-Session miterledigt werden.

---

## Zurückgestellt (bewusst)

| Item | Grund |
|---|---|
| Granulare Capabilities pro Template (`bs_fabric_manage_entries_*`) | Nicht nötig; R1/R2 mit drei Caps + Template-Setting ausreichend |
| Entry-Ownership (`created_by`-Sperre) | Gemeinsame Listenpflege mit Vertretung; Audit-Log statt Sperre (siehe „Bewusst nicht geplant“) |

---

## Härtung Runde 2 (laufend, parallel zu Phase 2/3 — kein Blocker für Gutenberg)

> Aus Audit Aug 2026 (Claude Code Review + Cursor-Analyse). Details siehe [`CODE_ANALYSIS.md`](CODE_ANALYSIS.md) Abschnitt 8.

| Prio | Item | Aufwand | Ref. |
|:---:|---|---|---|
| P1 | `WP_REST_Request`-Test-Stub einbinden (`RestQueryParserTest` läuft aktuell nicht) | klein | AD-3 |
| P1 | Relation-Embed-Bug in `EntrySerializer` fixen (liefert `[]` statt IDs) | klein-mittel | AD-4 |
| P1 | `Migrator`: Fehlerbehandlung bei fehlgeschlagenen DDL-Schritten (aktuell stiller Fail, Version wird trotzdem hochgesetzt) | mittel | AD-5 |
| P1 | Entscheidung: REST-Sichtbarkeit personenbezogener Daten (aktuell alle `published`-Entries global ohne Auth abrufbar) | Entscheidung | AD-12 |
| P2 | `Installer::ensureForeignKeys()` auf Fehler prüfen; `ENGINE=InnoDB` explizit setzen | klein | AD-6 |
| P2 | Tests für `RestPermissionChecker`, `Installer`, `RelationRepository` (aktuell 0 % Coverage) | mittel | AD-9 |
| P2 | Entry-Liste je Template auf DB-seitige Pagination umstellen (Explorer hat das bereits) | mittel | AD-7 |
| P2 | `resyncTitlesForTemplate()` batchen (Timeout-Risiko bei großen Templates) | klein | AD-8 |
| P3 | `EntryRepository` aufteilen (1773 Zeilen, God-Class) | groß | AD-10 |
| P3 | Feldtyp-Boilerplate (`mergeSettings`/`fieldName`/`settingsName`, 13× dupliziert) in Trait zusammenführen | klein-mittel | AD-11 |
| P3 | CI-Pipeline + statische Analyse (PHPStan) einführen | mittel | — |
| P3 | REST-API-Nutzung im Admin-Doku-Tab ergänzen (aktuell nur `REST-API.md` im Repo, nicht im Admin sichtbar) | klein | — |

### Erledigt (Aug 2026) ✓

- [x] CSV-Formula-Injection im Export behoben — `EntryListExporter::escapeCell()` prefixt gefährliche Startzeichen (`=+-@`, Tab, CR) nach OWASP-Empfehlung; Regressionstest ergänzt
- [x] Test-Suite lief nur zu ~74 % durch (stiller Absturz durch `exit()` in Template-Include, `ABSPATH` fehlte) — `tests/bootstrap.php` definiert `ABSPATH` jetzt, Suite läuft vollständig (130/130 Tests werden ausgeführt; verbleibende 8 Errors + 1 Failure sind vorbestehende, jetzt sichtbare Punkte, siehe AD-3/AD-4)
- [x] ARCHITECTURE.md §11 Selbstwiderspruch zum Uninstall-Verhalten korrigiert

---

## Phase 1 (Stabilität) — abgeschlossen ✓

> War als „Offen" gelistet, ist aber vollständig erledigt (Aug 2026 bereinigt). Weitere Stabilitätsarbeit läuft jetzt unter „Härtung Runde 2" oben.

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
- [x] Feldtyp `reference`
- [x] Feldtyp `address` **A1** Basis
- [x] Feldtyp `address` **A2** Geocoding (Lat/Lng beim Save)
- [x] Feldtyp `address` **A3** Admin-Karte (Leaflet)
- [x] Feldtyp `address` **A4** Frontend-Karte in Views
- [x] Explorer serverseitige Pagination & Filter *(Strang D)*
- [ ] Feldtypen `url` / `email`, `gallery`, `multiselect` *(Backlog Phase 2)*

### REST-API Phase 2

- [ ] Pagination: `page` + `offset` in `EntryQuery` (REST-Parameter `page`)
- [ ] `GET /templates` — `TemplatesController` (Template-Metadaten auflisten)
- [ ] CORS-Header für externe Headless-Domains (konfigurierbar)
- [ ] **Entscheidung offen (Audit Aug 2026, AD-12):** REST-Sichtbarkeit je Template konfigurierbar machen? Aktuell sind alle `published`-Entries global ohne Auth lesbar — bei personenbezogenen Daten (z. B. `address`-Felder) vor Produktivbetrieb klären, siehe „Härtung Runde 2"

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
- [x] Feldtyp `address` **A3** (Leaflet-Karte im Entry-Editor, Pin überschreibt Lat/Lng)
- [x] Feldtyp `address` **A4** (Frontend-Karte, `formatted` / `maps_url`, detail.php)
- [x] Feldtyp `reference` (WP-Beiträge/Seiten/Medien, AJAX-Suche, Single/Multi)

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
| **A3** | ✓ | Admin-Karte (Leaflet) |
| **A4** | ✓ | Frontend-Karte |
| A5–A6 | ◻ | Geo-Queries — Phase 3 |

Details (Dateien, Tests, DoD): ARCHITECTURE §14
