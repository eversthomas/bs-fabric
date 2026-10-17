# bs-fabric — Roadmap

> **Stand:** September 2026 (REST-Sichtbarkeit + Gutenberg-Block/Shortcode ergänzt)  
> **Sync:** Diese Datei ist die **schnelle Checkliste** (Status ✓/◻).  
> Spezifikationen, Sessions und Architektur-Details → [`ARCHITECTURE.md`](ARCHITECTURE.md)  
> Technische Einzelbefunde des Aug-2026-Audits → [`CODE_ANALYSIS.md`](CODE_ANALYSIS.md) Abschnitt 8 (AD-1 … AD-14)  
> **Pflege-Regel:** Status hier ändern; bei neuen Features kurz in ARCHITECTURE §5 / §14 nachziehen. Neue Audit-Befunde ergänzen CODE_ANALYSIS.md Abschnitt 8 und werden hier einsortiert — **keine neuen losen Analyse-Dateien im Plugin-Root anlegen.**

---

## Als Nächstes (priorisiert)

| Prio | Item | Session / Phase | Hinweis |
|:---:|---|---|---|
| 1 | Block/Shortcode: „alle veröffentlichten dieses Templates“ (+ Ansicht, Limit) | Live-Pfad | Nach Permalinks |
| 2 | Relation auf Detailseite als Karten (1 Ebene: Name, Bild, Kontakt) | Live-Pfad | |
| 3 | Migrator: Fehlerbehandlung bei DDL (AD-5) | Härtung | Vor wachsendem Live-Datenbestand |
| 4 | Query: `whereIn()`, `orWhere()`, Frontend-Pagination | Phase 3 | |
| 5 | REST Phase 2 (Pagination, Templates-Liste, CORS) | Phase 2 | |

### Erledigt — öffentliche Detail-URLs (Okt 2026) ✓

- [x] Permalink `/{template}/{slug}` — Migration 1.0.6 (`slug` an Entries, unique pro Template)
- [x] Template-Setting „Öffentliche Detailseite“ (`public_detail`, Default aus)
- [x] Rewrite + Frontend-`single.php`, `Renderer::getEntryLink()`, Filter `bs_fabric_entry_url` bleibt
- [x] Slug im Entry-Editor (auto aus Titel, editierbar), REST-Feld `slug`

### Erledigt — WP-Hauptmenü pro Template (Okt 2026) ✓

- [x] Template-Setting `show_in_wp_menu` (Default aus; nur mit `show_in_menu`)
- [x] Zusätzlicher Top-Level-Menüpunkt (`dashicons-list-view`), Fabric-Unterpunkt bleibt
- [x] Menü-Highlight: Top-Level aktiv, Fabric nicht aufgezwungen aufgeklappt

