# Handoff: bs·fabric — WordPress-Plugin-Backend

> **Status (Juni 2026):** Design System **DS-0 bis DS-3 sind in Produktion umgesetzt.**
> Dieses Dokument und `prototype/` dienen weiterhin als **visuelle Referenz** —
> nicht als 1:1-Kopierquelle für neuen Code.
>
> Abweichungen Produktion vs. Prototyp: siehe [`ARCHITECTURE.md`](../ARCHITECTURE.md) §13 Strang E.
>
> **Für Cursor:** Bei UI-Arbeit zuerst `ARCHITECTURE.md` §4 lesen, dann dieses Dokument
> und die Dateien unter `prototype/`.

---

## 1. Überblick

**bs·fabric** ist das Admin-Backend eines Plugins für **strukturierte Inhalte**. Drei Kernobjekte:

1. **Fields** — wiederverwendbare Datenbausteine (Typ + Konfiguration). „Die Fäden."
2. **Templates** — Blaupausen, die Fields zu einer Struktur zusammenstellen. „Das Webmuster."
3. **Einträge (Entries)** — konkrete Datensätze, die Redakteure nach einer Template-Blaupause anlegen/pflegen. „Das gewebte Material."

Flow: *Field anlegen → in Template einsetzen → Redakteur füllt Eingabemaske → Eintrag erscheint in Liste, editierbar.*

**Fidelity: High-fidelity.** Farben, Typo, Spacing, Interaktionen sind final gemeint. Pixelgenau nachbauen, aber mit den Mitteln des Plugins.

---

## 2. Prototyp starten

`prototype/bs-fabric.html` im Browser öffnen (React + Babel via CDN, kein Build nötig). Navigation über die linke Sidebar. Alle Screens sind klickbar; Daten sind Mock-Daten aus `prototype/data.js`.

**Datei-Übersicht:**
| Datei | Inhalt |
|---|---|
| `styles.css` | Komplettes Design-System (Tokens + alle Komponenten-Styles) |
| `tokens.css` | (eine Ebene höher) Nur die Tokens, scoped auf `.bsf-app` — Drop-in für wp-admin |
| `data.js` | Datenmodell + Mock-Daten (`window.DB`): `TYPES`, `fields`, `templates`, `entries`, `STATUS` |
| `components.jsx` | Icons (Inline-SVG-Set) + UI-Primitive (Btn, Badge, Sheet, Sidebar, Topbar …) |
| `screens-fields.jsx` | Fields-Liste + Feld-Editor (Sheet) + Typ-Picker |
| `screens-templates.jsx` | Templates-Grid + Template-Builder + „Feld hinzufügen"-Menü |
| `screens-inputs.jsx` | Eingabe-Widgets je Feldtyp (RichText, Media, Relation, Repeater …) |
| `screens-data.jsx` | Daten-Liste (Tabelle/Karten) + Eingabemaske + Einzelansicht |
| `screens-overview.jsx` | Dashboard + Einstellungen |
| `app.jsx` | Routing (State-basiert) + Tweaks-Defaults |

---

## 3. Design-System

### 3.1 Schrift
- **Space Grotesk** — Seitentitel, Card-Titel, Logo, große Zahlen (Stat-Kacheln). Weights 400–700.
- **Hanken Grotesk** — alles UI/Fließtext. Basisgröße **14.5px**, line-height 1.5.
- **JetBrains Mono** — Field-Keys, IDs, API-Pfade, Status-„mono"-Badges. 11–13px.

**Skala:** Seitentitel 25px/600/-0.02em · Card-Titel 14px/600 · Form-Label 13px/600 · Hint 12px/muted · Tabellen-Header 11px/600/uppercase/letter-spacing 0.04em.

### 3.2 Farben (alle als `oklch`, siehe `tokens.css`)
Kühle, gering gesättigte Neutraltöne; **ein** Blau-Akzent + sparsamer Teal-„Faden". Wichtigste:
- Hintergrund App `oklch(0.975 0.004 255)`, Cards weiß, Rahmen `oklch(0.92 0.006 255)`.
- Text: `--ink` (0.24), `--ink-soft` (0.42), `--muted` (0.58), `--faint` (0.72).
- Akzent Blau `oklch(0.55 0.142 252)`, Hover `0.48`, soft-Background `0.95 0.03 252`.
- Status: ok=grün, warn=amber, danger=rot (jeweils + `-soft` Variante).
- Sidebar dunkel: `--side-bg oklch(0.24 0.02 265)`, heller Text.

### 3.3 Geometrie
Radien 6/9/13/18px. Drei Schatten-Stufen (sm für Cards/Buttons, lg für Overlays). Dichte-Token `--row-h/--pad/--gap`, umschaltbar (compact/standard/comfy).

### 3.4 Kern-Komponenten (in `styles.css` + `components.jsx`)
- **App-Shell:** `grid 248px / 1fr` — dunkle Sidebar links, heller Arbeitsbereich rechts mit 60px Topbar (Breadcrumb + Suche).
- **Buttons** `.btn` Varianten: `primary` (Akzent), `ghost`, `subtle`, `danger`; Größen normal/`sm`/`icon`.
- **Badge / StatusBadge / ftype** (Typ-Chip mit Icon) / `key` (Mono-Code-Pill).
- **Tabelle** `.tbl` — Uppercase-Header, sortierbar, Hover-Row, 44px Zeilenhöhe.
- **Form-Felder:** `.input/.textarea/.selectbox`, Switch, Checkbox, Segmented-Control `.seg`.
- **Card** `.card` mit `card-head/card-body`.
- **Sheet** (Slide-over von rechts, 480px) für den Feld-Editor; Scrim + Esc-to-close.
- **Toast** (unten zentriert), **Empty-State**, **Dropdown-Menü** (Feld-hinzufügen).
- **Icons:** komplettes Inline-SVG-Set in `components.jsx` (`ICONS`-Map). Keine Icon-Library nötig — kann 1:1 nach PHP/JS übernommen oder durch Dashicons/eine Lib ersetzt werden.

---

## 4. Screens (im Detail)

> Reihenfolge = Sidebar-Navigation. Jeder Screen hat einen `PageHead` (Titel + Lede + Aktions-Buttons rechts).

### 4.1 Übersicht (Dashboard) — `screens-overview.jsx → DashScreen`
- 4 Stat-Kacheln (Templates, Felder, Einträge gesamt, offene Entwürfe).
- Zwei Spalten: **Templates**-Liste (Klick → Builder) und **Zuletzt bearbeitet** (Klick → Einzelansicht).
- Aktionen: „Neues Feld" → Fields, „Neues Template" → Builder.