Bewusste Entscheidung (Aug 2026): Die unten stehende „Härtung Runde 2“ blockiert Punkt 1–2 **nicht**. AD-3/AD-4/AD-12 (Unit-Suite grün, REST-Sichtbarkeit) sind erledigt, Gutenberg-Block + Shortcode ebenso (Sept 2026).

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
| P1 | `Migrator`: Fehlerbehandlung bei fehlgeschlagenen DDL-Schritten (aktuell stiller Fail, Version wird trotzdem hochgesetzt) | mittel | AD-5 |
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
- [x] Test-Suite lief nur zu ~74 % durch (stiller Absturz durch `exit()` in Template-Include, `ABSPATH` fehlte) — `tests/bootstrap.php` definiert `ABSPATH` jetzt, Suite läuft vollständig
- [x] **AD-3** — `WP_REST_Request`-Stub unter `tests/Stubs/` + Bootstrap; `RestQueryParserTest` lauffähig
- [x] **AD-4** — Relation-IDs in REST ohne Hydration: `Entry::getRelationIds()` + `EntrySerializer` nutzt Storage-IDs (auch wenn Ziel-Entries fehlen); Unit-Suite grün
- [x] Unit-Test-Nebenfixes: `esc_html_e`-Stub in `RendererTest`, Assertions in `EntryQueryTest` (Risky)
- [x] ARCHITECTURE.md §11 Selbstwiderspruch zum Uninstall-Verhalten korrigiert
- [x] Composer-Laufzeitabhängigkeit entfernt — `bs-fabric.php` lädt `BsFabric\`-Klassen über eigenen `spl_autoload_register()`-Autoloader statt `vendor/autoload.php`; Plugin ist ohne `composer install` in jede WordPress-Installation kopierbar. Composer/`vendor/` nur noch für die Testsuite. README.md entsprechend korrigiert (Requirements, Installation, `wp_doing_ajax()`-Hinweis)
- [x] Repo-Aufräumung — `.DS_Store` (2×) aus Tracking entfernt + gitignored; `Design System/` (Prototyp, laut eigener Doku bereits in Produktion überführt) und `WordpressPluginDesign.md` (plugin-übergreifendes Dokument, gehörte nicht hierher) entfernt, extern gesichert unter `~/Documents/bs-fabric-design-archive-2026-08-15/`; `bs-fabric.md` nach `.cursor/bs-fabric.md` verschoben (Cursor-Kontextdatei, Pfad entsprach schon immer der ARCHITECTURE.md-Doku); ARCHITECTURE.md-Verweise auf `Design System/` entsprechend korrigiert
- [x] **AD-12** — REST-Sichtbarkeit konfigurierbar (Migration 1.0.4): Template-Setting „In REST-API sichtbar" (Kill-Switch, Default an — kein Breaking Change) + Feld-Setting „REST verstecken" pro Template-Zuordnung (Default aus), analog zum bestehenden `is_required`-Muster. Durchsetzung in `EntriesController` (404 `bs_fabric_rest_template_not_found`, identisch für „existiert nicht" und „ist versteckt") und `EntrySerializer` (Feld wird beim JSON-Aufbau übersprungen). Admin-UI in TemplatesScreen + neuer AJAX-Handler. Tests: `TemplateSettingsTest`, `EntrySerializerTest` (Unit, grün), `TemplateRepositoryTest`/`EntriesControllerTest` (Integration, neue Fälle ergänzt). Doku: REST-API.md, ARCHITECTURE.md §6/§11

---

## Dokumentations-Konsolidierung + KI-Skills (nächste Session)

> Ziel: Doku so aufstellen, dass jede KI — nicht nur eine bestimmte — mit leerem Chat ohne Vorwissen produktiv weiterarbeiten kann, ohne dass dieselbe Information an mehreren Stellen gepflegt werden muss. Anlass: heute vier unabhängige Stale-Funde derselben Art (Feldtyp-Liste 2×, REST-Status, Kapitelverweis), weil es keine einzige Quelle der Wahrheit gab.

### Doku-Umbau

| Schritt | Beschreibung |
|---|---|
| `AGENTS.md` neu anlegen | Werkzeugneutraler, kanonischer Einstiegspunkt im Repo-Root: Kurz-Produktvision, Verweis auf ROADMAP.md als einzige Statusquelle, Grundregeln aus `.cursorrules`, Lesereihenfolge für Kaltstart, Verifikationspflicht statt Prosa-Vertrauen — **plus verbindliche Doku-Aktualisierungspflicht als Teil der Definition of Done** (ROADMAP-Checkbox setzen, betroffene ARCHITECTURE-/REST-API-Abschnitte nachziehen, bevor eine Session als fertig gilt) |
| `.cursorrules` kürzen | Bleibt am Root (Cursor braucht es dort für Auto-Loading), Inhalt auf Kurzverweis auf `AGENTS.md` reduzieren |
| `CLAUDE.md` neu anlegen | Kurzverweis auf `AGENTS.md`, analog zu `.cursorrules` |
| `.cursor/bs-fabric.md` auflösen | Inhalt vollständig nach `AGENTS.md` überführen, Datei entfernen |
| `README.md` bereinigen | Feldtyp-Liste korrigieren (13 Typen, `reference` fehlt aktuell), veralteten „Roadmap"-Abschnitt (Zeilen 365–394) durch Verweis auf ROADMAP.md ersetzen |
| `ARCHITECTURE.md` §14 trennen | Lebende Spezifikation vom historischen Session-Tagebuch abgrenzen, damit Ersteres nicht weiter mit Historie aufgebläht wird |

### KI-Skills

| Schritt | Beschreibung |
|---|---|
| `wp-abilities-audit` installieren + laufen lassen | Offizieller `wordpress/agent-skills`-Skill (WordPress-Projekt, 2K Stars), plugin-agnostisch; erstellt Inventar der bestehenden REST-Oberfläche (`EntriesController`, `RestPermissionChecker`) — Ergebnis fließt in AD-12 (REST-Sichtbarkeits-Entscheidung) und REST-Phase-2-Planung ein |
| `wp-rest-api` als Referenz vormerken | Für REST-Phase-2-Arbeit (Pagination, CORS, `GET /templates`) |
| `wp-abilities-api` / `wp-abilities-verify` | Bedingt — nur falls das Audit-Ergebnis eine echte Abilities-Implementierung nahelegt; Anknüpfung an Roadmap-Punkt „AI-Feldvorschläge (WP AI Client)" |
| `wordpress-pro` (jeffallan/claude-skills) prüfen | Community-Skill (7,8K Installs, 11K Stars), erzwingt `phpcs --standard=WordPress` im Workflow — Kandidat für laufende Coding-Standard-Durchsetzung (aktuell keine CI/Statik im Repo) |

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

- [x] Gutenberg-Block „Fabric-Inhalt" (`bs-fabric/entry-embed`) — ein Block statt zwei getrennter (`entry-list`/`single-entry`): Template- + Entry-Auswahl (Checkboxen) in der Inspector-Sidebar, `ServerSideRender`. Plus `[bs_fabric]`-Shortcode als Gutenberg-unabhängige Basis (Themes ohne Block-Editor sind dadurch nicht eingeschränkt) — beide teilen sich `EntryEmbedRenderer`. Nur `published`-Entries werden gerendert, auch wenn ein Entwurf ausgewählt ist
- [x] Redaktionsnotiz pro Entry (Migration 1.0.5, `editor_note`) — freier interner Text im Entry-Editor, gedacht um festzuhalten, wo ein Entry auf der Website eingebunden ist (z. B. „Hero auf der Startseite"). Bewusst außerhalb von `Entry`/`toArray()`/REST gehalten, kann strukturell nicht öffentlich auftauchen
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
- [x] **AD-12** — REST-Sichtbarkeit je Template und je Feld konfigurierbar (Migration 1.0.4), siehe „Erledigt" unten

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