### 4.2 Fields — `screens-fields.jsx → FieldsScreen`
- **Tabelle** aller Felder: Bezeichnung (+ Pflicht-Badge + Beschreibung), Field-Key (mono), Typ (`TypeBadge`), Verwendung (in N Templates), Aktionen (Bearbeiten/Duplizieren).
- Suche + Typ-Filter im Card-Header.
- **Feld-Editor** als Sheet (`FieldEditor`): Bezeichnung → Key wird auto-slugifiziert (`slugify()`, ä→ae etc.), **visueller Typ-Picker** (`TypePicker`, gruppiert: Basis/Medien/Struktur), typ-spezifische Optionen (z. B. Min/Max/Einheit bei Zahl, „verweist auf" bei Beziehung), Beschreibung/Hilfetext, Pflichtfeld-Switch.
- **Feldtypen** (Prototyp `DB.TYPES`, vereinfacht): `text`, `textarea`, `richtext`, `number`, `media` (Bild/Datei), `relation`, `repeater`. **Produktion:** 12 Typen — getrennt `image`/`file`, plus `date`, `select`, `boolean`, `address`, `code` (siehe [`README.md`](../README.md)).

### 4.3 Templates — `screens-templates.jsx → TemplatesScreen`
- **Karten-Grid**: pro Template Icon, Name, Key, Beschreibung, #Felder, #Einträge, „zuletzt geändert". Klick → Builder. Plus „Template anlegen"-Kachel.

### 4.4 Template-Builder — `screens-templates.jsx → BuilderScreen`
**Das Herzstück.** Zwei Spalten (`1fr / 300px`):
- **Links — „Felder im Template":** sortierbare Liste der eingesetzten Felder. Jede Zeile (`BuilderRow`): Drag-Handle, Typ-Icon, Bezeichnung + Key + Typ, **Pflicht-Toggle**, Entfernen-Button. **Produktion:** SortableJS per Drag-Handle (nicht Hoch/Runter-Pfeile).
- **„Feld hinzufügen"** (`AddFieldMenu`): Dropdown mit Suche, listet **Felder aus der Bibliothek** (bereits genutzte ausgeschlossen) + „Neues Feld erstellen…". → genau die gewünschte *Klick/Dropdown*-Interaktion.
- **Rechts — Template-Einstellungen:** Key (auto-slug), Symbol-Picker, Beschreibung, Schalter (Im Menü anzeigen / Versionierung / Kommentare).
- Titel ist inline editierbar. Aktionen: „Vorschau" (→ Eingabemaske), „Speichern".

> Hinweis: Im Prototyp ist die Sortierung per Hoch/Runter-Pfeil gelöst (Drag-Handle ist Deko). **Produktion:** SortableJS am Drag-Handle (`assets/js/sortable.min.js`).

### 4.5 Redakteurs-Eingabemaske — `screens-data.jsx → EditorScreen`
- Wird **automatisch aus dem Template generiert**: iteriert über die Template-Felder und rendert je Typ das passende Widget (`FieldInput` in `screens-inputs.jsx`).
- Layout ein-/zweispaltig (Tweak `formCols`); „breite" Typen (richtext, textarea, media, repeater, relation) nehmen immer die volle Breite.
- **Widgets:** Text/Textarea/Number (Standard-Inputs), **RichText** (Mini-Editor mit B/I/Liste/Link/H — im Prototyp via `execCommand`; in Prod ggf. TinyMCE/`@wordpress/block-editor`), **Media** (Platzhalter-Kacheln „Aus Mediathek" → WP Media Library), **Relation** (Such-Dropdown + Chips), **Repeater** (Zeilen aus Label+Wert, add/remove).
- Rechte Sidebar: **Veröffentlichung** (Status-Radio: Entwurf/In Prüfung/Veröffentlicht), Meta (Erstellt/Autor/#Felder), Link „Im Builder öffnen".

### 4.6 Daten-Liste (Einträge) — `screens-data.jsx → DataScreen`
- **Template-Tabs** oben (welches Template wird gelistet).
- Suche + Status-Filter + **Ansicht-Umschalter Tabelle ↔ Karten** (Tweak `listView`).
- **Tabelle:** Checkbox-Spalte (Select-all + Bulk-Bar mit Veröffentlichen/Duplizieren/Löschen), sortierbare Spalten (Titel/Preis/Bestand/Geändert), Status-Badge, Zeile-Klick → Einzelansicht, Stift → Editor.
- **Karten:** Bild-Platzhalter + Status + Preis + Meta. Paginierung im Footer.

### 4.7 Einzelansicht — `screens-data.jsx → EntryScreen`
- Read-only Detail: Header (Icon, Titel, Status, `template·id`, „zuletzt von"), „Ansehen" (Frontend) + „Bearbeiten".
- **Inhaltsdaten** als Label/Wert-Paare (`180px / 1fr`-Grid), je Feldtyp passend gerendert.

### 4.8 Einstellungen — `screens-overview.jsx → SettingsScreen`
- Vertikale Tabs: **Allgemein** (Menü-Bezeichnung, Default-Status, Autosave/Key-Lock/Papierkorb-Switches), **Rollen & Rechte** (Matrix Rolle × Capability), **API & Export** (REST-Endpoint, API-Key, JSON/CSV-Export), **Erweitert** (Daten beim Deinstallieren löschen, Reset).

---

## 5. Interaktionen & States
- **Routing:** im Prototyp State-basiert (`route = {name, params}`), `go(name, params)` wechselt + scrollt nach oben. In WP → je Admin-Page eine URL (`?page=bsf-...&...`) oder SPA-Routing innerhalb einer Page.
- **Animationen:** Route-Wechsel `routein` (5px translateY, 0.24s ease). Sheet `slidein` (0.22s cubic-bezier(0.22,1,0.36,1)). Toast `toastin`. **Wichtig:** Sichtbarkeit nie an eine Animation koppeln (Opacity-Endzustand = sichtbar), sonst Render-Probleme.
- **Auto-Slug:** Bezeichnung tippen → Key wird generiert, bis der User den Key manuell anfasst (`keyTouched`).
- **Validierung (geplant):** Pflichtfelder vor Speichern prüfen; Key eindeutig & `^[a-z0-9_]+$`.
- **Tweaks** (nur Prototyp-Komfort, kein Produkt-Feature): Akzentfarbe, Sidebar hell/dunkel, Dichte, Listen-Ansicht, Spalten — via `data-*`-Attribute auf dem Wurzel-Element.

---

## 6. WordPress-Umsetzung (Empfehlung)

> Endgültige Architektur ist eure Entscheidung — hier die naheliegende Abbildung. Bitte an die **bestehenden Muster eures Plugins** anpassen.

### 6.1 Datenmodell
Drei Entitäten — empfohlen als **eigene DB-Tabellen** (sauberer als CPT/Postmeta für stark strukturierte Daten, aber CPT geht auch):
- `wp_bsf_fields` — id, label, field_key, type, config (JSON), description, required.
- `wp_bsf_templates` — id, name, template_key, icon, description, settings (JSON).
- `wp_bsf_template_fields` — template_id, field_id, position, required (Override) → die Blaupause/Reihenfolge.
- `wp_bsf_entries` — id, template_id, title, status (draft/review/published), author_id, created/updated.
- `wp_bsf_entry_values` — entry_id, field_id, value (JSON/longtext) → die gepflegten Daten.

`DB.TYPES` in `data.js` ist die Quelle für die Feldtyp-Definitionen. Status-Map siehe `DB.STATUS`.

### 6.2 Admin-Menü & Seiten
`add_menu_page('bs·fabric', …, 'bsf')` als Top-Level + Submenüs: Übersicht, Templates, Fields, Einträge, Einstellungen — exakt die Sidebar des Prototyps. (Die eigene dunkle Sidebar im Design ist optional: entweder ihr ersetzt nur den Content-Bereich und nutzt das WP-Menü, **oder** ihr rendert die Vollbild-Shell inkl. eigener Sidebar in *einer* Admin-Page.)

### 6.3 Rendering — zwei Wege
- **A) React in der Admin-Page** (näher am Prototyp): Container `<div id="bsf-root" class="bsf-app">`, App per `wp_enqueue_script` mounten. JSX aus `prototype/` als Vorlage; CDN-Babel raus, echtes Build-Setup (`@wordpress/scripts`) rein.
- **B) PHP-gerenderte Admin-Pages** + punktuelles JS (Builder-Sortierung, Media-Picker, Repeater). Markup/Klassen aus `styles.css` übernehmen.

In beiden Fällen: **`tokens.css` + `styles.css` nur auf Plugin-Seiten enqueuen**, alles unter `.bsf-app` gescoped, damit wp-admin nicht beeinflusst wird (`admin_enqueue_scripts` + Page-Check).

### 6.4 Datenliste
Entweder natives **`WP_List_Table`** (vertrauter WP-Look, Bulk-Actions/Sortierung gratis) **oder** die Custom-Tabelle/Karten aus dem Prototyp (näher am Design, Tabelle↔Karten-Umschalter). Empfehlung: Custom, da der Karten-Modus gewünscht ist.

### 6.5 REST-API
`register_rest_route('bs-fabric/v1', …)` für CRUD auf Fields/Templates/Entries — deckt den „API & Export"-Tab und ein React-Frontend ab. Settings über die **Settings API** (oder Options + REST).

### 6.6 Integrationen
- **Media-Felder** → `wp.media` Frame (Mediathek).
- **RichText** → TinyMCE (`wp_editor`) oder Block-Editor-Komponente statt `execCommand`.
- **Relation** → REST-Suche über Einträge/Posts.
- **Capabilities** → Rollen-Matrix auf `current_user_can()` mappen (eigene Caps: `bsf_manage_structure`, `bsf_edit_entries`, `bsf_publish_entries`).

---

## 7. Was NICHT übernehmen
- `tweaks-panel.jsx` + die Tweak-Logik in `app.jsx` (reines Prototyp-Tooling).
- CDN-`<script>`-Tags für React/Babel (durch echtes Build ersetzen).
- Mock-Daten in `data.js` (nur als Schema/Beispiel lesen).

---

## 8. Kurz-Checkliste für Cursor
1. `tokens.css` ins Plugin, scoped auf `.bsf-app`, auf Plugin-Seiten enqueuen.
2. DB-Tabellen + Aktivierungs-Migration anlegen (Abschnitt 6.1).
3. Admin-Menü + Seiten registrieren (6.2).
4. Screens nachbauen — Reihenfolge: Fields → Templates/Builder → Eingabemaske → Daten-Liste → Einzelansicht → Einstellungen.
5. REST-Endpoints + Settings.
6. Integrationen (Media, RichText, Relation, Caps).
7. Komponenten-Styles aus `styles.css` als Referenz für Markup/Klassen.
